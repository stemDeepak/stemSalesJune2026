<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Leads extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET /api/leads?uid=&limit=
     */
    public function index()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $limit = min(100, max(1, (int) ($in['limit'] ?? 50)));

        $q = $this->db->query("
            SELECT init_call.id AS cid_id, init_call.cstatus,
                   company_master.id AS company_id, company_master.compname,
                   status.name AS status_name
            FROM init_call
            INNER JOIN company_master ON company_master.id = init_call.cmpid_id
            LEFT JOIN status ON status.id = init_call.cstatus
            WHERE init_call.user_id = ?
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

        $this->api->mobile_ok([
            'leads' => $rows,
            'rows' => $rows,
            'count' => count($rows),
        ]);
    }
}
