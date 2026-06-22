<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mom extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function approval_queue()
    {
        $user = $this->api->require_user();
        $uid = (int) ($this->api->input()['uid'] ?? $user['user_id']);
        $q = $this->db->query("
            SELECT mom_data.id, mom_data.tid, mom_data.approved_status,
                   company_master.compname AS cname
            FROM mom_data
            LEFT JOIN tblcallevents ON tblcallevents.id = mom_data.tid
            LEFT JOIN init_call ON init_call.id = tblcallevents.cid_id
            LEFT JOIN company_master ON company_master.id = init_call.cmpid_id
            WHERE mom_data.approved_status IS NULL
            LIMIT 100
        ");
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = [
                'id' => (int) $r->id,
                'mom_id' => (int) $r->id,
                'tid' => (int) $r->tid,
                'cname' => $r->cname,
            ];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }

    public function templates()
    {
        $this->api->require_user();
        $this->api->mobile_ok([
            'templates' => [
                ['id' => 'standard', 'name' => 'Standard MoM'],
                ['id' => 'rp', 'name' => 'RP Meeting MoM'],
            ],
        ]);
    }

    public function transcribe()
    {
        $this->api->require_user();
        $this->api->mobile_ok([
            'transcript' => '',
            'text' => '',
            'stub' => true,
            'message' => 'Audio transcription requires staging AI service or manual MoM entry.',
        ]);
    }

    public function approve()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $mom_id = (int) ($in['mom_id'] ?? 0);
        if ($mom_id > 0) {
            $this->db->where('id', $mom_id);
            $this->db->update('mom_data', ['approved_status' => 1, 'approved_date' => date('Y-m-d H:i:s')]);
        }
        $this->api->mobile_ok(['approved' => true, 'mom_id' => $mom_id]);
    }

    public function reject()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $mom_id = (int) ($in['mom_id'] ?? 0);
        if ($mom_id > 0) {
            $this->db->where('id', $mom_id);
            $this->db->update('mom_data', ['approved_status' => 0]);
        }
        $this->api->mobile_ok(['rejected' => true, 'mom_id' => $mom_id]);
    }

    public function bulk_approve()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $ids = $in['mom_ids'] ?? [];
        $n = 0;
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->db->where('id', (int) $id);
                $this->db->update('mom_data', ['approved_status' => 1, 'approved_date' => date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        $this->api->mobile_ok(['approved_count' => $n]);
    }
}
