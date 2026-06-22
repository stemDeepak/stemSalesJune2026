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
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $rows = $this->api->pending_autotask_rows($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out)]);
    }

    public function pbni_list()
    {
        $user = $this->api->require_user();
        $uid = $this->api->resolve_request_uid($user, $this->api->input());
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
        $uid = $this->api->resolve_request_uid($user, $this->api->input());
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
        $uid = $this->api->resolve_request_uid($user, $in);
        $date = $in['date'] ?? date('Y-m-d');
        $remarks = trim($in['reason'] ?? $in['remarks'] ?? '');
        $count = count($this->api->pending_autotask_rows($uid));
        $this->db->insert('create_planner_request', [
            'request_user_id' => $uid,
            'request_type' => 'same_day',
            'request_date' => $date,
            'task_count' => $count,
            'request_remarks' => $remarks,
        ]);
        $this->api->mobile_ok([
            'ok' => true,
            'uid' => $uid,
            'date' => $date,
            'request_id' => (int) $this->db->insert_id(),
            'submitted' => true,
        ]);
    }

    public function yesterday_request()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $req_date = $in['req_date'] ?? $in['date'] ?? date('Y-m-d', strtotime('-1 day'));
        $remarks = trim($in['reason'] ?? $in['remarks'] ?? '');
        $pbni = $this->Menu_model->get_OLDPendingTask($uid);
        $count = is_array($pbni) ? count($pbni) : 0;
        $this->db->insert('create_planner_request', [
            'request_user_id' => $uid,
            'request_type' => 'pbni_clear',
            'request_date' => $req_date,
            'task_count' => $count,
            'request_remarks' => $remarks,
        ]);
        $this->api->mobile_ok([
            'ok' => true,
            'uid' => $uid,
            'req_date' => $req_date,
            'request_id' => (int) $this->db->insert_id(),
            'submitted' => true,
        ]);
    }

    public function same_day_decision()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $req_id = (int) ($in['request_id'] ?? $in['id'] ?? 0);
        $decision = $in['decision'] ?? $in['action'] ?? 'Approved';
        if ($req_id > 0) {
            $this->db->where('id', $req_id);
            $this->db->update('create_planner_request', [
                'approved' => ($decision === 'Approved' || $decision === 'approve') ? 1 : 0,
                'approved_by' => (int) $user['user_id'],
                'approved_date' => date('Y-m-d H:i:s'),
                'approved_message' => $in['remarks'] ?? '',
            ]);
        }
        $this->api->mobile_ok(['decision' => $decision, 'ok' => true, 'request_id' => $req_id]);
    }

    public function yesterday_decision()
    {
        return $this->same_day_decision();
    }

    public function assign_task()
    {
        $this->api->require_user();
        $this->api->mobile_fail('assign_task_not_implemented_in_git_bridge', 501);
    }
}
