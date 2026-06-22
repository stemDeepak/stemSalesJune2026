<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// ============================================================
// STEM CRM MCP Server — OAuth 2.1 + RFC 9728
// VERSION: 3.0 — June 2026
// Upload to: application/controllers/Mcp.php
// ============================================================

class Mcp extends CI_Controller {

    private $base_url  = 'https://stemapp.in';
    private $token_ttl = 86400;
    private $cache_dir;

    public function __construct() {
        parent::__construct();
        $this->load->model('Mcp_model');
        $this->load->model('Menu_model'); // used by Mcp_model::get_coordinator_hierarchy
        $this->load->library('session');
        $this->cache_dir = APPPATH . 'cache/mcp_tokens/';
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200); exit;
        }
    }

    // ── Helpers ──────────────────────────────────────────────

    private function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function gen_token($len = 32) {
        return bin2hex(random_bytes($len));
    }

    private function save_tok($token, $data) {
        $data['expires_at'] = time() + $this->token_ttl;
        file_put_contents($this->cache_dir . md5($token) . '.json', json_encode($data));
    }

    private function get_tok($token) {
        $f = $this->cache_dir . md5($token) . '.json';
        if (!file_exists($f)) return null;
        $d = json_decode(file_get_contents($f), true);
        if (!$d || $d['expires_at'] < time()) { @unlink($f); return null; }
        return $d;
    }

    private function del_tok($token) {
        @unlink($this->cache_dir . md5($token) . '.json');
    }

    // ── DIAGNOSTIC (temporary): confirm the Authorization header reaches PHP ──
    // Hit: curl -s -H "Authorization: Bearer testabc123" https://stemapp.in/mcp/whoami
    // Remove this method once the connector works.
    public function whoami() {
        $h = function_exists('getallheaders') ? getallheaders() : [];
        $found_in = null; $val = '';
        foreach (['HTTP_AUTHORIZATION','REDIRECT_HTTP_AUTHORIZATION','REDIRECT_REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            if (!empty($_SERVER[$k])) { $found_in = $k; $val = $_SERVER[$k]; break; }
        }
        if (!$found_in) {
            foreach ($h as $hk => $hv) { if (strcasecmp($hk,'Authorization')===0) { $found_in='getallheaders'; $val=$hv; break; } }
        }
        $this->json([
            'authorization_seen' => $found_in ? true : false,
            'found_in'           => $found_in ?: 'NOWHERE (header is being stripped)',
            'value_prefix'       => $val ? substr($val, 0, 12) . '...' : '',
            'cache_dir'          => $this->cache_dir,
            'cache_writable'     => is_dir($this->cache_dir) && is_writable($this->cache_dir),
        ]);
    }

    private function bearer_user() {
        $auth = '';
        // Apache/cPanel (CGI) exposes the header in different places depending on
        // whether an internal rewrite happened. Check all of them.
        $candidates = ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION'];
        foreach ($candidates as $k) {
            if (!empty($_SERVER[$k])) { $auth = $_SERVER[$k]; break; }
        }
        if (!$auth && function_exists('getallheaders')) {
            foreach (getallheaders() as $hk => $hv) {
                if (strcasecmp($hk, 'Authorization') === 0) { $auth = $hv; break; }
            }
        }
        if (!$auth && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $hk => $hv) {
                if (strcasecmp($hk, 'Authorization') === 0) { $auth = $hv; break; }
            }
        }
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return null;
        return $this->get_tok(trim($m[1]));
    }

    // ── RFC 9728 ─────────────────────────────────────────────

    public function oauth_protected_resource() {
        $this->json([
            'resource'                 => $this->base_url . '/mcp',
            'authorization_servers'    => [$this->base_url],
            'bearer_methods_supported' => ['header'],
            'scopes_supported'         => ['mcp'],
        ]);
    }

    // ── RFC 8414 ─────────────────────────────────────────────

    public function oauth_authorization_server() {
        $this->json([
            'issuer'                                => $this->base_url,
            'authorization_endpoint'                => $this->base_url . '/mcp/oauth/authorize',
            'token_endpoint'                        => $this->base_url . '/mcp/oauth/token',
            'registration_endpoint'                 => $this->base_url . '/mcp/oauth/register',
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported'                      => ['mcp'],
        ]);
    }

    // ── MCP Index — returns 401 if no token, tools if authenticated ──

    public function index() {
        $user = $this->bearer_user();

        if (!$user) {
            // No token — tell Claude where to authenticate (RFC 9728 Section 5.1)
            http_response_code(401);
            header('Content-Type: application/json');
            header('WWW-Authenticate: Bearer realm="stemapp", resource_metadata="' . $this->base_url . '/.well-known/oauth-protected-resource"');
            echo json_encode(['error' => 'unauthorized', 'message' => 'Authentication required']);
            exit;
        }

        // ---- MCP speaks JSON-RPC 2.0 over Streamable HTTP ----
        $raw    = file_get_contents('php://input');
        $req    = json_decode($raw, true);
        $method = (is_array($req) && isset($req['method'])) ? $req['method'] : null;
        $id     = (is_array($req) && array_key_exists('id', $req)) ? $req['id'] : null;

        // A plain GET with no JSON body: simple health/info (optional).
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$method) {
            $this->json(['status' => 'ok', 'name' => 'stemapp', 'transport' => 'streamable-http']);
        }

        switch ($method) {
            case 'initialize':
                $pv = isset($req['params']['protocolVersion']) ? $req['params']['protocolVersion'] : '2025-06-18';
                $this->rpc_result($id, [
                    'protocolVersion' => $pv,
                    'capabilities'    => ['tools' => new stdClass()],
                    'serverInfo'      => ['name' => 'stemapp', 'version' => '1.0.0'],
                ]);
                break;

            case 'notifications/initialized':
            case 'initialized':
                // JSON-RPC notification: no response body.
                http_response_code(202);
                exit;

            case 'ping':
                $this->rpc_result($id, new stdClass());
                break;

            case 'tools/list':
                $this->rpc_result($id, ['tools' => $this->normalize_tools($this->tools_list())]);
                break;

            case 'tools/call':
                $p    = isset($req['params']) ? $req['params'] : [];
                $name = isset($p['name']) ? $p['name'] : '';
                $args = isset($p['arguments']) ? $p['arguments'] : [];
                $out  = $this->run_tool($name, $args);
                if (is_array($out) && isset($out['__error'])) {
                    $this->rpc_error($id, -32602, $out['__error']);
                }
                $this->rpc_result($id, [
                    'content' => [
                        ['type' => 'text', 'text' => json_encode($out, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);
                break;

            default:
                // No/unknown method. If it was a GET we already handled health above.
                $this->rpc_error($id, -32601, 'Method not found: ' . $method);
        }
    }

    // ── JSON-RPC envelope helpers ────────────────────────────
    private function rpc_result($id, $result) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function rpc_error($id, $code, $message) {
        http_response_code(200); // JSON-RPC transports errors in the body, HTTP stays 200
        header('Content-Type: application/json');
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Schema normaliser ────────────────────────────────────
    // Anthropic's FrontendRemoteMcpToolDefinition requires inputSchema.properties
    // to be a JSON OBJECT ({}). PHP encodes an empty PHP array as [] (a JSON array),
    // which fails validation ("input_schema.properties: Input should be a valid
    // dictionary"). This pass guarantees every tool emits a valid object, so no
    // no-parameter tool can ever break tools/list again — present or future.
    private function normalize_tools($tools) {
        if (!is_array($tools)) {
            return $tools;
        }
        foreach ($tools as &$t) {
            if (!is_array($t)) {
                continue;
            }
            // Support input_schema as an alias, but always emit inputSchema.
            if (!isset($t['inputSchema']) && isset($t['input_schema'])) {
                $t['inputSchema'] = $t['input_schema'];
                unset($t['input_schema']);
            }
            if (!isset($t['inputSchema']) || !is_array($t['inputSchema'])) {
                $t['inputSchema'] = ['type' => 'object', 'properties' => new stdClass()];
                continue;
            }
            if (empty($t['inputSchema']['type'])) {
                $t['inputSchema']['type'] = 'object';
            }
            // properties MUST be an object. Empty / non-array -> {} (stdClass -> {}).
            if (!isset($t['inputSchema']['properties'])
                || !is_array($t['inputSchema']['properties'])
                || count($t['inputSchema']['properties']) === 0) {
                $t['inputSchema']['properties'] = new stdClass();
            }
            // required MUST stay a JSON array; an empty one would mis-encode, so drop it.
            if (isset($t['inputSchema']['required'])
                && (!is_array($t['inputSchema']['required'])
                    || count($t['inputSchema']['required']) === 0)) {
                unset($t['inputSchema']['required']);
            }
        }
        unset($t);
        return $tools;
    }

    // ── Tool dispatch (shared by JSON-RPC tools/call) ────────
    private function run_tool($tool, $args) {
        switch ($tool) {
            case 'get_active_users':           return $this->Mcp_model->get_active_users(isset($args['limit']) ? (int)$args['limit'] : 50, isset($args['type_id']) ? (int)$args['type_id'] : null);
            case 'analyze_users':              return $this->Mcp_model->analyze_users($args);
            case 'get_user_by_id':             return isset($args['user_id']) ? $this->Mcp_model->get_user_by_id((int)$args['user_id']) : ['__error' => 'user_id required'];
            case 'get_users_by_role':          return $this->Mcp_model->get_users_by_role($args);
            case 'get_zone_analysis':          return $this->Mcp_model->get_zone_analysis(isset($args['group_by']) ? $args['group_by'] : 'zone_id');
            case 'get_recently_created_users': return $this->Mcp_model->get_recently_created_users(isset($args['days']) ? (int)$args['days'] : 30, isset($args['limit']) ? (int)$args['limit'] : 50);
            case 'search_users':               return isset($args['query']) ? $this->Mcp_model->search_users($args['query'], isset($args['limit']) ? (int)$args['limit'] : 20) : ['__error' => 'query required'];
            case 'get_coordinator_hierarchy':  return $this->Mcp_model->get_coordinator_hierarchy(isset($args['user_id']) ? (int)$args['user_id'] : 0);

            // ---- Deep-analysis tools (parity with Menu/Report/Management models) ----
            case 'pipeline_funnel':            return $this->Mcp_model->pipeline_funnel($args);
            case 'conversion_funnel':          return $this->Mcp_model->conversion_funnel($args);
            case 'bd_performance':             return $this->Mcp_model->bd_performance($args);
            case 'activity_analysis':          return $this->Mcp_model->activity_analysis($args);
            case 'company_activity_breakdown': return $this->Mcp_model->company_activity_breakdown($args);
            case 'meeting_outcomes':           return $this->Mcp_model->meeting_outcomes($args);
            case 'stuck_leads':                return $this->Mcp_model->stuck_leads($args);
            case 'proposal_analysis':          return $this->Mcp_model->proposal_analysis($args);
            case 'day_discipline':             return $this->Mcp_model->day_discipline($args);
            case 'cluster_performance':        return $this->Mcp_model->cluster_performance($args);
            case 'partner_mix':                return $this->Mcp_model->partner_mix($args);
            case 'bargin_meeting_analysis':    return $this->Mcp_model->bargin_meeting_analysis($args);
            case 'executive_summary':          return $this->Mcp_model->executive_summary($args);
            case 'rp_meeting_split':           return $this->Mcp_model->rp_meeting_split($args);
            case 'future_tasks':               return $this->Mcp_model->future_tasks($args);
            case 'team_conversion':            return $this->Mcp_model->team_conversion($args);
            case 'proposal_closure_overview':  return $this->Mcp_model->proposal_closure_overview($args);
            case 'quarter_target':             return $this->Mcp_model->quarter_target($args);
            case 'team_quarter_targets':       return $this->Mcp_model->team_quarter_targets($args);
            case 'expense_summary':               return $this->Mcp_model->expense_summary($args);
            case 'travel_advance_summary':        return $this->Mcp_model->travel_advance_summary($args);
            case 'handover_summary':              return $this->Mcp_model->handover_summary($args);
            case 'bd_reviews':                    return $this->Mcp_model->bd_reviews($args);
            case 'bd_requests':                   return $this->Mcp_model->bd_requests($args);
            case 'mom_status':                    return $this->Mcp_model->mom_status($args);
            case 'companies_missing_contact':     return $this->Mcp_model->companies_missing_contact($args);
            case 'focus_funnels':                 return $this->Mcp_model->focus_funnels($args);

            case 'meeting_metrics':            return $this->Mcp_model->meeting_metrics($args);
            case 'proposal_task_metrics':      return $this->Mcp_model->proposal_task_metrics($args);
            case 'star_ratings':                return $this->Mcp_model->star_ratings($args);
            case 'funnel_transfers':            return $this->Mcp_model->funnel_transfers($args);
            case 'planner_summary':             return $this->Mcp_model->planner_summary($args);
            case 'same_status_leads':           return $this->Mcp_model->same_status_leads($args);
            case 'rp_lead_calling':               return $this->Mcp_model->rp_lead_calling($args);
            case 'special_remarks_leads':         return $this->Mcp_model->special_remarks_leads($args);
            case 'calling_outcome_conversion':    return $this->Mcp_model->calling_outcome_conversion($args);
            case 'status_change_requests':        return $this->Mcp_model->status_change_requests($args);
            case 'user_time_spent':               return $this->Mcp_model->user_time_spent($args);
            case 'leave_summary':                 return $this->Mcp_model->leave_summary($args);
            case 'vendor_requests':               return $this->Mcp_model->vendor_requests($args);
            case 'attention_log':                 return $this->Mcp_model->attention_log($args);
            case 'artwork_status':                return $this->Mcp_model->artwork_status($args);
            case 'schools':                       return $this->Mcp_model->schools($args);
            case 'task_check_status':             return $this->Mcp_model->task_check_status($args);
            case 'company_removal_logs':          return $this->Mcp_model->company_removal_logs($args);
            case 'travel_cluster_summary':        return $this->Mcp_model->travel_cluster_summary($args);
            case 'funnel_metrics':              return $this->Mcp_model->funnel_metrics($args);
            case 'closure_overview_metrics':    return $this->Mcp_model->closure_overview_metrics($args);
            case 'task_funnel_metrics':         return $this->Mcp_model->task_funnel_metrics($args);
            case 'planner_logs':                return $this->Mcp_model->planner_logs($args);
            case 'planner_live_status':         return $this->Mcp_model->planner_live_status($args);
            case 'mom_text':                    return $this->Mcp_model->mom_text($args);
            case 'mom_quality':                 return $this->Mcp_model->mom_quality($args);
            case 'mtm_meeting_quality':         return $this->Mcp_model->mtm_meeting_quality($args);
            case 'mtm_committed_not_sent':      return $this->Mcp_model->mtm_committed_not_sent($args);
            case 'mtm_manager_adherence':       return $this->Mcp_model->mtm_manager_adherence($args);
            case 'mtm_closure_scorecard':       return $this->Mcp_model->mtm_closure_scorecard($args);
            case 'mtm_coordinator':             return $this->Mcp_model->mtm_coordinator($args);
            case 'daily_huddle_pack':           return $this->Mcp_model->daily_huddle_pack($args);
            case 'sprint_closure_churn':        return $this->Mcp_model->sprint_closure_churn($args);
            case 'sprint_cluster_plan':         return $this->Mcp_model->sprint_cluster_plan($args);
            case 'bd_performance_scorecard':    return $this->Mcp_model->bd_performance_scorecard($args);
            case 'bd_category_funnel':          return $this->Mcp_model->bd_category_funnel($args);
            case 'user_attendance':               return $this->Mcp_model->user_attendance($args);
            case 'planner_live_board':            return $this->Mcp_model->planner_live_board($args);
            case 'day_ceremony_live':             return $this->Mcp_model->day_ceremony_live($args);
            case 'calling_outcome_rp_proposal':   return $this->Mcp_model->calling_outcome_rp_proposal($args);
            case 'closure_pipeline_overview':     return $this->Mcp_model->closure_pipeline_overview($args);
            case 'travel_cluster_meeting':        return $this->Mcp_model->travel_cluster_meeting($args);
            case 'meeting_detail':                return $this->Mcp_model->meeting_detail($args);
            case 'repeat_company_summary':        return $this->Mcp_model->repeat_company_summary($args);
            case 'activity_log':                  return $this->Mcp_model->activity_log($args);
            case 'lead_timeline':                 return $this->Mcp_model->lead_timeline($args);
            case 'planner_replan_log':            return $this->Mcp_model->planner_replan_log($args);
            case 'today_planner_activity':        return $this->Mcp_model->today_planner_activity($args);
            case 'next_day_planner_activity':     return $this->Mcp_model->next_day_planner_activity($args);
            case 'planner_live_report':           return $this->Mcp_model->planner_live_report($args);
            case 'company_creation_by_bd':        return $this->Mcp_model->company_creation_by_bd($args);
            case 'company_details':               return $this->Mcp_model->company_details($args);
            case 'sprint_book':                   return $this->Mcp_model->sprint_book($args);
            case 'touchpoint_model':              return $this->Mcp_model->touchpoint_model($args);
            default:                           return ['__error' => 'Unknown tool: ' . $tool];
        }
    }

    // ── Dynamic Client Registration ──────────────────────────

    public function oauth_register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'method_not_allowed'], 405);
        }
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $cid  = 'stemapp_' . $this->gen_token(8);
        $csec = $this->gen_token(16);
        $cf   = $this->cache_dir . 'clients.json';
        $clients = file_exists($cf) ? json_decode(file_get_contents($cf), true) : [];
        $clients[$cid] = [
            'secret'        => $csec,
            'redirect_uris' => isset($body['redirect_uris']) ? $body['redirect_uris'] : [],
            'created_at'    => time(),
        ];
        file_put_contents($cf, json_encode($clients));
        $this->json([
            'client_id'                  => $cid,
            'client_secret'              => $csec,
            'client_id_issued_at'        => time(),
            'client_secret_expires_at'   => 0,
            'redirect_uris'              => $clients[$cid]['redirect_uris'],
            'grant_types'                => ['authorization_code'],
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }

    // ── Authorization Endpoint ───────────────────────────────

    public function oauth_authorize() {
        $p = [
            'client_id'             => $this->input->get('client_id'),
            'redirect_uri'          => $this->input->get('redirect_uri'),
            'state'                 => $this->input->get('state'),
            'code_challenge'        => $this->input->get('code_challenge'),
            'code_challenge_method' => $this->input->get('code_challenge_method'),
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->_do_login($p);
        } else {
            $this->_show_form($p);
        }
    }

    private function _show_form($p) {
        $err = $this->session->flashdata('mcp_err');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>STEM CRM — Claude Connect</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#1e293b;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
h1{color:#38bdf8;font-size:26px;text-align:center;margin-bottom:6px;font-weight:700}
.sub{color:#94a3b8;font-size:13px;text-align:center;margin-bottom:24px}
label{display:block;color:#cbd5e1;font-size:13px;font-weight:600;margin-bottom:5px}
input[type=text],input[type=password]{width:100%;padding:11px 13px;border-radius:8px;border:1.5px solid #334155;background:#0f172a;color:#f1f5f9;font-size:15px;outline:none;margin-bottom:16px;transition:border .2s}
input:focus{border-color:#38bdf8}
button{width:100%;padding:13px;background:#38bdf8;color:#0f172a;font-size:15px;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:background .2s}
button:hover{background:#0ea5e9}
.err{background:#450a0a;color:#fca5a5;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid #7f1d1d}
.info{background:#0c1a2e;color:#93c5fd;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:20px;border:1px solid #1e3a5f}
</style></head><body><div class="box">
<h1>STEM CRM</h1>
<p class="sub">Claude ko access dene ke liye login karein</p>
<div class="info">&#x1F916; Claude aapke CRM data ko securely access karna chahta hai</div>'
. ($err ? '<div class="err">&#x274C; ' . htmlspecialchars($err) . '</div>' : '') .
'<form method="POST">
<input type="hidden" name="ci" value="' . htmlspecialchars($p['client_id']) . '">
<input type="hidden" name="ru" value="' . htmlspecialchars($p['redirect_uri']) . '">
<input type="hidden" name="st" value="' . htmlspecialchars($p['state']) . '">
<input type="hidden" name="cc" value="' . htmlspecialchars($p['code_challenge']) . '">
<input type="hidden" name="cm" value="' . htmlspecialchars($p['code_challenge_method']) . '">
<label>User ID</label>
<input type="text" name="uid" placeholder="Apna User ID dalein" required autofocus>
<label>Password</label>
<input type="password" name="pwd" placeholder="Password dalein" required>
<button type="submit">Login &amp; Authorize Claude</button>
</form></div></body></html>';
        exit;
    }

    private function _do_login($get) {
        $uid = $this->input->post('uid');
        $pwd = $this->input->post('pwd');
        $ci  = $this->input->post('ci')  ?: $get['client_id'];
        $ru  = $this->input->post('ru')  ?: $get['redirect_uri'];
        $st  = $this->input->post('st')  ?: $get['state'];
        $cc  = $this->input->post('cc')  ?: $get['code_challenge'];
        $cm  = $this->input->post('cm')  ?: $get['code_challenge_method'];

        $user = $this->Mcp_model->authenticate($uid, $pwd);
        if (!$user) {
            $this->session->set_flashdata('mcp_err', 'User ID ya Password galat hai. Dobara try karein.');
            redirect($this->base_url . '/mcp/oauth/authorize?' . http_build_query([
                'response_type'         => 'code',
                'client_id'             => $ci,
                'redirect_uri'          => $ru,
                'state'                 => $st,
                'code_challenge'        => $cc,
                'code_challenge_method' => $cm,
            ]));
            return;
        }

        $code = $this->gen_token(16);
        $this->save_tok('c_' . $code, [
            'type'           => 'code',
            'user'           => $user,
            'redirect_uri'   => $ru,
            'client_id'      => $ci,
            'code_challenge' => $cc,
            'code_method'    => $cm,
            'expires_at'     => time() + 600,
        ]);

        $sep    = (strpos($ru, '?') !== false) ? '&' : '?';
        $params = 'code=' . urlencode($code) . ($st ? '&state=' . urlencode($st) : '');
        redirect($ru . $sep . $params);
    }

    // ── Token Endpoint ───────────────────────────────────────

    public function oauth_token() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'method_not_allowed'], 405);
        }
        $grant = $this->input->post('grant_type');
        $code  = $this->input->post('code');
        $verif = $this->input->post('code_verifier');

        if ($grant !== 'authorization_code') {
            $this->json(['error' => 'unsupported_grant_type'], 400);
        }

        $cd = $this->get_tok('c_' . $code);
        if (!$cd) {
            $this->json(['error' => 'invalid_grant', 'error_description' => 'Code invalid ya expire ho gaya'], 400);
        }

        // PKCE S256 verify
        if (!empty($cd['code_challenge']) && $verif) {
            $digest = rtrim(strtr(base64_encode(hash('sha256', $verif, true)), '+/', '-_'), '=');
            if ($digest !== $cd['code_challenge']) {
                $this->json(['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed'], 400);
            }
        }

        $at = $this->gen_token(32);
        $this->save_tok($at, ['type' => 'access', 'user' => $cd['user']]);
        $this->del_tok('c_' . $code);

        $this->json([
            'access_token' => $at,
            'token_type'   => 'bearer',
            'expires_in'   => $this->token_ttl,
            'scope'        => 'mcp',
        ]);
    }

    // ── Tools List ───────────────────────────────────────────

    private function tools_list() {
        return [
            ['name' => 'get_active_users',           'description' => 'Saare active users ki list',          'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 50], 'type_id' => ['type' => 'integer']]]],
            ['name' => 'analyze_users',               'description' => 'User_details ka full analysis',        'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'zone_id' => ['type' => 'integer'], 'state' => ['type' => 'string']]]],
            ['name' => 'get_user_by_id',              'description' => 'Ek user ki poori detail',              'inputSchema' => ['type' => 'object', 'required' => ['user_id'], 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'user_id' => ['type' => 'integer']]]],
            ['name' => 'get_users_by_role',           'description' => 'Role/type ke users',                   'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'type_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 100]]]],
            ['name' => 'get_zone_analysis',           'description' => 'Zone-wise user distribution',          'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'group_by' => ['type' => 'string', 'default' => 'zone_id']]]],
            ['name' => 'get_recently_created_users',  'description' => 'Naaye registered users',               'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'days' => ['type' => 'integer', 'default' => 30], 'limit' => ['type' => 'integer', 'default' => 50]]]],
            ['name' => 'search_users',                'description' => 'Name/email/phone se search',           'inputSchema' => ['type' => 'object', 'required' => ['query'], 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'query' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 20]]]],
            ['name' => 'get_coordinator_hierarchy',   'description' => 'Coordinator/reporting hierarchy counts for a user_id',     'inputSchema' => ['type' => 'object', 'required' => ['user_id'], 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'user_id' => ['type' => 'integer']]]],

            // ---- Deep-analysis tools ----
            ['name' => 'pipeline_funnel',          'description' => 'Lead count by pipeline stage (init_call.cstatus -> status). Optional bd_id, cluster_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer']]]],
            ['name' => 'conversion_funnel',        'description' => 'Open / won / lost counts and win-rate %. Optional bd_id, cluster_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer']]]],
            ['name' => 'bd_performance',           'description' => 'Per-BD leads owned, open/won/lost, and activity count in a date range.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'zone_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 50]]]],
            ['name' => 'activity_analysis',        'description' => 'Activities (tblcallevents) by action type in a date range. Optional bd_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'company_activity_breakdown', 'description' => 'Per-company activity counts (calls, meetings, emails, whatsapp, MOM, proposals, action_taken, purpose_achieved) in a date range. One row per company (company_master.id); carries company_id + lead_ids + lead_count. Optional bd_id, cluster_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 1000]]]],
            ['name' => 'meeting_outcomes',         'description' => 'Meeting outcomes: purpose-achieved, stage advances, moves to won/lost, by new stage.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'stuck_leads',              'description' => 'Open leads idle >= N days (no activity). Revival list.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'days' => ['type' => 'integer', 'default' => 30], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 50]]]],
            ['name' => 'proposal_analysis',        'description' => 'Proposals sent in range: approved/pending counts + recent list.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'day_discipline',           'description' => 'Day-start logs per user (user_day) in a date range.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 100]]]],
            ['name' => 'cluster_performance',      'description' => 'Leads and win/loss per cluster_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'partner_mix',              'description' => 'Company count by partner type (company_master.partner).', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'bargin_meeting_analysis',  'description' => 'Bargin meetings started vs closed in range. Optional bd_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'executive_summary',        'description' => 'One-call CEO roll-up: conversion, pipeline funnel, activities, proposals, workforce for a period.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string']]]],
            ['name' => 'rp_meeting_split',         'description' => 'Meetings split by RP vs non-RP (tblcallevents.mtype) in a date range. Optional bd_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'future_tasks',             'description' => 'Upcoming planned tasks (appointment in the future), by action type and by BD.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 100]]]],
            ['name' => 'team_conversion',          'description' => 'Per-BD conversions between dates: stage advances, won, lost.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 50]]]],
            ['name' => 'proposal_closure_overview','description' => 'For proposals sent in a range, the won/lost/open outcome of their leads.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'quarter_target',           'description' => 'Quarter target vs achievement for one user (target_vs_achievement). Current financial quarter by default.', 'inputSchema' => ['type' => 'object', 'required' => ['user_id'], 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'user_id' => ['type' => 'integer'], 'quarter' => ['type' => 'string'], 'fy_year' => ['type' => 'integer']]]],
            ['name' => 'team_quarter_targets',     'description' => 'Current-quarter targets across all users (no_of_meeting, proposals, closure/potential/new-lead funnels).', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'quarter' => ['type' => 'string'], 'fy_year' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 200]]]],
            ['name' => 'expense_summary', 'description' => 'Cash-expense per user: total + manager/admin/account approval pipeline (cash_expense).', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'travel_advance_summary', 'description' => 'Travel-advance per user: cluster/admin/account approval pipeline (travel_advance).', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'handover_summary', 'description' => 'Client handovers in a date range (client_handover): counts + recent. Optional client_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'client_id' => ['type' => 'integer']]]],
            ['name' => 'bd_reviews', 'description' => 'BD-wise reviews per user (bd_wise_reviews): count, avg session time, completed/pending.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'user_id' => ['type' => 'integer'], 'review_type_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'bd_requests', 'description' => 'BD requests by status in a date range (bdrequest).', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string']]]],
            ['name' => 'mom_status', 'description' => 'Minutes-of-meeting approval status per user (mom_data): pending/approved/rejected.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'companies_missing_contact', 'description' => 'Companies with no primary contact (data hygiene): count + sample.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'focus_funnels', 'description' => 'Current-year focus-funnel leads by stage (init_call.focus_funnel). Optional bd_id, cluster_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer']]]],
            ['name' => 'meeting_metrics', 'description' => 'Meeting tile metrics (tblcallevents): total, RP, no-RP, purpose-achieved + by action type. Mirrors MeetingsDatas dashboard group.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string']]]],
            ['name' => 'proposal_task_metrics', 'description' => 'Proposal tile metrics (proposal): total, approved, rejected, pending-approval. Mirrors ProposalDetailsData dashboard group.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string']]]],
            ['name' => 'star_ratings', 'description' => 'Sales day star ratings per user (sales_star_rating): count, avg star, 4+/2- splits. Mirrors DayManagementStarRating.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'funnel_transfers', 'description' => 'Lead funnel transfers/reassignments (funnel_transfer_log): from/to user, new status, date. Mirrors FunnelTransferDetails.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'from_uid' => ['type' => 'integer'], 'to_uid' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'planner_summary', 'description' => 'Next-day planner submissions per user (task_plan_for_today): count, tasks planned, approval split. Mirrors TeamPlannerReport.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'same_status_leads', 'description' => 'Open leads idle >= N days with current status (init_call + tblcallevents). Mirrors SameStatusSinceDays.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'min_days' => ['type' => 'integer'], 'bd_id' => ['type' => 'integer'], 'cluster_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'rp_lead_calling', 'description' => 'RP-lead calling activity by BD (tblcallevents+init_call), optionally scoped to a CM/ACM line-manager. Mirrors LineManagerCallingonRPLeads.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'manager_id' => ['type' => 'integer'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'special_remarks_leads', 'description' => 'Leads with special remarks (tblcallevents.special_remarks). Mirrors SpecialRemarksLeadsCurrentFY/AdminRemarks.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'calling_outcome_conversion', 'description' => 'RP meeting outcome + RP-to-proposal conversion (tblcallevents+proposal). Mirrors RMCMCallingOutcome report.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'status_change_requests', 'description' => 'Status-change requests by transition (status_changed_required): pending vs accepted. Mirrors AllStatusChangedRequiredRequest.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'user_time_spent', 'description' => 'Per-user app activity (user_activity): events + distinct screens in range. Mirrors TodaysUserTotalTimeSpentByUrlPath.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'leave_summary', 'description' => 'Leaves per user (leave_management). Mirrors LeaveManagement.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'vendor_requests', 'description' => 'Vendor/buy requests approval pipeline (buy_requests). Mirrors VendorRequestDetails.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'attention_log', 'description' => 'Compulsive / need-attention log (compulsive_log_test): count + recent. Mirrors GetAllCompulsiveAndNeedYourAttentionLog.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'artwork_status', 'description' => 'Artwork handling status (artworks_handling): BD/FM/Pro done counts. Mirrors ArtworksHandling.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'schools', 'description' => 'School/project directory (spd) by state + list. Mirrors PreIdentifySchools/ProgramDetail.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'state' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'task_check_status', 'description' => 'Task-check status by action type (tblcallevents.approved_status): approved/pending/rejected. Mirrors TeamTaskCheck/TaskCheckLive/Live tasks.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'company_removal_logs', 'description' => 'Company removal requests in range (remove_company_log). Mirrors CheckRemoveCompanyLogsBetweenDate.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'travel_cluster_summary', 'description' => 'Travel cluster count + recent (travel_cluster). Mirrors TravelClusterDetails/TeamVisitInTravelCluster.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],

           
            [
                'name' => 'funnel_metrics',
                'description' => 'Role-scoped funnel (port of getAllCompanyBYRollesMaiing): cstatus 1-14 named stages, flag categories (focus/key/upsell/priority/top-spender/potential), FY funnels vs financial-year start, current-quarter funnels, and partner mix. Pass user_id to scope by that user\'s coordinator hierarchy.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => [
                            'type' => 'integer'
                        ],
                        'date_from' => ['type' => 'string'],
                        'date_to'   => ['type' => 'string'],
                        'date'      => ['type' => 'string']
                    ]
                ]
            ],


            ['name' => 'closure_overview_metrics', 'description' => 'Meeting->proposal->closure overview: proposal sent/not-sent, closure done, closure pending (init_call+proposal). Mirrors MeetingDoneProposalOverviewClosureStatus.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'task_funnel_metrics', 'description' => 'Task funnel counts (tblcallevents): total/complete/pending/status-change. Mirrors GetTeamTaskOnSelfOrOtherFunnelTask.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'planner_logs', 'description' => 'Re-planned task log per user (planner_log): count + recent. Mirrors Menu::GetAllPlannerLogPlannedByUsers.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'planner_live_status', 'description' => 'Live per-user planner/day status for a date: planned, autotask, day started/closed (task_plan_for_today+autotask_time+user_day). Mirrors TodaysTaskPlannerLive/TaskPlannerLive.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'mom_text', 'description' => 'Real MoM minutes text per meeting (tblcallevents.mom, actiontype_id=6) with actontaken, purpose_achieved + quality verdict (GOOD>=12 words / PARTIAL / VAGUE). Optional verdict filter.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'verdict' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'mom_quality', 'description' => 'MoM quality rollup (tblcallevents actiontype_id=6): good/partial/vague counts + action-taken & purpose-achieved fill, overall and per BD.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'mtm_meeting_quality', 'description' => 'Gate A meeting quality: per-BD RP/Fit/Purpose/MoM weighted score (>=70% quality), junk count + DQ8 risk. Meeting-to-Money Assurance.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'mtm_committed_not_sent', 'description' => 'Gate B proposal SLA: MoM-committed proposals aged vs SLA (default 5d) -> SENT/WARN/BREACH leak list. Meeting-to-Money Assurance.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'sla_days' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'mtm_manager_adherence', 'description' => 'Gate C manager closure: post-proposal live leads with last manager touch, idle days, no-weekly-touch flag. Meeting-to-Money Assurance.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'manager_id' => ['type' => 'integer'], 'week_days' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'mtm_closure_scorecard', 'description' => 'Closure efficiency scorecard by manager: proposals owned, on-time %, win %. Meeting-to-Money Assurance.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'sla_days' => ['type' => 'integer']]]],
            ['name' => 'mtm_coordinator', 'description' => 'Runs Gates A+B+C and returns the combined Meeting-to-Money leak pack (meeting quality, proposal-SLA breaches, manager no-touch).', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer']]]],
            ['name' => 'daily_huddle_pack', 'description' => 'Daily Huddle scoreboard: per-BD meetings today, RP today, pending >24h, planned/day-started, yesterday star + cluster summary. Pre-call readiness pack.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'sprint_closure_churn', 'description' => 'Quarter-End Sprint Closure Arm: live-deal churn list (positive/closure pipeline) ranked by closeability, with proposal flag, idle days, awaiting-verdict.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'manager_id' => ['type' => 'integer'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'sprint_cluster_plan', 'description' => 'Quarter-End Sprint Cluster Arm: open pipeline (open/reachout/tentative) by district + by travel cluster, for the Q+1 prospecting plan.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'bd_performance_scorecard', 'description' => 'Performance Dashboard per-BD scorecard: total/RP/No-RP meetings, RP-on-date, proposals sent/approved/pending + approved value, closures done. Date range + bd_id/manager_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'rp_on_date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'bd_category_funnel', 'description' => 'Performance Dashboard 4-category funnel per BD: Q1 Closure / To-Be-Nurture / Anchor / Upsell, each split into RP-done/pending and Proposal-done/pending. Flags vs FY start year.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'user_attendance', 'description' => 'User attendance / work-from for a date: present, WFO, WF-field, field+office (userworkfrom 1/2/3). Port of GetTodaysAllUserByReportingManager.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'date' => ['type' => 'string'], 'mode' => ['type' => 'string'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'planner_live_board', 'description' => 'Planner-live summary for a working date + planner target date: total/present/present-not-closed/present-closed/absent/planning-set/not-set. Port of DayendManagementCheckingDatas.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'date' => ['type' => 'string'], 'planner_date' => ['type' => 'string'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'day_ceremony_live', 'description' => 'Per-user day start/close board for a date (DayStartLive/DayEndLive): started, closed, not-closed, GPS + work-from.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'date' => ['type' => 'string'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'calling_outcome_rp_proposal', 'description' => 'Per-lead (cid) RP-to-proposal conversion: meetings, proposals shared, RP meetings, calls connected (actontaken+purpose_achieved). Port of RMCMCallingOutcome report.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'closure_pipeline_overview', 'description' => 'Closure-pipeline rollup: pending-for-RP, RP done, proposal sent/pending, closure, direct closure, closure-after-proposal, plus pbudgetme budgets. Port of ClosurePipeLine.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'travel_cluster_meeting', 'description' => 'Per travel cluster: leads, base/outStation, planned/complete/RP/scheduled(3)/barg(4)/virtual(22) meetings, proposal sent, closure. Port of getAllCompanyBYRollesMaiingBYMeeting.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer']]]],
            ['name' => 'meeting_detail', 'description' => 'One row per MEETING (tblcallevents actiontype 3/4/22) with company_id (cid), company_name, city/state, lead_id, bd, mtype (rp|no_rp|only_got_detail|change_rp), old_stage->new_stage, purpose_achieved, and visit_seq/is_repeat (2+ = repeat visit to same company). Filters: mtype, bd_id, manager_id.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'mtype' => ['type' => 'string'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 2000]]]],
            ['name' => 'repeat_company_summary', 'description' => 'One row per COMPANY met >= min_meetings times (default mtype=rp). Returns company_id (cid), company_name, bd, rp_meeting_count, first/last meeting date, current_status + repeat_companies/repeat_visits totals. The repeat-company list behind the repeat count, with cid.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'mtype' => ['type' => 'string'], 'min_meetings' => ['type' => 'integer', 'default' => 2], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 1000]]]],
            ['name' => 'activity_log', 'description' => 'RAW event log: one row per tblcallevents record attributed to the PERFORMER (BD or line manager). Columns: event_id, event_datetime, company_id (cid), company_name, lead_id, user_id, user_name, user_role (type_id), action_type, result, purpose_achieved, old_stage, new_stage, status_changed, remark, mom_text. Filters: company_id, lead_id, bd_id, manager_id, action_type. Deep who/when/what/result/status-change view, ungrouped.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string'], 'company_id' => ['type' => 'integer'], 'lead_id' => ['type' => 'integer'], 'bd_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'action_type' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 500]]]],
            ['name' => 'lead_timeline', 'description' => 'Chronological full activity history for ONE company/lead (same columns as activity_log) plus manager_touch flag (event performed by someone other than the lead owner). Requires lead_id or company_id.', 'inputSchema' => ['type' => 'object', 'required' => ['company_id'], 'properties' => ['lead_id' => ['type' => 'integer'], 'company_id' => ['type' => 'integer'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'date' => ['type' => 'string']]]],
            ['name' => 'planner_replan_log', 'description' => 'Re-planned task report (port of GetAllTodaysPlannerLog): one row per task with cid, company_name, to_user, task_name, current_status, org_task_date, last_create_date, duplicate_count (how many times the task was (re)planned), time_difference, remarks. Role-scoped by the requesting user_id type_id (BD sees own; CM/ACM/RM/admin see their team). Summary counts tasks_replanned + extra_replans, plus a by_user rollup. Pass user_wise=true to limit to the user own re-plans.', 'inputSchema' => ['type' => 'object', 'required' => ['user_id'], 'properties' => ['user_id' => ['type' => 'integer'], 'date' => ['type' => 'string'], 'user_wise' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 1000]]]],
            ['name' => 'today_planner_activity', 'description' => 'Rich per-user WHOLE-DAY activity for a working date (Reports/TodaysTaskPlannerLive). Port of UserWholDayActivity: day start/close + GPS, work-from planned vs actual, same-day task-plan request, planner-approval request, special-leave request, pending-task-planning request, pending-meetings request, and complete/pending task counts. Role-scoped by manager_id (_scopeUsers); pass user_id to limit to ONE user (User_Wise). Returns a summary rollup + one row per present user.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'user_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 500]]]],
            ['name' => 'next_day_planner_activity', 'description' => 'Rich per-user NEXT-DAY planner activity (Reports/TaskPlannerLive). Port of UserWholDayENDActivity: presence read from the working date (date) while planner-approval / pending-task / pending-meetings / autotask joins key off planner_date (the target plan day). No same-day task-plan or special-leave columns. Role-scoped by manager_id (_scopeUsers); pass user_id to limit to ONE user. Returns a summary rollup + one row per present user.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'planner_date' => ['type' => 'string'], 'date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'user_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 500]]]],
            ['name' => 'planner_live_report', 'description' => 'UNIFIED range-aware same-day planner report (Reports/TodaysTaskPlannerLive), grouped BY DAY each with its own summary (total_users/present/absent/planner_set/not_set + day-closed/same-day-task-plan/planner-approval/special-leave/pending-task/pending-meetings). Accepts a single date OR start_date+end_date. Role-scoped via _scopeUsersSafe (parameterised); pass user_id for one user. Returns days[] with per-day summary + user detail rows.', 'inputSchema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string'], 'start_date' => ['type' => 'string'], 'end_date' => ['type' => 'string'], 'user_id' => ['type' => 'integer'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 5000]]]],
            ['name' => 'company_creation_by_bd', 'description' => 'Per-BD new company/lead creation in a date window (port of GetCompanyInfoCreateBetweenDate). Counts new leads (init_call.new_lead=1 with a completed linked task, tce.nextCFID!=0) per BD, split by admin-approval state (approved/pending/need-to-update) and by linked task action type (barg-in meeting=4, research=10), with the BDs manager name. Manager scope reproduces the source role ladder. Params: date_from, date_to, manager_id, limit. Returns summary rollup + per-BD rows.', 'inputSchema' => ['type' => 'object', 'properties' => ['date_from' => ['type' => 'string'], 'date_to' => ['type' => 'string'], 'manager_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 500]]]],
            ['name' => 'company_details', 'description' => 'Single-company 360 view (port of Menu/CompanyDetails/{cid}) + KEY ANALYSIS. Requires company_id (= company_master.id = cid). Returns: overview, created-date understanding (created_on/last_updated/days_since_created), team (BD/Cluster-Manager/ACM resolved to names), classification flags (top_spender/upsell/focus_funnel/anchor/key_company/positive_key_client/priority_calling/potential/marked_for_monitoring), FY funnels, task-activity summary (by action), status-activity summary (by change-on status), STATUS CONVERSIONS from->to with counts (all_time + current_fy), MEETINGS done (actiontype 3/4/22 with RP/No-RP split), PROPOSALS (count/approved/pending/total_value via pbudgetme), completed/pending tasks, and ACTIVITY-GAP analysis (duration between consecutive activities: avg/min/max gap days + days_since_last_activity). Unmapped report fields are returned verbatim in company_master_raw + lead_raw.', 'inputSchema' => ['type' => 'object', 'required' => ['company_id'], 'properties' => ['company_id' => ['type' => 'integer']]]],
            ['name' => 'sprint_book', 'description' => 'Quarter-End Sprint LIVE engine (BD-Capacity workbook sheets 6/7/8). Qualifying leads = cstatus IN (6,7,9,12,13) (Positive/Closure-in-progress/Very Positive/Positive-NAP/Very Positive-NAP), DEDUPED BY COMPANY. Each row carries SLA max-days for the stage, days_overdue + BREACH flag, a T1/T2/VERIFY buy-signal tier (T1=proposal out & very-positive/closure stage; T2=proposal out or purpose achieved; VERIFY=otherwise) and RAG. Returns headline summary (expected_closures = qualifying x conversion, indicative_asv_cr), a by_bd load/capacity rollup (8-12 cap, expected closures), the book, and the model assumptions. Filters: manager_id, bd_id, limit; tunable conversion (def 0.35) and avg_ticket Cr (def 0.25).', 'inputSchema' => ['type' => 'object', 'properties' => ['manager_id' => ['type' => 'integer'], 'bd_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'default' => 500], 'conversion' => ['type' => 'number', 'default' => 0.35], 'avg_ticket' => ['type' => 'number', 'default' => 0.25]]]],
            ['name' => 'touchpoint_model', 'description' => 'STATIC reference (BD-Capacity workbook sheets 2 + 5): the cold->close touchpoint model as 13 SLA statuses (status, touchpoint, touch_count, sla_max_days, owner), the 3-week quarter-end sprint cadence, and BD capacity levers (3.3 outreaches/day, 242 working days, 8-12 priority leads, 30-40% sprint conversion, 3-5 closures/BD). No parameters; pure reference constants.', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ];
    }

    // ── Tools Call ───────────────────────────────────────────

    public function tools_call() {
        $td = $this->bearer_user();
        if (!$td) {
            header('WWW-Authenticate: Bearer realm="stemapp", resource_metadata="' . $this->base_url . '/.well-known/oauth-protected-resource"');
            $this->json(['error' => 'unauthorized', 'message' => 'Valid token required'], 401);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'POST required'], 405);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $tool = isset($body['name'])      ? $body['name']      : '';
        $args = isset($body['arguments']) ? $body['arguments'] : (isset($body['params']) ? $body['params'] : []);

        switch ($tool) {
            case 'get_active_users':           $r = $this->Mcp_model->get_active_users(isset($args['limit']) ? (int)$args['limit'] : 50, isset($args['type_id']) ? (int)$args['type_id'] : null); break;
            case 'analyze_users':              $r = $this->Mcp_model->analyze_users($args); break;
            case 'get_user_by_id':             $r = $this->Mcp_model->get_user_by_id((int)$args['user_id']); break;
            case 'get_users_by_role':          $r = $this->Mcp_model->get_users_by_role($args); break;
            case 'get_zone_analysis':          $r = $this->Mcp_model->get_zone_analysis(isset($args['group_by']) ? $args['group_by'] : 'zone_id'); break;
            case 'get_recently_created_users': $r = $this->Mcp_model->get_recently_created_users(isset($args['days']) ? (int)$args['days'] : 30, isset($args['limit']) ? (int)$args['limit'] : 50); break;
            case 'search_users':               $r = $this->Mcp_model->search_users($args['query'], isset($args['limit']) ? (int)$args['limit'] : 20); break;
            case 'get_coordinator_hierarchy':  $r = $this->Mcp_model->get_coordinator_hierarchy(isset($args['coordinator_type']) ? $args['coordinator_type'] : 'sales_co'); break;
            default: $this->json(['error' => 'Unknown tool: ' . $tool], 404);
        }
        $this->json(['result' => $r]);
    }
}