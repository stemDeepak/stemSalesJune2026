<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * ABSOLUTE LAST routes — git bridge (real Menu_model handlers, no stubs).
 */
$route['api/auth/login'] = 'auth/api_login';
$route['api/login']      = 'auth/api_login';
$route['api/auth']       = 'auth/api_login';
$route['api/auth/me']    = 'git_bridge/me_index';

$route['api/discipline/state']          = 'git_bridge/discipline_state';
$route['api/task/today']                = 'git_bridge/task_today';
$route['api/task']                      = 'git_bridge/task_today';
$route['api/task/detail']               = 'git_bridge/task_detail';
$route['api/task/submit']               = 'git_bridge/task_submit';
$route['api/task/submit_closure']       = 'git_bridge/task_submit_closure';
$route['api/task/submit_closure_timeline'] = 'git_bridge/task_submit_closure';
$route['api/task/preflight']            = 'git_bridge/task_preflight';

$route['api/planner/auto_seeded']       = 'git_bridge/planner_auto_seeded';
$route['api/planner/pbni_list']         = 'git_bridge/planner_pbni_list';
$route['api/planner/pending_autotasks'] = 'git_bridge/planner_auto_seeded';
$route['api/planner/same_day_request']  = 'git_bridge/planner_same_day_request';
$route['api/planner/yesterday_request'] = 'git_bridge/planner_yesterday_request';

$route['api/leads']                     = 'git_bridge/leads_index';
$route['api/leads/list']                = 'git_bridge/leads_index';
$route['api/lead/detail/(:num)']        = 'git_bridge/leads_detail/$1';

$route['api/mom_v2/queue']              = 'git_bridge/mom_queue';
$route['api/mom/approval_queue']        = 'git_bridge/mom_queue';
$route['api/mom/approve']               = 'git_bridge/mom_approve';
$route['api/mom/reject']                = 'git_bridge/mom_reject';

$route['api/me']                        = 'git_bridge/me_index';
$route['api/me/role']                   = 'git_bridge/me_role';
$route['api/dashboard/bd/(:num)']       = 'git_bridge/dashboard_bd/$1';

$route['api/day_ceremony/start']        = 'DayCeremonyController/start';
$route['api/day_ceremony/close']        = 'DayCeremonyController/close';
$route['api/day_ceremony/status']       = 'api/day_ceremony/status';
$route['api/day_close']                 = 'DayCeremonyController/close';
