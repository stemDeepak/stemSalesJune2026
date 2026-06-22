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

    protected function digest_secret()
    {
        $env = getenv('STEM_DIGEST_TOKEN');
        if ($env) {
            return $env;
        }
        $cfg = (string) $this->CI->config->item('mobile_api_secret');
        if ($cfg !== '' && $cfg !== 'stem-mobile-api-change-me-in-production') {
            return $cfg;
        }
        return '4eBaiAT7r4zu6OK3b8evjLNia1D7RGgb0qRTuLJfUSo';
    }

    /**
     * Per-user daily digest token — matches staging BearerAuth / Mobile_write_api.
     */
    public function issue_token($user_row)
    {
        $uid = (int) $user_row->user_id;
        $secret = $this->digest_secret();
        return hash('sha1', $secret . '|' . $uid . '|' . date('Y-m-d'));
    }

    protected function uid_from_digest_token($token)
    {
        if (!$token || !preg_match('/^[a-f0-9]{40}$/i', $token)) {
            return false;
        }
        $secret = $this->digest_secret();
        $days = [
            date('Y-m-d'),
            date('Y-m-d', strtotime('-1 day')),
            date('Y-m-d', strtotime('+1 day')),
        ];
        $candidates = [];
        foreach (['uid', 'bd_uid', 'cm_uid', 'user_id'] as $k) {
            if (!empty($_GET[$k]) && (int) $_GET[$k] > 0) {
                $candidates[(int) $_GET[$k]] = 1;
            }
            if (!empty($_POST[$k]) && (int) $_POST[$k] > 0) {
                $candidates[(int) $_POST[$k]] = 1;
            }
        }
        foreach (array_keys($candidates) as $uid) {
            foreach ($days as $d) {
                if (hash_equals(hash('sha1', $secret . '|' . $uid . '|' . $d), $token)) {
                    return (int) $uid;
                }
            }
        }
        $rows = $this->CI->db->query(
            "SELECT user_id FROM user_details WHERE status = 'active' LIMIT 2000"
        )->result();
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            if ($uid <= 0) {
                continue;
            }
            foreach ($days as $d) {
                if (hash_equals(hash('sha1', $secret . '|' . $uid . '|' . $d), $token)) {
                    return $uid;
                }
            }
        }
        return false;
    }

    public function user_from_token($token)
    {
        if (!$token) {
            return false;
        }

        $uid = $this->uid_from_digest_token($token);
        if ($uid) {
            $row = $this->CI->db->select('user_id,type_id,name')
                ->from('user_details')->where('user_id', $uid)->limit(1)->get()->row();
            return [
                'uid' => $uid,
                'type_id' => $row ? (int) $row->type_id : 0,
                'name' => $row ? (string) $row->name : '',
            ];
        }

        if (strpos($token, '.') === false) {
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

    /**
     * Field roles that run the BD day-discipline gate chain (not managers).
     */
    public function is_field_gate_role($type_id)
    {
        return in_array((int) $type_id, [3, 4, 24], true);
    }

    /**
     * Force token uid for field users so two logins never share the same pending lists.
     */
    public function resolve_request_uid($user, $input = null)
    {
        $input = is_array($input) ? $input : $this->input();
        $auth_uid = (int) ($user['user_id'] ?? 0);
        $type_id = (int) ($user['type_id'] ?? 0);
        $req_uid = (int) ($input['uid'] ?? $input['bd_uid'] ?? $input['user_id'] ?? 0);
        if ($this->is_field_gate_role($type_id)) {
            return $auth_uid > 0 ? $auth_uid : $req_uid;
        }
        return $req_uid > 0 ? $req_uid : $auth_uid;
    }

    /**
     * Pending autotasks from prior days + today (matches production gate).
     */
    public function pending_autotask_rows($uid)
    {
        $uid = (int) $uid;
        $by_id = [];
        foreach (['get_PendingAutoTask', 'get_PendingAutoTaskForToday'] as $method) {
            if (!method_exists($this->CI->Menu_model, $method)) {
                continue;
            }
            $rows = $this->CI->Menu_model->$method($uid);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $r) {
                $id = is_object($r) ? (int) ($r->id ?? 0) : (int) ($r['id'] ?? 0);
                if ($id > 0) {
                    $by_id[$id] = $r;
                }
            }
        }
        return array_values($by_id);
    }

    protected function pbni_state_for($uid, $tdate = null)
    {
        $tdate = $tdate ?: date('Y-m-d');
        $rows = method_exists($this->CI->Menu_model, 'get_OLDPendingTask')
            ? $this->CI->Menu_model->get_OLDPendingTask($uid)
            : [];
        $pbni_count = is_array($rows) ? count($rows) : 0;
        $pbni_alert_approved = true;
        $pbni_alert_pending = false;
        if ($pbni_count > 0) {
            $pbni_alert_approved = false;
            $pbni_alert_pending = true;
            try {
                $q = $this->CI->db->query(
                    "SELECT approvel_status FROM pbni_alert
                     WHERE user_id = ? AND DATE(alert_date) = ? ORDER BY id DESC LIMIT 1",
                    [(int) $uid, $tdate]
                );
                $row = $q->row();
                if ($row && ($row->approvel_status === 'Approved' || (int) $row->approvel_status === 1)) {
                    $pbni_alert_approved = true;
                    $pbni_alert_pending = false;
                }
            } catch (Exception $e) {
                log_message('error', 'pbni_alert lookup: ' . $e->getMessage());
            }
        }
        return [
            'pbni_count' => $pbni_count,
            'pbni_alert_approved' => $pbni_alert_approved,
            'pbni_alert_pending' => $pbni_alert_pending,
        ];
    }

    public function build_discipline_state($uid, $tdate = null, $type_id = null)
    {
        $uid = (int) $uid;
        $tdate = $tdate ?: date('Y-m-d');
        $day = $this->day_state($uid, $tdate);
        $day_started = in_array($day['state'], ['started', 'closed'], true);
        $day_closed = $day['state'] === 'closed';

        $pending_autotask_count = count($this->pending_autotask_rows($uid));
        $pbni = $this->pbni_state_for($uid, $tdate);

        $new_lead_count = 0;
        if (method_exists($this->CI->Menu_model, 'GetReUpdateNewLeadComapny')) {
            $nl = $this->CI->Menu_model->GetReUpdateNewLeadComapny($uid);
            $new_lead_count = is_array($nl) ? count($nl) : 0;
        }

        $utype = $type_id !== null ? (int) $type_id : 0;
        if ($utype <= 0) {
            $urows = $this->CI->Menu_model->get_userbyid($uid);
            if (!empty($urows)) {
                $utype = (int) $urows[0]->type_id;
            }
        }

        $today_planner_locked = false;
        if (in_array($utype, [3, 4, 24], true)) {
            $q = $this->CI->db->query(
                "SELECT id FROM planner_approved WHERE user_id = ? AND request_date = ? AND approved_status = 1 LIMIT 1",
                [$uid, $tdate]
            );
            $today_planner_locked = empty($q->row());
        }

        $next_required_screen = 'PlannerV2';
        $next_required_action = null;
        $block_reason = null;

        if ($this->is_field_gate_role($utype)) {
            if (!$day_started) {
                $next_required_screen = 'DayCeremonyV2';
                $next_required_action = 'start_day';
                $block_reason = 'Day not started';
            } elseif ($pbni['pbni_count'] > 0 && !$pbni['pbni_alert_approved']) {
                $next_required_screen = 'DayManagement';
                $next_required_action = 'clear_pbni';
                $block_reason = $pbni['pbni_count'] . ' yesterday pending tasks (PBNI)';
            } elseif ($pending_autotask_count > 0) {
                $next_required_screen = 'DayManagement';
                $next_required_action = 'clear_autotask';
                $block_reason = $pending_autotask_count . ' pending auto tasks';
            } elseif ($today_planner_locked) {
                $next_required_screen = 'SameDayRequestScreen';
                $next_required_action = 'request_same_day';
                $block_reason = 'Planner not approved for today';
            }
        }

        return [
            'ok' => true,
            'uid' => $uid,
            'today' => $tdate,
            'day_started' => $day_started,
            'day_start_time' => $day['ustart'] ?? null,
            'day_closed' => $day_closed,
            'pending_autotask_count' => $pending_autotask_count,
            'pbni_count' => $pbni['pbni_count'],
            'pbni_alert_pending' => $pbni['pbni_alert_pending'],
            'pbni_alert_approved' => $pbni['pbni_alert_approved'],
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
