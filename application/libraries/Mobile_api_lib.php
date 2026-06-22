<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared helpers for STEM CRM mobile REST endpoints.
 * Bridges JSON/mobile clients to existing Menu_model session flows.
 */
class Mobile_api_lib {

    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('mobile_api');
        $this->CI->load->database();
        $this->CI->load->model('Menu_model');
        $this->send_cors_headers();
    }

    public function send_cors_headers()
    {
        if (headers_sent()) {
            return;
        }
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    public function json($payload, $status = 200)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code((int) $status);
        }
        echo json_encode($payload);
        exit;
    }

    public function ok($data = [], $message = 'OK')
    {
        $this->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /** Mobile app contract: flat { ok: true, ...fields } */
    public function mobile_ok($fields = [])
    {
        $this->json(array_merge(['ok' => true], $fields));
    }

    public function mobile_fail($error, $status = 400, $extra = [])
    {
        $this->json(array_merge(['ok' => false, 'error' => $error], $extra), $status);
    }

    public function fail($message, $status = 400, $extra = [])
    {
        $this->json(array_merge([
            'status' => false,
            'message' => $message,
            'ok' => false,
            'error' => $message,
        ], $extra), $status);
    }

    public function role_for_type($type_id)
    {
        $map = [
            1 => 'ADMIN', 2 => 'ADMIN', 3 => 'BD', 4 => 'PST', 10 => 'FOUNDER',
            13 => 'CM', 15 => 'SC', 17 => 'EA', 19 => 'ASH', 20 => 'ASH', 21 => 'ASH',
            22 => 'RM', 23 => 'RM', 24 => 'ACM', 27 => 'AO',
        ];
        $t = (int) $type_id;
        return isset($map[$t]) ? $map[$t] : 'OTHER';
    }

    public function mobile_login_payload($row, $token)
    {
        return [
            'ok' => true,
            'uid' => (int) $row->user_id,
            'user_details_id' => (int) $row->id,
            'name' => (string) $row->name,
            'username' => isset($row->username) ? (string) $row->username : '',
            'type_id' => (int) $row->type_id,
            'role' => $this->role_for_type($row->type_id),
            'token' => $token,
            'photo' => $row->photo ?? '',
            'zone_id' => isset($row->zone_id) ? (int) $row->zone_id : 0,
        ];
    }

    public function input()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_merge($_POST, $json);
        }
        return $_POST;
    }

    public function issue_token($user_row)
    {
        $ttl = (int) $this->CI->config->item('mobile_api_token_ttl');
        $payload = [
            'uid' => (int) $user_row->user_id,
            'type_id' => (int) $user_row->type_id,
            'name' => (string) $user_row->name,
            'exp' => time() + $ttl,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $secret = (string) $this->CI->config->item('mobile_api_secret');
        $sig = hash_hmac('sha256', $encoded, $secret);
        return $encoded . '.' . $sig;
    }

    public function user_from_token($token)
    {
        if (!$token || strpos($token, '.') === false) {
            return false;
        }
        list($encoded, $sig) = explode('.', $token, 2);
        $secret = (string) $this->CI->config->item('mobile_api_secret');
        $expected = hash_hmac('sha256', $encoded, $secret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $json = base64_decode(strtr($encoded, '-_', '+/'));
        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp'])) {
            return false;
        }
        if ((int) $payload['exp'] < time()) {
            return false;
        }
        return $payload;
    }

    public function bearer_token()
    {
        $auth = '';
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $auth = $v;
                    break;
                }
            }
        }
        if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Resolve authenticated user; sets CI session for model compatibility.
     */
    public function require_user()
    {
        $token = $this->bearer_token();
        $payload = $token ? $this->user_from_token($token) : false;

        if (!$payload) {
            $this->CI->load->library('session');
            $sess = $this->CI->session->userdata('user');
            if (!empty($sess['user_id'])) {
                return $sess;
            }
            $this->fail('Unauthorized', 401);
        }

        $user = [
            'id' => (string) ($payload['uid'] ?? ''),
            'user_id' => (string) $payload['uid'],
            'type_id' => (string) ($payload['type_id'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
        ];

        $this->CI->load->library('session');
        $this->CI->session->set_userdata('user', $user);
        return $user;
    }

    public function format_task_row($row)
    {
        $mobile = $this->format_task_row_mobile($row);
        return array_merge($mobile, [
            'assignedto_id' => isset($row->assignedto_id) ? (int) $row->assignedto_id : null,
            'action_name' => $row->aname ?? ($row->task_name ?? null),
            'appointmentdatetime' => $row->appointmentdatetime ?? null,
            'nextCFID' => (int) ($row->nextCFID ?? 0),
            'pending' => ((int) ($row->nextCFID ?? 0)) === 0,
            'completed' => ((int) ($row->nextCFID ?? 0)) !== 0,
            'company_id' => $this->task_cmpid($row),
            'company_name' => $row->compname ?? ($row->company_name ?? null),
            'status_name' => $row->csname ?? ($row->current_status ?? ($row->name ?? null)),
            'status_color' => $row->color ?? null,
            'remarks' => $row->remarks ?? null,
            'plan' => $row->plan ?? null,
            'mtype' => $row->mtype ?? null,
        ]);
    }

    protected function task_cmpid($row)
    {
        if (isset($row->cmpid_id) && $row->cmpid_id !== '' && $row->cmpid_id !== null) {
            return (int) $row->cmpid_id;
        }
        if (isset($row->cmpid) && $row->cmpid !== '' && $row->cmpid !== null) {
            return (int) $row->cmpid;
        }
        if (isset($row->cid) && $row->cid !== '' && $row->cid !== null) {
            return (int) $row->cid;
        }
        return null;
    }

    /** Fields expected by M047 dashboard / task execution screens */
    public function format_task_row_mobile($row)
    {
        $appt = $row->appointmentdatetime ?? '';
        $time = '';
        if ($appt && strlen($appt) >= 16) {
            $time = substr($appt, 11, 5);
        }
        $cmpid = $this->task_cmpid($row);
        return [
            'id' => (int) $row->id,
            'tid' => (int) $row->id,
            'user_id' => (int) ($row->user_id ?? $row->task_user_id ?? 0),
            'actiontype_id' => (int) ($row->actiontype_id ?? 0),
            'cname' => $row->compname ?? ($row->company_name ?? ('Task #' . $row->id)),
            'ctname' => $row->aname ?? ($row->action_name ?? ($row->task_name ?? '')),
            'cstatus' => isset($row->cstatus) ? (int) $row->cstatus : 1,
            'appointmenttime' => $time,
            'appointmentdatetime' => $appt,
            'purpose_label' => $row->aname ?? ($row->task_name ?? ''),
            'cmpid' => $cmpid,
            'cid_id' => isset($row->cid_id) ? (int) $row->cid_id : null,
        ];
    }

    /**
     * Single task with joins (M047 task detail / execution).
     */
    public function task_row_by_id($tid)
    {
        $tid = (int) $tid;
        if ($tid <= 0) {
            return null;
        }
        $q = $this->CI->db->query(
            "SELECT
                tblcallevents.*,
                status.name,
                action.name AS aname,
                status.color,
                status.clr,
                init_call.cstatus AS cstatus,
                status_cstatus.name AS csname,
                init_call.cmpid_id,
                company_master.compname,
                company_master.id AS cid
            FROM tblcallevents
            LEFT JOIN action ON action.id = tblcallevents.actiontype_id
            LEFT JOIN init_call ON init_call.id = tblcallevents.cid_id
            LEFT JOIN status ON status.id = init_call.cstatus
            LEFT JOIN status AS status_cstatus ON status_cstatus.id = init_call.cstatus
            LEFT JOIN company_master ON company_master.id = init_call.cmpid_id
            WHERE tblcallevents.id = ?
            LIMIT 1",
            [$tid]
        );
        return $q->row();
    }

    public function bucket_tasks_into_tabs(array $rows)
    {
        $tabs = [
            'meetings' => [],
            'calls' => [],
            'email' => [],
            'writing' => [],
            'other' => [],
        ];
        foreach ($rows as $row) {
            $item = is_array($row) ? $row : $this->format_task_row($row);
            $at = (int) ($item['actiontype_id'] ?? 0);
            if (in_array($at, [3, 4, 17, 22, 23, 24, 25, 26], true)) {
                $tabs['meetings'][] = $item;
            } elseif (in_array($at, [1, 5, 13], true)) {
                $tabs['calls'][] = $item;
            } elseif ($at === 2) {
                $tabs['email'][] = $item;
            } elseif (in_array($at, [6, 7, 10, 18], true)) {
                $tabs['writing'][] = $item;
            } else {
                $tabs['other'][] = $item;
            }
        }
        return $tabs;
    }

    public function build_discipline_state($uid, $tdate = null)
    {
        $uid = (int) $uid;
        $tdate = $tdate ?: date('Y-m-d');
        $day = $this->day_state($uid, $tdate);
        $day_started = in_array($day['state'], ['started', 'closed'], true);
        $day_closed = $day['state'] === 'closed';

        $pending_auto = $this->CI->Menu_model->get_PendingTaskForToday($uid);
        $pending_autotask_count = is_array($pending_auto) ? count($pending_auto) : 0;

        $new_lead_count = 0;
        if (method_exists($this->CI->Menu_model, 'GetReUpdateNewLeadComapny')) {
            $nl = $this->CI->Menu_model->GetReUpdateNewLeadComapny($uid);
            $new_lead_count = is_array($nl) ? count($nl) : 0;
        }

        $utype = 0;
        $urows = $this->CI->Menu_model->get_userbyid($uid);
        if (!empty($urows)) {
            $utype = (int) $urows[0]->type_id;
        }

        $today_planner_locked = false;
        if (in_array($utype, [3, 4, 13, 24], true)) {
            $q = $this->CI->db->query(
                "SELECT id FROM planner_approved WHERE user_id = ? AND request_date = ? AND approved_status = 1 LIMIT 1",
                [$uid, $tdate]
            );
            $today_planner_locked = empty($q->row());
        }

        $next_required_screen = 'planner_open';
        $next_required_action = null;
        $block_reason = null;

        if (!$day_started) {
            $next_required_screen = 'DayCeremonyV2';
            $next_required_action = 'start_day';
            $block_reason = 'Day not started';
        } elseif ($pending_autotask_count > 0) {
            $next_required_screen = 'DayManagement';
            $next_required_action = 'complete_autotasks';
            $block_reason = $pending_autotask_count . ' pending auto tasks';
        } elseif ($today_planner_locked && in_array($utype, [3, 4, 13, 24], true)) {
            $next_required_screen = 'Dashboard';
            $block_reason = 'Planner not approved for today';
        }

        return [
            'ok' => true,
            'uid' => $uid,
            'today' => $tdate,
            'day_started' => $day_started,
            'day_start_time' => $day['ustart'] ?? null,
            'day_closed' => $day_closed,
            'pending_autotask_count' => $pending_autotask_count,
            'pbni_count' => 0,
            'pbni_alert_pending' => false,
            'pbni_alert_approved' => true,
            'rp_mom_pending_count' => 0,
            'meeting_expense_pending_count' => 0,
            'research_not_updated_count' => 0,
            'new_lead_reupdate_count' => $new_lead_count,
            'today_planner_locked' => $today_planner_locked,
            'cutoff_time' => null,
            'cutoff_passed' => false,
            'same_day_request' => ['exists' => false],
            'yesterday_request' => ['exists' => false],
            'day_close_override' => ['exists' => false],
            'line_manager' => null,
            'next_required_action' => $next_required_action,
            'next_required_screen' => $next_required_screen,
            'block_reason' => $block_reason,
        ];
    }

    public function day_state($uid, $tdate = null)
    {
        $tdate = $tdate ?: date('Y-m-d');
        $started = $this->CI->Menu_model->get_daystarted($uid, $tdate);
        $detail = $this->CI->Menu_model->get_daydetail($uid, $tdate);
        $state = 'not_started';
        if (!empty($started)) {
            $state = 'started';
        }
        if (!empty($detail) && !empty($detail[0]->uclose)) {
            $state = 'closed';
        }
        return [
            'date' => $tdate,
            'state' => $state,
            'ustart' => !empty($detail[0]->ustart) ? $detail[0]->ustart : null,
            'uclose' => !empty($detail[0]->uclose) ? $detail[0]->uclose : null,
        ];
    }
}
