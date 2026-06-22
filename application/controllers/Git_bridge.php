<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Flat mobile API bridge — root-level controller (staging-safe).
 */
class Git_bridge extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function discipline_state()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $type_id = (int) ($user['type_id'] ?? 0);
        $tdate = $in['date'] ?? $in['tdate'] ?? date('Y-m-d');
        $this->api->mobile_ok($this->api->build_discipline_state($uid, $tdate, $type_id));
    }

    public function task_today()
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
        $day = $this->api->day_state($uid, $tdate);
        $this->api->mobile_ok([
            'date' => $tdate,
            'uid' => $uid,
            'tasks' => $tasks,
            'rows' => $tasks,
            'tabs' => $this->api->bucket_tasks_into_tabs($tasks),
            'count' => count($tasks),
            'day_start_done' => in_array($day['state'], ['started', 'closed'], true),
            'day_closed' => $day['state'] === 'closed',
            'preflight' => ['hard_gates' => [], 'soft_gates' => []],
        ]);
    }

    public function planner_auto_seeded()
    {
        $user = $this->api->require_user();
        $uid = $this->api->resolve_request_uid($user, $this->api->input());
        $rows = $this->api->pending_autotask_rows($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out)]);
    }

    public function planner_pbni_list()
    {
        $user = $this->api->require_user();
        $uid = $this->api->resolve_request_uid($user, $this->api->input());
        $rows = $this->Menu_model->get_OLDPendingTask($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out)]);
    }

    public function leads_index()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $limit = min(100, max(1, (int) ($in['limit'] ?? 50)));
        $q = $this->db->query("
            SELECT init_call.id AS cid_id, init_call.cstatus,
                   company_master.id AS company_id, company_master.compname,
                   status.name AS status_name
            FROM init_call
            INNER JOIN company_master ON company_master.id = init_call.cmpid_id
            LEFT JOIN status ON status.id = init_call.cstatus
            WHERE init_call.mainbd = ?
            ORDER BY init_call.id DESC
            LIMIT ?
        ", [$uid, $limit]);
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = [
                'cid_id' => (int) $r->cid_id,
                'company_id' => (int) $r->company_id,
                'compname' => $r->compname,
                'cstatus' => (int) $r->cstatus,
                'status_name' => $r->status_name,
            ];
        }
        $this->api->mobile_ok(['leads' => $rows, 'rows' => $rows, 'count' => count($rows)]);
    }

    public function leads_detail($cid_id = null)
    {
        $this->api->require_user();
        $cid_id = (int) ($cid_id ?? $this->api->input()['cid_id'] ?? 0);
        if ($cid_id <= 0) {
            $this->api->mobile_fail('cid_id required', 422);
        }
        $q = $this->db->query("
            SELECT init_call.id AS cid_id, init_call.cstatus, init_call.mainbd,
                   company_master.compname, status.name AS status_name
            FROM init_call
            INNER JOIN company_master ON company_master.id = init_call.cmpid_id
            LEFT JOIN status ON status.id = init_call.cstatus
            WHERE init_call.id = ? LIMIT 1
        ", [$cid_id]);
        $row = $q->row();
        if (!$row) {
            $this->api->mobile_fail('Lead not found', 404);
        }
        $this->api->mobile_ok(['lead' => [
            'cid_id' => (int) $row->cid_id,
            'compname' => $row->compname,
            'cstatus' => (int) $row->cstatus,
            'status_name' => $row->status_name,
            'mainbd' => (int) $row->mainbd,
        ]]);
    }

    public function mom_queue()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $cm_uid = (int) ($in['cm_uid'] ?? $in['uid'] ?? $user['user_id']);
        $q = $this->db->query("
            SELECT mom_data.id, mom_data.tid, company_master.compname AS cname
            FROM mom_data
            INNER JOIN tblcallevents ON tblcallevents.id = mom_data.tid
            INNER JOIN user_details bd ON bd.user_id = tblcallevents.user_id
            LEFT JOIN init_call ON init_call.id = tblcallevents.cid_id
            LEFT JOIN company_master ON company_master.id = init_call.cmpid_id
            WHERE (mom_data.approved_status IS NULL OR mom_data.approved_status = '')
              AND bd.admin_id = ?
            ORDER BY mom_data.id DESC LIMIT 100
        ", [$cm_uid]);
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = ['id' => (int) $r->id, 'mom_id' => (int) $r->id, 'tid' => (int) $r->tid, 'cname' => $r->cname];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }

    /** Delegate to api/* controllers (staging-safe flat entrypoints). */
    protected function _run_api($file, $class, $method, ...$args)
    {
        require_once APPPATH . 'controllers/api/' . $file;
        $c = new $class();
        return call_user_func_array([$c, $method], $args);
    }

    public function me_role()
    {
        return $this->_run_api('Me.php', 'Me', 'role');
    }

    public function me_index()
    {
        return $this->_run_api('Me.php', 'Me', 'index');
    }

    public function task_detail()
    {
        return $this->_run_api('Task.php', 'Task', 'detail');
    }

    public function task_submit()
    {
        return $this->_run_api('Task.php', 'Task', 'submit');
    }

    public function task_submit_closure()
    {
        return $this->_run_api('Task.php', 'Task', 'submit_closure');
    }

    public function task_preflight()
    {
        return $this->_run_api('Task.php', 'Task', 'preflight');
    }

    public function dashboard_bd($uid = null)
    {
        return $this->_run_api('Dashboard.php', 'Dashboard', 'bd', $uid);
    }

    public function planner_same_day_request()
    {
        return $this->_run_api('Planner.php', 'Planner', 'same_day_request');
    }

    public function planner_yesterday_request()
    {
        return $this->_run_api('Planner.php', 'Planner', 'yesterday_request');
    }

    public function mom_approve()
    {
        return $this->_run_api('Mom.php', 'Mom', 'approve');
    }

    public function mom_reject()
    {
        return $this->_run_api('Mom.php', 'Mom', 'reject');
    }
}
