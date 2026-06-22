<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Parity wave 2026-06-22 — beats StubController catch-all via re-float pattern.
 * See routes_stub_closeout_20260617.php for CI3 insertion-order rationale.
 */

// ---- P0 bare-path aliases (APK inventory paths that lack a segment) ----
$route['api/day_ceremony']   = 'DayCeremonyStatusController/status';
$route['api/day_ceremony/status'] = 'DayCeremonyStatusController/status';
$route['api/day_ceremony/start']  = 'DayCeremonyController/start';
$route['api/day_ceremony/close']  = 'DayCeremonyController/close';
$route['api/day_ceremony/geo_context'] = 'Day_ceremony_api/geo_context';
$route['api/day_close']      = 'DayCeremonyController/close';
$route['api/day_plan']       = 'DayPlanController/today';
$route['api/day_plan/today'] = 'DayPlanController/today';

$route['api/task']           = 'Task_api/today';
$route['api/task/today']     = 'Task_api/today';
$route['api/task/detail']    = 'Mobile_task_detail_api/detail';
$route['api/task/preflight'] = 'Mobile_stub_real/task_preflight';
$route['api/task/live']      = 'Mobile_stub_real/task_live';
$route['api/my_tasks']       = 'Task_api/my_tasks';
$route['api/my_tasks/today'] = 'MobileMisc_api/my_tasks_today';
$route['api/auto_tasks/today'] = 'Mobile_read_api/auto_tasks_today';

$route['api/planner']        = 'Planner_api/today_detail';
$route['api/planner/today']  = 'Planner_api/today_detail';
$route['api/planner/pending'] = 'v28/PlannerV28/v2_pending';
$route['api/planner/assign'] = 'Planner_api/today_detail';
$route['api/planner_v2']     = 'v28/PlannerV28/v2_pending';

$route['api/leads']          = 'Leads_api/index';
$route['api/leads/list']     = 'DataWireShim/leads_list';
$route['api/leads/detail']   = 'DataWireShim/leads_detail';

$route['api/auth']           = 'auth/api_login';
$route['api/auth/login']     = 'auth/api_login';
$route['api/auth/me']        = 'Auth/whoami';
$route['api/login']          = 'auth/api_login';

$route['api/me']             = 'Auth/whoami';
$route['api/me/role']        = 'Mobile_write_api/me_role';

// ---- Discipline / advance / expense (mobile screens) ----
$route['api/discipline/state'] = 'DisciplineApi/discipline_state';
$route['api/discipline/advance']     = 'Discipline_api/advance_my';
$route['api/discipline/advance/my']  = 'Discipline_api/advance_my';
$route['api/discipline/advance/unsettled'] = 'Discipline_api/advance_unsettled';
$route['api/discipline/advance/queue']     = 'Discipline_api/expense_ao_queue';
$route['api/discipline/advance/return']    = 'Mobile_stub_real/advance_return';
$route['api/discipline/advance/settle_v2'] = 'Mobile_stub_real/advance_settle_v2';
$route['api/discipline/expense']           = 'Discipline_api/expense_cm_queue';
$route['api/discipline/expense/cm_queue']  = 'Discipline_api/expense_cm_queue';
$route['api/discipline/expense/gate_check'] = 'Discipline_api/expense_gate_check';
$route['api/discipline/bd_score']    = 'Mobile_stub_real/discipline_bd_score';
$route['api/discipline/narrative']   = 'Mobile_stub_real/discipline_narrative';
$route['api/discipline/cancel/audit'] = 'Discipline_api/cancel_audit';

// ---- Dashboard ----
$route['api/dashboard/bd']        = 'Reports_v2150/dashboard_bd';
$route['api/dashboard/bd/(:num)'] = 'DashboardBdController/index/$1';
$route['api/dashboard/cm']        = 'DashboardCmController/index';
$route['api/dashboard/cm/(:num)'] = 'DashboardCmController/index/$1';

// ---- Planner subpaths (APK inventory) ----
$route['api/planner/auto_seeded']       = 'v28/PlannerV28/pbni_list';
$route['api/planner/pbni_list']         = 'v28/PlannerV28/pbni_list';
$route['api/planner/pending_autotasks'] = 'v28/PlannerV28/pending_autotasks';
$route['api/planner/pending_moms']      = 'v28/PlannerV28/pending_moms';
$route['api/planner/yesterday_plans']   = 'Planner_api/yesterday_plans';
$route['api/planner/research_pending'] = 'v28/PlannerV28/research_pending';
$route['api/planner/time_budget']       = 'PlannerExtra_api/time_budget';
$route['api/planner/v2/pending']        = 'v28/PlannerV28/v2_pending';
$route['api/planner/v2/leads']          = 'v28/PlannerV28/v2_leads';
$route['api/planner/v2/purposes_v2']    = 'PlannerV2Extra/purposes_v2';
$route['api/planner/v2/today_not_startedisc'] = 'v28/PlannerV28/today_not_started';
$route['api/planner_coach/today']       = 'BlitzCoach_api/today';
$route['api/planner_analyst/today']     = 'BlitzCoach_api/analyst_today';

// ---- Day management ----
$route['api/day_management'] = 'DayMgmtExtra_api/probe';
$route['api/day_management/yesterday_close_status'] = 'DayMgmtExtra_api/yesterday_close_status';

// ---- Anaya / agents bare ----
$route['api/anaya']          = 'AnayaAsk/today';
$route['api/anaya/briefing'] = 'AnayaAsk/briefing';
$route['api/agent']          = 'AgentRunner_api/probe';
$route['api/agents']         = 'Mobile_read_api/agents_list';

// ---- CM / comm / wallet ----
$route['api/cm/activities_feed'] = 'Mobile_read_api/cm_activities_feed';
$route['api/cm/calls_feed']      = 'Mobile_read_api/cm_calls_feed';
$route['api/comm/inbox']         = 'CommOrchestratorController/inbox';
$route['api/wallet/balance']     = 'Mobile_write_api/wallet_balance';
$route['api/wallet/history']     = 'Mobile_write_api/wallet_history';
$route['api/applause/today']    = 'Applause_api/today';
$route['api/productivity/bd_today'] = 'v28/MiscV28/productivity_bd_today';
$route['api/productivity/cm_today'] = 'v28/MiscV28/productivity_cm_today';

// Re-float StubController catch-alls to the very end (CI3 first-match wins).
foreach (array(
    'api/(:any)',
    'api/(:any)/(:any)',
    'api/(:any)/(:any)/(:any)',
    'api/(:any)/(:any)/(:any)/(:any)',
) as $__catchall) {
    if (isset($route[$__catchall])) {
        unset($route[$__catchall]);
    }
    $route[$__catchall] = 'StubController/handle';
}
unset($__catchall);
