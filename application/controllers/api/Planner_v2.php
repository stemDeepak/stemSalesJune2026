<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Planner_v2 extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function pending()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $tdate = $this->api->input()['date'] ?? date('Y-m-d');
        $rows = $this->Menu_model->get_totalt($uid, $tdate);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out), 'date' => $tdate]);
    }

    public function pending_carry()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $rows = $this->Menu_model->get_OLDPendingTask($uid);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->api->format_task_row($r);
        }
        $this->api->mobile_ok(['rows' => $out, 'count' => count($out)]);
    }

    public function leads()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $q = $this->db->query("
            SELECT init_call.id AS cid_id, company_master.compname, init_call.cstatus
            FROM init_call
            INNER JOIN company_master ON company_master.id = init_call.cmpid_id
            WHERE init_call.user_id = ?
            ORDER BY init_call.id DESC
            LIMIT 100
        ", [$uid]);
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = [
                'cid_id' => (int) $r->cid_id,
                'cname' => $r->compname,
                'cstatus' => (int) $r->cstatus,
            ];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }

    public function today_not_started()
    {
        return $this->pending();
    }

    public function purposes_v2()
    {
        $this->api->require_user();
        $this->api->mobile_ok([
            'rows' => [
                ['id' => 1, 'label' => 'Introduction'],
                ['id' => 2, 'label' => 'Follow-up'],
                ['id' => 3, 'label' => 'Proposal discussion'],
            ],
        ]);
    }
}
