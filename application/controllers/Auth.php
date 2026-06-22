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

        $row = $this->Menu_model->user_login_check($username, $password);
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

        $this->db->insert('login_session', [
            'user_id' => $row->user_id,
            'log_in' => 'yes',
        ]);

        $this->api->mobile_ok($this->api->mobile_login_payload($row, $token));
    }
}
