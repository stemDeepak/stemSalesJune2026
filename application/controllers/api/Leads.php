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
        $uid = $this->api->resolve_request_uid($user, $in);
        $limit = min(100, max(1, (int) ($in['limit'] ?? 50)));

        $q = $this->db->query("
            SELECT init_call.id AS cid_id, init_call.cstatus, init_call.mainbd,
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

        $this->api->mobile_ok([
            'leads' => $rows,
            'rows' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * GET /api/lead/detail/{cid_id} — thin wrapper for lead header card.
     */
    public function detail($cid_id = null)
    {
        $this->api->require_user();
        $cid_id = (int) ($cid_id ?? $this->api->input()['cid_id'] ?? 0);
        if ($cid_id <= 0) {
            $this->api->mobile_fail('cid_id required', 422);
        }
        $q = $this->db->query("
            SELECT init_call.id AS cid_id, init_call.cstatus, init_call.mainbd,
                   company_master.id AS company_id, company_master.compname,
                   status.name AS status_name, status.color AS status_color,
                   partner_master.name AS partner_name
            FROM init_call
            INNER JOIN company_master ON company_master.id = init_call.cmpid_id
            LEFT JOIN status ON status.id = init_call.cstatus
            LEFT JOIN partner_master ON partner_master.id = company_master.partnerType_id
            WHERE init_call.id = ?
            LIMIT 1
        ", [$cid_id]);
        $row = $q->row();
        if (!$row) {
            $this->api->mobile_fail('Lead not found', 404);
        }
        $this->api->mobile_ok([
            'lead' => [
                'cid_id' => (int) $row->cid_id,
                'company_id' => (int) $row->company_id,
                'compname' => $row->compname,
                'cstatus' => (int) $row->cstatus,
                'status_name' => $row->status_name,
                'status_color' => $row->status_color,
                'mainbd' => (int) $row->mainbd,
                'partner_name' => $row->partner_name,
            ],
        ]);
    }
}
