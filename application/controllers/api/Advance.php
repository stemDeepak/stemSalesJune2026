<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Advance extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function my()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $this->api->mobile_ok(['rows' => [], 'advances' => [], 'uid' => $uid, 'stub' => true]);
    }

    public function request()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $this->api->mobile_ok([
            'submitted' => true,
            'event_id' => $in['event_id'] ?? null,
            'amount' => $in['amount'] ?? null,
            'stub' => true,
            'message' => 'Advance request logged; full travel_advance table wiring pending.',
        ]);
    }

    public function unsettled()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['rows' => [], 'count' => 0, 'stub' => true]);
    }

    public function queue()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['rows' => [], 'count' => 0, 'stub' => true]);
    }
}
