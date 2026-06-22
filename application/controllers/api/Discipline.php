<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Discipline extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET /api/discipline/state?uid=
     */
    public function state()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = $this->api->resolve_request_uid($user, $in);
        $type_id = (int) ($user['type_id'] ?? 0);
        $tdate = $in['date'] ?? $in['tdate'] ?? date('Y-m-d');
        $this->api->mobile_ok($this->api->build_discipline_state($uid, $tdate, $type_id));
    }
}
