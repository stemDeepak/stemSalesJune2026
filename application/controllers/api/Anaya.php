<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Anaya extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function briefing()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $tdate = date('Y-m-d');
        $tasks = $this->Menu_model->get_totalt($uid, $tdate);
        $this->api->mobile_ok([
            'uid' => $uid,
            'summary' => 'You have ' . count($tasks) . ' open tasks today.',
            'task_count' => count($tasks),
            'stub' => true,
        ]);
    }

    public function today()
    {
        return $this->briefing();
    }
}
