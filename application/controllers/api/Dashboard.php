<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET /api/dashboard/bd/{uid}
     */
    public function bd($uid = null)
    {
        $user = $this->api->require_user();
        $uid = (int) ($uid ?: $user['user_id']);
        $tdate = date('Y-m-d');
        $rows = $this->Menu_model->get_totalt($uid, $tdate);
        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = $this->api->format_task_row($row);
        }
        $this->api->mobile_ok([
            'uid' => $uid,
            'date' => $tdate,
            'open_tasks' => count($tasks),
            'tasks' => $tasks,
            'day' => $this->api->day_state($uid, $tdate),
            'discipline' => $this->api->build_discipline_state($uid, $tdate),
        ]);
    }
}
