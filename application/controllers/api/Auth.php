<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * POST api/auth/login
     */
    public function login()
    {
        $in = $this->api->input();
        $username = trim($in['username'] ?? $in['email'] ?? $in['user'] ?? '');
        $password = trim($in['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->api->mobile_fail('missing_credentials', 422);
        }

        $row = $this->Menu_model->user_login_check($username, $password);
        if (empty($row) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $q = $this->db
                ->select('id,name,zone_id,email,photo,type_id,user_id,admin_id,password,username')
                ->from('user_details')
                ->where('email', $username)
                ->where('status', 'active')
                ->limit(1)
                ->get();
            $erow = $q->row();
            if (!empty($erow) && (string) $password === (string) $erow->password) {
                $row = $erow;
            }
        }
        if (empty($row)) {
            $this->api->mobile_fail('invalid_credentials', 401);
        }

        $token = $this->api->issue_token($row);
        $payload = $this->api->mobile_login_payload($row, $token);

        $sess = [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'photo' => (string) $row->photo,
            'type_id' => (string) $row->type_id,
            'user_id' => (string) $row->user_id,
        ];
        $this->load->library('session');
        $this->session->set_userdata('user', $sess);

        $this->db->insert('login_session', [
            'user_id' => $row->user_id,
            'log_in' => 'yes',
        ]);

        $this->api->mobile_ok($payload);
    }

    /**
     * GET /api/auth/me
     */
    public function me()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $rows = $this->Menu_model->get_userbyid($uid);
        $row = !empty($rows) ? $rows[0] : null;
        if (!$row) {
            $this->api->mobile_fail('user_not_found', 404);
        }
        $this->api->mobile_ok([
            'uid' => $uid,
            'user_details_id' => (int) $row->id,
            'name' => $row->name,
            'type_id' => (int) $row->type_id,
            'role' => $this->api->role_for_type($row->type_id),
            'photo' => $row->photo ?? '',
            'zone_id' => isset($row->zone_id) ? (int) $row->zone_id : 0,
        ]);
    }
}
