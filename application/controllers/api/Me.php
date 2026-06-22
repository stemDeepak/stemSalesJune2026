<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Me extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET/POST api/me — current user profile
     */
    public function index()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $rows = $this->Menu_model->get_userbyid($uid);
        if (empty($rows)) {
            $this->api->fail('User not found', 404);
        }
        $u = $rows[0];
        $this->api->mobile_ok([
            'uid' => (int) $u->user_id,
            'user_id' => (int) $u->user_id,
            'name' => $u->name ?? null,
            'type_id' => (int) $u->type_id,
            'role' => $this->api->role_for_type($u->type_id),
            'photo' => $u->photo ?? null,
            'email' => $u->email ?? null,
            'day' => $this->api->day_state($uid),
        ]);
    }

    /**
     * GET/POST api/me/role
     */
    public function role()
    {
        $user = $this->api->require_user();
        $uyid = (int) $user['type_id'];
        $uid = (int) $user['user_id'];
        $dt = $this->Menu_model->get_utype($uyid);
        $dep = !empty($dt[0]->name) ? $dt[0]->name : 'Unknown';
        $role = $this->api->role_for_type($uyid);
        $this->api->mobile_ok([
            'uid' => $uid,
            'type_id' => $uyid,
            'role' => $role,
            'department' => $dep,
            'role_key' => $this->role_key($uyid),
            'caps' => $this->caps_for_type($uyid),
        ]);
    }

    private function caps_for_type($type_id)
    {
        $t = (int) $type_id;
        $bd = in_array($t, [3, 24], true);
        $cm = in_array($t, [13, 22, 23], true);
        return [
            'create_lead' => $bd || $cm,
            'plan_task' => $bd || $cm,
            'submit_task' => $bd || $cm || $t === 4,
            'barge' => $bd,
            'research' => $bd || $t === 4,
            'join_meeting' => $bd,
            'write_mom' => $bd || $cm,
            'upload_proposal' => $bd,
            'approve_proposal' => $cm,
            'approve_mom' => $cm,
            'approve_planner' => $cm,
            'submit_handover' => $bd,
            'submit_bd_request' => $bd,
            'wallet_view' => $bd,
        ];
    }

    private function role_key($type_id)
    {
        $map = [
            3 => 'sales_person',
            4 => 'pst',
            13 => 'acm',
            15 => 'sales_coordinator',
            24 => 'regional_manager',
        ];
        return $map[$type_id] ?? 'other';
    }
}
