<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function active()
    {
        $this->api->require_user();
        $in = $this->api->input();
        $role = $in['role'] ?? null;

        $q = $this->db->query("
            SELECT user_id, name, type_id, photo, status
            FROM user_details
            WHERE status = 'active'
            ORDER BY name ASC
            LIMIT 500
        ");
        $rows = [];
        foreach ($q->result() as $r) {
            if ($role !== null && $role !== '') {
                $roleLower = strtolower((string) $role);
                if (strpos(strtolower($r->name), $roleLower) === false && (string) $r->type_id !== (string) $role) {
                    continue;
                }
            }
            $rows[] = [
                'uid' => (int) $r->user_id,
                'user_id' => (int) $r->user_id,
                'name' => $r->name,
                'type_id' => (int) $r->type_id,
                'active' => 1,
            ];
        }
        $this->api->mobile_ok(['rows' => $rows, 'count' => count($rows)]);
    }
}
