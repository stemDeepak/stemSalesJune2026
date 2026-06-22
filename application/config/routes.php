<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';

|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'Menu/main';
$route['default_controller'] = 'index';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
$route['xyz-test'] = 'Menu/home';
// $route['404_override'] = 'errorpage/show404';


// AI Query Routes
$route['ai_query'] = 'ai_query/index';
$route['ai_query/process_query'] = 'ai_query/process_query';
$route['ai_query/smart_search'] = 'ai_query/smart_search';
$route['ai_query/execute_predefined'] = 'ai_query/execute_predefined';
$route['ai_query/view_table/(:any)'] = 'ai_query/view_table/$1';
$route['ai_query/view_table'] = 'ai_query/view_table';
$route['ai_query/clear_recent'] = 'ai_query/clear_recent';



$route['chat'] = 'chat/index';
$route['chat/send_message'] = 'chat/send_message';
$route['chat/get_chat_history'] = 'chat/get_chat_history';



// Mobile / REST API (STEM CRM app)
$route['auth/api_login'] = 'auth/api_login';
$route['api/login'] = 'api/auth/login';
$route['api/auth'] = 'api/auth/login';
$route['api/auth/login'] = 'api/auth/login';
$route['api/auth/me'] = 'api/auth/me';
$route['api/discipline/state'] = 'api/discipline/state';
$route['api/feature_flags/list'] = 'api/feature_flags/list';
$route['api/pilot/uids'] = 'api/pilot/uids';
$route['api/leads'] = 'api/leads/index';
$route['api/leads/list'] = 'api/leads/index';
$route['api/lead/detail/(:num)'] = 'api/leads/detail/$1';
$route['api/dashboard/bd/(:num)'] = 'api/dashboard/bd/$1';
$route['api/day_ceremony'] = 'api/day_ceremony/status';
$route['api/day_ceremony/start'] = 'api/day_ceremony/start';
$route['api/day_ceremony/close'] = 'api/day_ceremony/close';
$route['api/day_close'] = 'api/day_ceremony/close';
$route['api/day_ceremony/status'] = 'api/day_ceremony/status';
$route['api/day_ceremony/geo_context'] = 'api/day_ceremony/geo_context';
$route['api/callevents'] = 'api/callevents/list';
$route['api/callevents/list'] = 'api/callevents/list';
$route['api/task'] = 'api/task/today';
$route['api/task/today'] = 'api/task/today';
$route['api/task/detail'] = 'api/task/detail';
$route['api/task/submit'] = 'api/task/submit';
$route['api/task/submit_closure'] = 'api/task/submit_closure';
$route['api/task/submit_closure_timeline'] = 'api/task/submit_closure_timeline';
$route['api/task/preflight'] = 'api/task/preflight';
$route['api/task/save_draft'] = 'api/task/save_draft';
$route['api/meeting/initiate'] = 'api/meeting/initiate';
$route['api/meeting/start'] = 'api/meeting/start';
$route['api/meeting/close'] = 'api/meeting/close';
$route['api/meeting/barge'] = 'api/meeting/barge';
$route['api/users/active'] = 'api/users/active';
$route['api/reminder/list'] = 'api/reminder/list';
$route['api/reminder/create'] = 'api/reminder/create';
$route['api/reminder/ack'] = 'api/reminder/ack';
$route['api/me'] = 'api/me/index';
$route['api/me/role'] = 'api/me/role';

// P2 — Planner
$route['api/planner/auto_seeded'] = 'api/planner/auto_seeded';
$route['api/planner/pbni_list'] = 'api/planner/pbni_list';
$route['api/planner/pending_autotasks'] = 'api/planner/pending_autotasks';
$route['api/planner/pending_moms'] = 'api/planner/pending_moms';
$route['api/planner/yesterday_plans'] = 'api/planner/yesterday_plans';
$route['api/planner/same_day_request'] = 'api/planner/same_day_request';
$route['api/planner/yesterday_request'] = 'api/planner/yesterday_request';
$route['api/planner/same_day_decision'] = 'api/planner/same_day_decision';
$route['api/planner/yesterday_decision'] = 'api/planner/yesterday_decision';
$route['api/planner/assign_task'] = 'api/planner/assign_task';
$route['api/planner/v2/pending'] = 'api/planner_v2/pending';
$route['api/planner/v2/pending/carry'] = 'api/planner_v2/pending_carry';
$route['api/planner/pending/carry'] = 'api/planner_v2/pending_carry';
$route['api/planner/v2/leads'] = 'api/planner_v2/leads';
$route['api/planner/v2/today_not_started'] = 'api/planner_v2/today_not_started';
$route['api/planner/v2/purposes_v2'] = 'api/planner_v2/purposes_v2';

// P2 — MoM
$route['api/mom/approval_queue'] = 'api/mom/approval_queue';
$route['api/mom/templates'] = 'api/mom/templates';
$route['api/mom/transcribe'] = 'api/mom/transcribe';
$route['api/mom/approve'] = 'api/mom/approve';
$route['api/mom/reject'] = 'api/mom/reject';
$route['api/mom/bulk_approve'] = 'api/mom/bulk_approve';
$route['api/mom_v2/queue'] = 'api/mom/queue';
$route['api/lead/detail/(:num)'] = 'api/leads/detail/$1';

// P2 — Travel advance
$route['api/discipline/advance/my'] = 'api/advance/my';
$route['api/discipline/advance/request'] = 'api/advance/request';
$route['api/discipline/advance/unsettled'] = 'api/advance/unsettled';
$route['api/discipline/advance/queue'] = 'api/advance/queue';

// P2 — Anaya (stub briefing)
$route['api/anaya/briefing'] = 'api/anaya/briefing';
$route['api/anaya/today'] = 'api/anaya/today';




$route['\.well-known/oauth-protected-resource']   = 'mcp/oauth_protected_resource';
$route['\.well-known/oauth-authorization-server'] = 'mcp/oauth_authorization_server';
$route['mcp']                  = 'mcp/index';
$route['mcp/login']            = 'mcp/login';
$route['mcp/tools/call']       = 'mcp/tools_call';
$route['mcp/oauth/register']   = 'mcp/oauth_register';
$route['mcp/oauth/authorize']  = 'mcp/oauth_authorize';
$route['mcp/oauth/token']      = 'mcp/oauth_token';

/* Mobile API parity — load LAST (wins over staging catch-alls) */
if (file_exists(__DIR__ . '/routes_gate_fix_20260622.php')) {
    include __DIR__ . '/routes_gate_fix_20260622.php';
}
if (file_exists(__DIR__ . '/routes_parity_20260622.php')) {
    include __DIR__ . '/routes_parity_20260622.php';
}