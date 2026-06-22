<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reminder extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function list()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['rows' => [], 'reminders' => [], 'count' => 0, 'stub' => true]);
    }

    public function create()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['created' => true, 'stub' => true]);
    }

    public function ack()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['acknowledged' => true, 'stub' => true]);
    }
}
