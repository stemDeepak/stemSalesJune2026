<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Meeting extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    /**
     * POST api/meeting/initiate — Menu/rpminitiate
     */
    public function initiate()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();

        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $smid = (int) ($in['smid'] ?? 0);
        $startm = $in['startm'] ?? date('Y-m-d H:i:s');
        $company_name = $in['company_name'] ?? '';
        $lat = $in['lat'] ?? '';
        $lng = $in['lng'] ?? '';
        $bscid = $in['bscid'] ?? '';

        if ($smid <= 0) {
            $this->api->fail('smid (scheduled meeting id) required', 422);
        }

        $meetings = $this->Menu_model->CheckVMMeetinfoByid($smid);
        if (!empty($meetings)) {
            $meeting_link = trim($in['meeting_link'] ?? '');
            if ($meeting_link === '') {
                $this->api->fail('Virtual meeting link required', 422);
            }
            $this->db->where('id', $smid);
            $this->db->update('barginmeeting', ['meeting_link' => $meeting_link]);
        }

        $flink = $this->Menu_model->InitiateMeetingsuploadfile('cphoto', 'uploads/day/');
        $cbmid = $this->Menu_model->initiate_rpm($uid, $startm, $lat, $lng, $smid, $bscid, $company_name, $flink);

        $this->api->mobile_ok([
            'meeting_id' => $cbmid,
            'smid' => $smid,
            'mtype' => 'RP',
        ]);
    }

    /**
     * POST api/meeting/start — Menu/rpmstart
     */
    public function start()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();

        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $smid = (int) ($in['smid'] ?? 0);
        $startm = $in['startm'] ?? date('Y-m-d H:i:s');
        $company_name = $in['company_name'] ?? '';
        $lat = $in['lat'] ?? '';
        $lng = $in['lng'] ?? '';
        $bscid = $in['bscid'] ?? '';

        $flink = $this->Menu_model->StattMeetingsuploadfileNew('cphoto', 'uploads/day/');
        $cbmid = $this->Menu_model->start_rpm($uid, $startm, $company_name, $flink, $lat, $lng, $smid, $bscid);

        $this->api->mobile_ok([
            'meeting_id' => $cbmid,
            'smid' => $smid,
        ]);
    }

    /**
     * POST api/meeting/close — Menu/rpmclose
     */
    public function close()
    {
        $user = $this->api->require_user();
        $uyid = (int) $user['type_id'];
        $in = $this->api->input();

        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $type = $in['type'] ?? $in['mtype'] ?? 'RP';
        $potentional_client = $in['potentional_client'] ?? '';
        $company_as = $in['company_as'] ?? '';

        if (in_array($type, ['RP', 'RPClose', 'Change RP'], true) && in_array($uyid, [3, 13], true)) {
            if ($potentional_client === '') {
                $this->api->fail('potentional_client required for RP meetings', 422);
            }
            if ($company_as === '') {
                $this->api->fail('company_as required for RP meetings', 422);
            }
        }

        $cbmid = $this->Menu_model->close_rpm(
            $uid,
            $in['closem'] ?? date('Y-m-d H:i:s'),
            str_replace(["`", "'"], '', $in['caddress'] ?? ''),
            str_replace(["`", "'"], '', $in['cpname'] ?? ''),
            str_replace(["`", "'"], '', $in['cpdes'] ?? ''),
            $in['cpno'] ?? '',
            $in['cpemail'] ?? '',
            $in['lat'] ?? '',
            $in['lng'] ?? '',
            $type,
            $in['priority'] ?? '',
            (int) ($in['cmid'] ?? 0),
            (int) ($in['bmcid'] ?? 0),
            (int) ($in['bmccid'] ?? 0),
            (int) ($in['bminid'] ?? 0),
            (int) ($in['bmtid'] ?? 0),
            str_replace(["`", "'"], '', $in['letmeetingsremarks'] ?? ''),
            $in['updateCompanyStatus'] ?? $in['updateStatus'] ?? '',
            $company_as,
            str_replace(["`", "'"], '', $in['company_descri'] ?? ''),
            $potentional_client,
            $in['meetingProposalStatus'] ?? '',
            $in['linked_in'] ?? ''
        );

        $this->api->mobile_ok([
            'meeting_id' => $cbmid,
            'type' => $type,
        ]);
    }

    /**
     * POST api/meeting/barge — mobile creates ad-hoc bargain meeting.
     * Uses existing scheduled meeting row when smid provided; otherwise returns guidance.
     */
    public function barge()
    {
        $user = $this->api->require_user();
        $in = $this->api->input();
        $uid = (int) ($in['uid'] ?? $user['user_id']);
        $smid = (int) ($in['smid'] ?? $in['task_id'] ?? $in['tid'] ?? 0);

        if ($smid <= 0) {
            $this->api->mobile_fail('smid_or_task_id_required', 422, [
                'message' => 'Plan a meeting task first, then initiate from the task row.',
            ]);
        }

        return $this->initiate();
    }
}
