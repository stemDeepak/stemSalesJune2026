<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Day_ceremony extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * GET/POST api/day_ceremony/status
     */
    public function status()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $in = $this->api->input();
        $tdate = $in['date'] ?? $in['tdate'] ?? date('Y-m-d');
        $this->api->mobile_ok(array_merge(
            $this->api->day_state($uid, $tdate),
            $this->api->build_discipline_state($uid, $tdate)
        ));
    }

    /**
     * POST api/day_ceremony/start
     * Mirrors Menu/daysc do=0
     */
    public function start()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $uyid = (int) $user['type_id'];
        $in = $this->api->input();

        $lat = $in['lat'] ?? $in['latitude'] ?? '';
        $lng = $in['lng'] ?? $in['longitude'] ?? '';
        $wffo = (int) ($in['wffo'] ?? 0);

        $state = $this->api->day_state($uid);
        if ($state['state'] === 'started') {
            $this->api->mobile_fail('Day already started', 409, ['day' => $state]);
        }
        if ($state['state'] === 'closed') {
            $this->api->mobile_fail('Day already closed for today', 409, ['day' => $state]);
        }

        $pending = $this->Menu_model->get_PendingTaskForToday($uid);
        $pending_count = is_array($pending) ? count($pending) : 0;

        $flink = '';
        if (!empty($_FILES['filname']['name'])) {
            $flink = $this->Menu_model->uploadfile($_FILES['filname']['name'], 'uploads/day/');
        }

        $this->Menu_model->submit_day($wffo, $flink, $uid, $lat, $lng, 0);

        $warnings = [];
        if ($pending_count > 0) {
            $warnings[] = $pending_count . ' pending auto task(s) — complete them in Day Management';
        }
        if (in_array($uyid, [3, 4, 13], true)) {
            $warnings[] = 'Please set today\'s planner (TaskPlanner2)';
        }

        $this->api->mobile_ok(array_merge([
            'day' => $this->api->day_state($uid),
            'warnings' => $warnings,
            'pending_count' => $pending_count,
        ], $this->api->build_discipline_state($uid)));
    }

    /**
     * POST api/day_ceremony/close
     * Mirrors Menu/daysc do=1
     */
    public function close()
    {
        $user = $this->api->require_user();
        $uid = (int) $user['user_id'];
        $uyid = (int) $user['type_id'];
        $in = $this->api->input();

        $lat = $in['lat'] ?? $in['latitude'] ?? '';
        $lng = $in['lng'] ?? $in['longitude'] ?? '';
        $wffo = (int) ($in['wffo'] ?? 0);

        $state = $this->api->day_state($uid);
        if ($state['state'] === 'not_started') {
            $this->api->mobile_fail('Day has not been started', 422, ['day' => $state]);
        }
        if ($state['state'] === 'closed') {
            $this->api->mobile_fail('Day already closed', 409, ['day' => $state]);
        }

        if (in_array($uyid, [3, 4, 13, 24], true)) {
            $newleads = $this->Menu_model->GetReUpdateNewLeadComapny($uid);
            if (count($newleads) > 0) {
                $this->api->mobile_fail(count($newleads) . ' new leads pending re-update', 422, [
                    'pending_leads' => count($newleads),
                ]);
            }
            $pending = $this->Menu_model->get_PendingTaskForToday($uid);
            if (count($pending) > 0) {
                $this->api->mobile_fail(count($pending) . ' pending auto tasks', 422, [
                    'pending_tasks' => count($pending),
                ]);
            }
        }

        $flink = '';
        if (!empty($_FILES['filname']['name'])) {
            $flink = $this->Menu_model->uploadfile($_FILES['filname']['name'], 'uploads/day/');
        }

        $this->Menu_model->submit_day($wffo, $flink, $uid, $lat, $lng, 1);

        $this->api->mobile_ok(array_merge([
            'day' => $this->api->day_state($uid),
        ], $this->api->build_discipline_state($uid)));
    }

    /**
     * POST api/day_ceremony/geo_context
     */
    public function geo_context()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $this->api->mobile_ok([
            'user_id' => (int) $user['user_id'],
            'lat' => $in['lat'] ?? null,
            'lng' => $in['lng'] ?? null,
            'base_lat' => $in['lat'] ?? null,
            'base_lng' => $in['lng'] ?? null,
            'allowed_radius_m' => 500,
            'accepted' => true,
        ]);
    }
}
