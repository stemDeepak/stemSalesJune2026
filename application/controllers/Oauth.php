<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Oauth extends CI_Controller {

    public function discovery()
    {
        header('Content-Type: application/json');

        echo json_encode([
            "issuer" => "https://stemapp.in",
            "authorization_endpoint" => "https://stemapp.in/oauth/authorize",
            "token_endpoint" => "https://stemapp.in/oauth/token",
            "response_types_supported" => ["code"],
            "grant_types_supported" => ["authorization_code"],
            "code_challenge_methods_supported" => ["S256"]
        ]);
    }

    public function authorize()
    {
        $user = $this->session->userdata('user');

        // Login nahi hai
        if(empty($user))
        {
            redirect('login');
            return;
        }

        // Login hai
        echo "User Logged In: ".$user['user_id'];
    }

    public function token()
    {
        echo "OAuth Token Endpoint Working";
    }
}