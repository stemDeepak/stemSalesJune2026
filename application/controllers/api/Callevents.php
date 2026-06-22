<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Callevents extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET/POST api/callevents/list
     * Pending + completed tasks for date (golden rule: nextCFID 0=pending).
     */
    public function list()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $in = $this->api->input();
        $tdate = $in['date'] ?? $in['tdate'] ?? date('Y-m-d');

        $pending = $this->Menu_model->get_pendingt($uid, $tdate);
        $planned = $this->Menu_model->get_totalt($uid, $tdate);

        $pendingOut = [];
        foreach ($pending as $row) {
            $pendingOut[] = $this->api->format_task_row($row);
        }
        $plannedOut = [];
        foreach ($planned as $row) {
            $plannedOut[] = $this->api->format_task_row($row);
        }

        $this->api->mobile_ok([
            'date' => $tdate,
            'day' => $this->api->day_state($uid, $tdate),
            'pending' => $pendingOut,
            'planned_open' => $plannedOut,
            'rows' => $plannedOut,
            'tasks' => $plannedOut,
            'counts' => [
                'pending' => count($pendingOut),
                'planned_open' => count($plannedOut),
            ],
        ]);
    }
}
