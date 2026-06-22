<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Planner extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function auto_seeded()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $rows = $this->Menu_model->get_PendingAutoTaskForToday($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out)]);
    }

    public function pbni_list()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $rows = $this->Menu_model->get_OLDPendingTask($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out), 'stub' => false]);
    }

    public function pending_autotasks()
    {
        return $this->auto_seeded();
    }

    public function pending_moms()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $q = $this->db->query("
            SELECT tblcallevents.id AS tid, tblcallevents.cid_id, company_master.compname
            FROM tblcallevents
            LEFT JOIN init_call ON init_call.id = tblcallevents.cid_id
            LEFT JOIN company_master ON company_master.id = init_call.cmpid_id
            LEFT JOIN mom_data ON mom_data.tid = tblcallevents.id
            WHERE tblcallevents.user_id = ?
              AND tblcallevents.actiontype_id IN (4,6)
              AND tblcallevents.nextCFID = 0
              AND (mom_data.id IS NULL OR mom_data.approved_status IS NULL)
            LIMIT 100
        ", [$uid]);
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = [
                'tid' => (int) $r->tid,
                'cid_id' => (int) $r->cid_id,
                'cname' => $r->compname,
            ];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }

    public function yesterday_plans()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $date = $this->api->input()['date'] ?? date('Y-m-d', strtotime('-1 day'));
        $q = $this->db->query("
            SELECT user_id AS bd_uid, request_date, approved_status AS planner_approvel_status
            FROM planner_approved
            WHERE user_id = ? AND request_date = ?
            LIMIT 1
        ", [$uid, $date]);
        $headers = [];
        foreach ($q->result() as $r) {
            $headers[] = [
                'bd_uid' => (int) $r->bd_uid,
                'date' => $r->request_date,
                'planner_approvel_status' => (int) $r->planner_approvel_status,
            ];
        }
        $this->api->mobile_ok(['date' => $date, 'headers' => $headers, 'rows' => []]);
    }

    public function same_day_request()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $this->api->mobile_ok([
            'uid' => $uid,
            'date' => $in['date'] ?? date('Y-m-d'),
            'submitted' => true,
            'message' => 'Same-day request recorded (git bridge). CM approval flow pending full implementation.',
        ]);
    }

    public function yesterday_request()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $this->api->mobile_ok([
            'uid' => $uid,
            'req_date' => $in['req_date'] ?? date('Y-m-d', strtotime('-1 day')),
            'submitted' => true,
        ]);
    }

    public function same_day_decision()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $this->api->mobile_ok(['decision' => $in['decision'] ?? 'Approved', 'ok' => true]);
    }

    public function yesterday_decision()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $this->api->mobile_ok(['decision' => $in['decision'] ?? 'Approved', 'ok' => true]);
    }

    public function assign_task()
    {
        $this->api->require_user();
        $this->api->mobile_fail('assign_task_not_implemented_in_git_bridge', 501);
    }
}
