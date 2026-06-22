<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Task extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET/POST api/task/today — M047 dashboard shape.
     */
    public function today()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $tdate = $in['date'] ?? $in['tdate'] ?? date('Y-m-d');

        $rows = $this->Menu_model->get_totalt($uid, $tdate);
        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = $this->api->format_task_row($row);
        }
        $tabs = $this->api->bucket_tasks_into_tabs($tasks);
        $day = $this->api->day_state($uid, $tdate);

        $this->api->mobile_ok([
            'date' => $tdate,
            'uid' => $uid,
            'tasks' => $tasks,
            'rows' => $tasks,
            'tabs' => $tabs,
            'count' => count($tasks),
            'day_start_done' => in_array($day['state'], ['started', 'closed'], true),
            'day_closed' => $day['state'] === 'closed',
            'preflight' => ['hard_gates' => [], 'soft_gates' => []],
            'created_pending' => [],
            'auto_pending' => [],
        ]);
    }

    public function detail()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $tid = (int) ($in['tid'] ?? $in['task_id'] ?? $in['id'] ?? 0);
        if ($tid <= 0) {
            $this->api->mobile_fail('task id required', 422);
        }
        $row = $this->api->task_row_by_id($tid);
        if (!$row) {
            $this->api->mobile_fail('Task not found', 404);
        }
        $task = $this->api->format_task_row($row);
        $this->api->mobile_ok(['task' => $task, 'data' => $task]);
    }

    public function submit()
    {
        $this->_submit_core();
    }

    public function submit_closure()
    {
        $this->_submit_core();
    }

    public function submit_closure_timeline()
    {
        $this->_submit_core();
    }

    public function save_draft()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['saved_at' => date('c'), 'stub' => true]);
    }

    public function preflight()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $disc = $this->api->build_discipline_state($uid);
        $hard = [];
        if ($disc['next_required_screen'] === 'DayCeremonyV2') {
            $hard[] = ['code' => 'day_start', 'label' => 'Start your day first', 'fix_route' => 'DayCeremony'];
        }
        if ($disc['pending_autotask_count'] > 0) {
            $hard[] = ['code' => 'autotasks', 'label' => 'Complete pending auto tasks', 'fix_route' => 'DayManagement'];
        }
        $this->api->mobile_ok([
            'hard_gates' => $hard,
            'soft_gates' => [],
        ]);
    }

    protected function _submit_core()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $in = $this->api->input();

        $tid = (int) ($in['tid'] ?? $in['task_id'] ?? 0);
        if ($tid <= 0) {
            $this->api->mobile_fail('tid required', 422);
        }

        $cmpid = (int) ($in['cmpid'] ?? $in['company_id'] ?? 0);
        $actontaken = $in['actontaken'] ?? ($in['action_taken'] ?? 'yes');
        $action_id = $in['action_id'] ?? $in['yaction_id'] ?? $in['actiontype_id'] ?? '';
        $status = $in['ystatus'] ?? $in['status'] ?? $in['ccstatus'] ?? 1;
        $remark = $in['remark'] ?? $in['yremark_msg'] ?? $in['nremark_msg'] ?? $in['noremark'] ?? '';
        $purpose = $in['purpose'] ?? 'no';
        $rpmmom = $in['rpmmom'] ?? 'null';
        $mom_id = $in['mom_id'] ?? '';
        $partner = $in['partner'] ?? '';
        $propasal_types = $in['propasal_types'] ?? '';
        $noofsc = $in['noofsc'] ?? ($in['no_of_school'] ?? '');
        $pbudgetme = $in['pbudgetme'] ?? ($in['revenue'] ?? '');
        $late_remarks_message = $in['late_remarks_message'] ?? '';

        $flink = 0;
        $flink1 = 0;
        $flink2 = 0;
        if (!empty($_FILES['filname']['name'])) {
            $flink = $this->Menu_model->uploadfile($_FILES['filname']['name'], 'uploads/proposal/');
        }
        if (!empty($_FILES['filname1']['name'])) {
            $flink1 = $this->Menu_model->uploadfile($_FILES['filname1']['name'], 'uploads/proposal/');
        }
        if (!empty($_FILES['filname2']['name'])) {
            $flink2 = $this->Menu_model->uploadfile($_FILES['filname2']['name'], 'uploads/proposal/');
        }

        $social = ['LinkedIn' => '', 'Facebook' => '', 'YouTube' => '', 'Instagram' => '', 'OtherSocial' => ''];
        foreach (array_keys($social) as $k) {
            if (isset($in[$k])) {
                $social[$k] = $in[$k];
            }
        }

        try {
            $this->Menu_model->submit_task1(
                $tid,
                $uid,
                $cmpid,
                $actontaken,
                $action_id,
                $status,
                $remark,
                $rpmmom,
                $purpose,
                $flink,
                $flink1,
                $flink2,
                $partner,
                $propasal_types,
                $noofsc,
                $pbudgetme,
                $social['LinkedIn'],
                $social['Facebook'],
                $social['YouTube'],
                $social['Instagram'],
                $social['OtherSocial'],
                $late_remarks_message,
                $mom_id
            );
        } catch (Exception $e) {
            $this->api->mobile_fail('Task submit failed: ' . $e->getMessage(), 500);
        }

        $rows = $this->Menu_model->getTBLTaskByDingleID($tid);
        $row = !empty($rows) ? $rows[0] : null;

        $this->api->mobile_ok([
            'task_id' => $tid,
            'completed' => $row ? ((int) $row->nextCFID !== 0) : null,
            'nextCFID' => $row ? (int) $row->nextCFID : null,
        ]);
    }
}
