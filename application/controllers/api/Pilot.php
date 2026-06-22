<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pilot extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function uids()
    {
        $this->api->require_user();
        $q = $this->db->query("
            SELECT user_id, name, type_id, photo
            FROM user_details
            WHERE status = 'active'
            ORDER BY name ASC
            LIMIT 500
        ");
        $rows = [];
        foreach ($q->result() as $r) {
            $rows[] = [
                'uid' => (int) $r->user_id,
                'user_id' => (int) $r->user_id,
                'name' => $r->name,
                'type_id' => (int) $r->type_id,
            ];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }
}
