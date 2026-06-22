<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Legacy Api/login — delegates to same auth as web (user_details).
 * Prefer POST api/auth/login for mobile clients.
 */
class Api extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    public function login()
    {
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'mobile');

        $in = $this->mobile->input();
        $username = trim($in['username'] ?? $this->input->post('username') ?? '');
        $password = trim($in['password'] ?? $this->input->post('password') ?? '');

        if ($username === '' || $password === '') {
            $this->mobile->mobile_fail('missing_credentials', 422);
        }

        $row = $this->Menu_model->user_login_check($username, $password);
        if (empty($row)) {
            $this->mobile->mobile_fail('invalid_credentials', 401);
        }

        $token = $this->mobile->issue_token($row);
        $this->mobile->mobile_ok($this->mobile->mobile_login_payload($row, $token));
    }
}
