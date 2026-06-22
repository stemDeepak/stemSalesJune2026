<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Gate / discipline routing fix (2026-06-22).
 * Loaded LAST in routes.php so these win over DisciplineApi / PlannerV28 stubs.
 */
$route['api/discipline/state'] = 'api/discipline/state';
$route['api/planner/auto_seeded'] = 'api/planner/auto_seeded';
$route['api/planner/pbni_list'] = 'api/planner/pbni_list';
$route['api/planner/pending_autotasks'] = 'api/planner/pending_autotasks';
$route['api/task/today'] = 'api/task/today';
