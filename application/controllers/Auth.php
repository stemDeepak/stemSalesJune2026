<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mobile login entry: POST /auth/api_login (FormData or JSON username/password).
 */
class Auth extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function api_login()
    {
        $in = $this->api->input();
        $username = trim($in['username'] ?? $in['email'] ?? $in['user'] ?? $in['mobile'] ?? '');
        $password = trim($in['password'] ?? $in['otp'] ?? '');

        if ($username === '' || $password === '') {
            $this->api->mobile_fail('missing_credentials', 422);
        }

        $row = $this->_resolve_login_row($username, $password);
        if (empty($row)) {
            $this->api->mobile_fail('invalid_credentials', 401);
        }

        $token = $this->api->issue_token($row);

        $sess = [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'photo' => (string) $row->photo,
            'type_id' => (string) $row->type_id,
            'user_id' => (string) $row->user_id,
        ];
        $this->load->library('session');
        $this->session->set_userdata('user', $sess);

        try {
            $this->db->insert('login_session', [
                'user_id' => $row->user_id,
                'log_in' => 'yes',
            ]);
        } catch (Throwable $e) {
            log_message('error', 'login_session insert skipped: ' . $e->getMessage());
        }

        $this->api->mobile_ok($this->api->mobile_login_payload($row, $token));
    }

    /**
     * Exact username, email, or unique short alias (e.g. rimly -> Rimly.Chakraborty).
     */
    protected function _resolve_login_row($username, $password)
    {
        $row = $this->Menu_model->user_login_check($username, $password);
        if (!empty($row)) {
            return $row;
        }

        $cols = 'id,name,zone_id,email,photo,type_id,user_id,admin_id,password,username';
        $candidates = [];

        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $q = $this->db->select($cols)->from('user_details')
                ->where('email', $username)->where('status', 'active')->limit(1)->get();
            if ($q->row()) {
                $candidates[] = $q->row();
            }
        } else {
            $needle = strtolower($username);
            $q = $this->db->query(
                "SELECT {$cols} FROM user_details
                 WHERE status = 'active'
                   AND (
                     LOWER(username) = ?
                     OR LOWER(username) LIKE ?
                     OR LOWER(username) LIKE ?
                     OR LOWER(email) LIKE ?
                   )
                 LIMIT 5",
                [$needle, $needle . '.%', $needle . '%', $needle . '@%']
            );
            $candidates = $q->result();
        }

        $matches = [];
        foreach ($candidates as $c) {
            if ($this->_password_matches($password, $c->password)) {
                $matches[] = $c;
            }
        }
        if (count($matches) === 1) {
            return $matches[0];
        }
        if (count($matches) > 1) {
            $this->api->mobile_fail('ambiguous_username', 409);
        }
        return null;
    }

    protected function _password_matches($pwd, $stored)
    {
        $pwd = (string) $pwd;
        $stored = trim((string) $stored);
        if ($pwd === $stored) {
            return true;
        }
        if ($stored !== '' && md5($pwd) === $stored) {
            return true;
        }
        return false;
    }
}
