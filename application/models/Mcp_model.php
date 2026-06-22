<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * STEM CRM — MCP Model
 * Login: username + plain text password
 * Status: status = 'active'
 */
class Mcp_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    // ── AUTH ──────────────────────────────────────────────────
    public function authenticate($username, $password) {
        if (!$username || !$password) return false;

        $query = $this->db->query(
            "SELECT id, user_id, name, email, phoneno, type_id, status, zone_id, username
             FROM user_details
             WHERE username = ? AND password = ? AND status = 'active'
             LIMIT 1",
            [$username, $password]
        );

        if ($query->num_rows() === 0) return false;
        return $query->row_array();
    }

    // ── get_active_users ──────────────────────────────────────
    public function get_active_users($limit = 50, $type_id = null) {
        $sql = "SELECT id, user_id, name, email, phoneno, dob, username,
                       usercreateDate, type_id, zone_id, user_cluster_zone,
                       admin_id, sadmin_id, pro_id, sales_co, pst_co, ea_co,
                       ash_nae_co, ash_w_co, ash_s_co, rm_east_co, rm_north_co,
                       acm_co, status, inside, aadmin, badmin, cadmin,
                       base, city, district, state, country, address,
                       ucash, leave_balance, updated_at, zone_master_id,
                       cluster_master_id, base_cluster, inside_sales,
                       create_by, last_update_id
                FROM user_details
                WHERE status = 'active'";
        $params = [];
        if ($type_id !== null) {
            $sql .= " AND type_id = ?";
            $params[] = $type_id;
        }
        $sql .= " ORDER BY usercreateDate DESC LIMIT ?";
        $params[] = $limit;

        $result = $this->db->query($sql, $params)->result_array();
        return ['count' => count($result), 'users' => $result];
    }

    // ── analyze_users ─────────────────────────────────────────
    public function analyze_users($filters = []) {
        $where  = "WHERE 1=1";
        $params = [];

        if (!empty($filters['date_from'])) { $where .= " AND usercreateDate >= ?"; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where .= " AND usercreateDate <= ?"; $params[] = $filters['date_to'] . ' 23:59:59'; }
        if (!empty($filters['zone_id']))   { $where .= " AND zone_id = ?";         $params[] = (int)$filters['zone_id']; }
        if (!empty($filters['state']))     { $where .= " AND state = ?";            $params[] = $filters['state']; }

        $stats = $this->db->query(
            "SELECT COUNT(*) as total,
                    SUM(status = 'active') as active,
                    SUM(status != 'active') as inactive,
                    COUNT(DISTINCT zone_id) as total_zones,
                    COUNT(DISTINCT state) as total_states,
                    COUNT(DISTINCT type_id) as total_roles,
                    MIN(usercreateDate) as oldest_user,
                    MAX(usercreateDate) as newest_user
             FROM user_details $where", $params
        )->row_array();

        $roles = $this->db->query(
            "SELECT type_id, COUNT(*) as count FROM user_details $where GROUP BY type_id ORDER BY count DESC", $params
        )->result_array();

        $zones = $this->db->query(
            "SELECT zone_id, state, COUNT(*) as count FROM user_details $where GROUP BY zone_id, state ORDER BY count DESC LIMIT 10", $params
        )->result_array();

        $states = $this->db->query(
            "SELECT state, COUNT(*) as count FROM user_details $where AND state != '' GROUP BY state ORDER BY count DESC LIMIT 15", $params
        )->result_array();

        $monthly = $this->db->query(
            "SELECT DATE_FORMAT(usercreateDate, '%Y-%m') as month, COUNT(*) as count
             FROM user_details $where AND usercreateDate >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month ORDER BY month DESC", $params
        )->result_array();

        return [
            'summary'           => $stats,
            'role_distribution' => $roles,
            'zone_distribution' => $zones,
            'state_distribution'=> $states,
            'monthly_signups'   => $monthly,
            'filters_applied'   => $filters,
        ];
    }

    // ── get_user_by_id ────────────────────────────────────────
    public function get_user_by_id($user_id) {
        $result = $this->db->query(
            "SELECT * FROM user_details WHERE user_id = ? LIMIT 1", [$user_id]
        )->row_array();
        if (!$result) return ['error' => 'User not found: ' . $user_id];
        unset($result['password']);
        return $result;
    }

    // ── get_users_by_role ─────────────────────────────────────
    public function get_users_by_role($args = []) {
        $sql = "SELECT id, user_id, name, email, phoneno, username, type_id, zone_id,
                       state, district, city, base, status, inside_sales,
                       sales_co, pst_co, usercreateDate
                FROM user_details WHERE 1=1";
        $params = [];
        if (!empty($args['type_id']))      { $sql .= " AND type_id = ?";      $params[] = (int)$args['type_id']; }
        if (!empty($args['inside_sales'])) { $sql .= " AND inside_sales = ?"; $params[] = $args['inside_sales']; }
        $limit = isset($args['limit']) ? (int)$args['limit'] : 100;
        $sql .= " ORDER BY name ASC LIMIT ?";
        $params[] = $limit;
        $result = $this->db->query($sql, $params)->result_array();
        return ['count' => count($result), 'users' => $result];
    }

    // ── get_zone_analysis ─────────────────────────────────────
    public function get_zone_analysis($group_by = 'zone_id') {
        $allowed = ['zone_id','state','district','city','base','cluster_master_id','zone_master_id'];
        if (!in_array($group_by, $allowed)) $group_by = 'zone_id';
        $result = $this->db->query(
            "SELECT `$group_by` as group_name,
                    COUNT(*) as total_users,
                    SUM(status = 'active') as active_users,
                    COUNT(DISTINCT type_id) as roles_present
             FROM user_details
             WHERE `$group_by` IS NOT NULL AND `$group_by` != ''
             GROUP BY `$group_by` ORDER BY total_users DESC LIMIT 50"
        )->result_array();
        return ['group_by' => $group_by, 'count' => count($result), 'data' => $result];
    }

    // ── get_recently_created_users ────────────────────────────
    public function get_recently_created_users($days = 30, $limit = 50) {
        $result = $this->db->query(
            "SELECT id, user_id, name, email, phoneno, username, type_id, zone_id,
                    state, city, status, usercreateDate, create_by
             FROM user_details
             WHERE usercreateDate >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY usercreateDate DESC LIMIT ?",
            [$days, $limit]
        )->result_array();
        return ['days' => $days, 'count' => count($result), 'users' => $result];
    }

    // ── search_users ──────────────────────────────────────────
    public function search_users($query, $limit = 20) {
        $q = '%' . $query . '%';
        $result = $this->db->query(
            "SELECT id, user_id, name, email, phoneno, username, type_id,
                    zone_id, state, city, status, usercreateDate
             FROM user_details
             WHERE name LIKE ? OR email LIKE ? OR phoneno LIKE ?
                OR username LIKE ? OR CAST(user_id AS CHAR) LIKE ?
             ORDER BY name ASC LIMIT ?",
            [$q, $q, $q, $q, $q, $limit]
        )->result_array();
        return ['query' => $query, 'count' => count($result), 'users' => $result];
    }

    // ── get_coordinator_hierarchy ─────────────────────────────
    // public function get_coordinator_hierarchy($coordinator_type = 'sales_co') {
    //     $allowed = ['sales_co','pst_co','ea_co','ash_nae_co','ash_w_co','ash_s_co','rm_east_co','rm_north_co','acm_co'];
    //     if (!in_array($coordinator_type, $allowed)) $coordinator_type = 'sales_co';
    //     $result = $this->db->query(
    //         "SELECT `$coordinator_type` as coordinator_id,
    //                 COUNT(*) as assigned_users,
    //                 SUM(status = 'active') as active_users,
    //                 COUNT(DISTINCT zone_id) as zones_covered
    //          FROM user_details
    //          WHERE `$coordinator_type` IS NOT NULL AND `$coordinator_type` != 0
    //          GROUP BY `$coordinator_type` ORDER BY assigned_users DESC LIMIT 50"
    //     )->result_array();
    //     return ['coordinator_type' => $coordinator_type, 'count' => count($result), 'data' => $result];
    // }

    public function get_coordinator_hierarchy($cur_userid)
{
    $cur_userid = (int)$cur_userid;
    $utype = $this->Menu_model->get_userbyid($cur_userid);
    $userTypeid = $utype[0]->type_id;

    if($userTypeid == 1){
        $column = 'sadmin_id';
    }elseif($userTypeid == 2){
        $column = 'admin_id';
    }elseif($userTypeid == 4){
        $column = 'pst_co';
    }elseif($userTypeid == 13){
        $column = 'aadmin';
    }elseif($userTypeid == 15){
        $column = 'sales_co';
    }elseif($userTypeid == 19){
        $column = 'ash_nae_co';
    }elseif($userTypeid == 20){
        $column = 'ash_w_co';
    }elseif($userTypeid == 21){
        $column = 'ash_s_co';
    }elseif($userTypeid == 22){
        $column = 'rm_east_co';
    }elseif($userTypeid == 23){
        $column = 'rm_north_co';
    }elseif($userTypeid == 24){
        $column = 'acm_co';
    }else{
        $column = 'user_id';
    }

    $result = $this->db->query("
        SELECT
            COUNT(*) AS total_users,
            SUM(status = 'active') AS active_users,
            COUNT(DISTINCT zone_id) AS zones_covered
        FROM user_details
        WHERE (
            $column = $cur_userid
            OR user_id = $cur_userid
        )
    ")->row_array();

    return [
        'hierarchy_column' => $column,
        'current_user_id'  => $cur_userid,
        'data'             => $result
    ];
}

    // ========================================================================
    // DEEP-ANALYSIS METHODS (parity with Menu_model / Report_model / Management_model)
    // All read-only, all parameterized (CI query bindings). Optional filters:
    //   bd_id (init_call.mainbd / tblcallevents.user_id), cluster_id, date_from, date_to.
    // Pipeline statuses live in `status` (id 1..13); action types in `action`.
    // ========================================================================

    private function _dateRange($f) {
        // Default = TODAY (present day). Any tool may override with date_from/date_to,
        // sdate/edate, or a single-day `date`. Values are normalised to full-day bounds.
        $day  = !empty($f['date']) ? $f['date'] : (!empty($f['day']) ? $f['day'] : '');
        $from = !empty($f['date_from']) ? $f['date_from'] : (!empty($f['sdate']) ? $f['sdate'] : ($day !== '' ? $day : date('Y-m-d')));
        $to   = !empty($f['date_to'])   ? $f['date_to']   : (!empty($f['edate']) ? $f['edate'] : ($day !== '' ? $day : date('Y-m-d')));
        $from = substr($from, 0, 10) . ' 00:00:00';
        $to   = substr($to, 0, 10) . ' 23:59:59';
        return [$from, $to];
    }

    // 1) Pipeline funnel: lead count by stage (init_call.cstatus -> status.name).
    public function pipeline_funnel($f = []) {
        $sql = "SELECT s.id AS status_id, s.name AS stage, s.color AS color,
                       COUNT(ic.id) AS leads
                FROM status s
                LEFT JOIN init_call ic ON ic.cstatus = s.id";
        $params = [];
        $on = [];
        if (!empty($f['bd_id']))      { $on[] = "ic.mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $on[] = "ic.cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        if ($on) $sql = str_replace("ON ic.cstatus = s.id", "ON ic.cstatus = s.id AND " . implode(' AND ', $on), $sql);
        $sql .= " GROUP BY s.id ORDER BY s.id ASC";
        $rows = $this->db->query($sql, $params)->result_array();
        $total = array_sum(array_map(function($r){ return (int)$r['leads']; }, $rows));
        return ['total_leads' => $total, 'funnel' => $rows, 'filters' => $f];
    }

    // 2) Conversion summary: open / won / lost / win-rate.
    public function conversion_funnel($f = []) {
        $sql = "SELECT
                  SUM(cstatus NOT IN (12,13)) AS open_leads,
                  SUM(cstatus = 12) AS won,
                  SUM(cstatus = 13) AS lost,
                  COUNT(*) AS total,
                  ROUND(SUM(cstatus = 12) / NULLIF(SUM(cstatus IN (12,13)),0) * 100, 1) AS win_rate_pct
                FROM init_call WHERE 1=1";
        $params = [];
        if (!empty($f['bd_id']))      { $sql .= " AND mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $sql .= " AND cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        return ['conversion' => $this->db->query($sql, $params)->row_array(), 'filters' => $f];
    }

    // 3) BD performance: leads owned + open/won/lost + activity count in range.
    public function bd_performance($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 50;
        $sql = "SELECT u.user_id, u.name, u.type_id, u.zone_id,
                       COUNT(DISTINCT ic.id) AS leads_owned,
                       SUM(ic.cstatus = 12) AS won,
                       SUM(ic.cstatus = 13) AS lost,
                       SUM(ic.cstatus NOT IN (12,13)) AS open_leads,
                       (SELECT COUNT(*) FROM tblcallevents tc
                          WHERE tc.user_id = u.user_id
                            AND tc.appointmentdatetime BETWEEN ? AND ?) AS activities_in_range
                FROM user_details u
                LEFT JOIN init_call ic ON ic.mainbd = u.user_id
                WHERE u.status = 'active' AND u.type_id = 3";
        $params = [$from, $to];
        if (!empty($f['zone_id'])) { $sql .= " AND u.zone_id = ?"; $params[] = (int)$f['zone_id']; }
        $sql .= " GROUP BY u.user_id ORDER BY leads_owned DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['range' => [$from, $to], 'count' => count($rows), 'bds' => $rows];
    }

    // 4) Activity analysis: tblcallevents by action type within a date range.
    public function activity_analysis($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT a.id AS action_id, a.name AS action,
                       COUNT(tc.id) AS total,
                       SUM(tc.actontaken IS NOT NULL AND tc.actontaken <> '') AS acted,
                       SUM(tc.purpose_achieved = 1) AS purpose_achieved
                FROM tblcallevents tc
                LEFT JOIN action a ON a.id = tc.actiontype_id
                WHERE tc.appointmentdatetime BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND tc.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY tc.actiontype_id ORDER BY total DESC";
        $rows = $this->db->query($sql, $params)->result_array();
        return ['range' => [$from, $to], 'by_action' => $rows, 'filters' => $f];
    }

    public function company_activity_breakdown($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? max(1, min((int)$f['limit'], 5000)) : 1000;

        $sql = "SELECT cm.id AS company_id,
                       cm.compname AS company_name,
                       COUNT(DISTINCT ic.id) AS lead_count,
                       GROUP_CONCAT(DISTINCT ic.id ORDER BY ic.id) AS lead_ids,
                       MAX(ic.mainbd) AS bd_id,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.id) AS stages,
                       SUM(tc.actiontype_id IN (1,26))    AS calls,
                       SUM(tc.actiontype_id IN (3,4,22))  AS meetings,
                       SUM(tc.actiontype_id = 2)  AS emails,
                       SUM(tc.actiontype_id = 5)  AS whatsapp,
                       SUM(tc.actiontype_id = 6)  AS mom_written,
                       SUM(tc.actiontype_id = 18) AS mom_check,
                       SUM(tc.actiontype_id = 7)  AS proposals_written,
                       SUM(tc.actiontype_id = 21) AS proposal_check,
                       SUM(tc.actiontype_id = 10) AS research,
                       SUM(tc.actiontype_id NOT IN (1,2,3,4,5,6,7,10,18,21,22,26)) AS other_activities,
                       SUM(tc.actontaken IS NOT NULL AND tc.actontaken <> '') AS action_taken,
                       SUM(tc.purpose_achieved = 1) AS purpose_achieved,
                       COUNT(tc.id) AS total_activities,
                       MAX(tc.appointmentdatetime) AS last_activity,
                       DATEDIFF(NOW(), MAX(tc.appointmentdatetime)) AS days_since_activity
                FROM init_call ic
                JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN status s ON s.id = ic.cstatus
                INNER JOIN tblcallevents tc ON tc.cid_id = ic.id
                WHERE tc.appointmentdatetime BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $sql .= " AND ic.cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        $sql .= " GROUP BY cm.id ORDER BY total_activities DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['range' => [$from, $to], 'count' => count($rows), 'companies' => $rows, 'filters' => $f];
    }

    // 5) Meeting outcomes: status progression + purpose-achieved on activities.
    public function meeting_outcomes($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(purpose_achieved = 1) AS purpose_achieved,
                       SUM(nstatus_id > status_id) AS stage_advanced,
                       SUM(nstatus_id = 13) AS moved_to_lost,
                       SUM(nstatus_id = 12) AS moved_to_won
                FROM tblcallevents
                WHERE appointmentdatetime BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND user_id = ?"; $params[] = (int)$f['bd_id']; }
        $summary = $this->db->query($sql, $params)->row_array();

        $bySql = "SELECT s.name AS new_stage, COUNT(tc.id) AS cnt
                  FROM tblcallevents tc LEFT JOIN status s ON s.id = tc.nstatus_id
                  WHERE tc.appointmentdatetime BETWEEN ? AND ?";
        $bp = [$from, $to];
        if (!empty($f['bd_id'])) { $bySql .= " AND tc.user_id = ?"; $bp[] = (int)$f['bd_id']; }
        $bySql .= " GROUP BY tc.nstatus_id ORDER BY cnt DESC";
        return ['range' => [$from, $to], 'summary' => $summary, 'by_new_stage' => $this->db->query($bySql, $bp)->result_array()];
    }

    // 6) Stuck leads: open leads with no activity in >= N days (default 30).
    public function stuck_leads($f = []) {
        $days  = isset($f['days'])  ? (int)$f['days']  : 30;
        $limit = isset($f['limit']) ? (int)$f['limit'] : 50;
        $sql = "SELECT cm.id AS lead_id, cm.compname AS company, s.name AS stage,
                       ic.mainbd AS bd_user_id,
                       DATEDIFF(NOW(), MAX(tc.updateddate)) AS days_idle,
                       MAX(tc.updateddate) AS last_activity
                FROM init_call ic
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN status s ON s.id = ic.cstatus
                LEFT JOIN tblcallevents tc ON tc.cid_id = ic.id
                WHERE ic.cstatus NOT IN (12,13)";
        $params = [];
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $sql .= " AND ic.cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        $sql .= " GROUP BY ic.id
                  HAVING days_idle >= ? OR days_idle IS NULL
                  ORDER BY (days_idle IS NULL) DESC, days_idle DESC LIMIT ?";
        $params[] = $days; $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['idle_threshold_days' => $days, 'count' => count($rows), 'leads' => $rows];
    }

    // 7) Proposal analysis: counts by approval state + recent proposals.
    public function proposal_analysis($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sumSql = "SELECT COUNT(*) AS total,
                          SUM(apr = 1) AS approved,
                          SUM(apr = 0 OR apr IS NULL) AS pending
                   FROM proposal WHERE sdatet BETWEEN ? AND ?";
        $sp = [$from, $to];
        if (!empty($f['bd_id'])) { $sumSql .= " AND user_id = ?"; $sp[] = (int)$f['bd_id']; }
        $summary = $this->db->query($sumSql, $sp)->row_array();

        $recSql = "SELECT p.id, p.user_id, p.sdatet AS sent_on, p.apr AS approved,
                          cm.compname AS company
                   FROM proposal p
                   LEFT JOIN init_call ic ON ic.id = p.init_id
                   LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                   WHERE p.sdatet BETWEEN ? AND ?";
        $rp = [$from, $to];
        if (!empty($f['bd_id'])) { $recSql .= " AND p.user_id = ?"; $rp[] = (int)$f['bd_id']; }
        $recSql .= " ORDER BY p.sdatet DESC LIMIT 50";
        return ['range' => [$from, $to], 'summary' => $summary, 'recent' => $this->db->query($recSql, $rp)->result_array()];
    }

    // 8) Day discipline: who logged days in range (user_day), grouped by user.
    public function day_discipline($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT ud.user_id, u.name,
                       COUNT(ud.id) AS days_logged,
                       MIN(ud.sdatet) AS first_day, MAX(ud.sdatet) AS last_day
                FROM user_day ud
                LEFT JOIN user_details u ON u.user_id = ud.user_id
                WHERE ud.sdatet BETWEEN ? AND ?
                GROUP BY ud.user_id ORDER BY days_logged DESC LIMIT ?";
        $rows = $this->db->query($sql, [$from, $to, $limit])->result_array();
        return ['range' => [$from, $to], 'count' => count($rows), 'attendance' => $rows];
    }

    // 9) Cluster performance: leads + wins per cluster_id.
    public function cluster_performance($f = []) {
        $sql = "SELECT ic.cluster_id,
                       COUNT(*) AS leads,
                       SUM(ic.cstatus = 12) AS won,
                       SUM(ic.cstatus = 13) AS lost,
                       SUM(ic.cstatus NOT IN (12,13)) AS open_leads
                FROM init_call ic
                WHERE ic.cluster_id IS NOT NULL AND ic.cluster_id <> 0
                GROUP BY ic.cluster_id ORDER BY leads DESC LIMIT 100";
        return ['clusters' => $this->db->query($sql)->result_array()];
    }

    // 10) Partner mix: company_master grouped by partner type.
    public function partner_mix($f = []) {
        $sql = "SELECT partner, COUNT(*) AS companies
                FROM company_master
                WHERE partner IS NOT NULL AND partner <> ''
                GROUP BY partner ORDER BY companies DESC LIMIT 50";
        return ['partner_mix' => $this->db->query($sql)->result_array()];
    }

    // 11) Bargin-meeting analysis: started vs closed in range.
    public function bargin_meeting_analysis($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(closem IS NOT NULL AND closem <> '') AS closed,
                       SUM(potentional_client = 1) AS potential_clients
                FROM barginmeeting WHERE storedt BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND user_id = ?"; $params[] = (int)$f['bd_id']; }
        return ['range' => [$from, $to], 'summary' => $this->db->query($sql, $params)->row_array()];
    }

    // 12) Executive summary: one-call CEO roll-up.
    public function executive_summary($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $conv = $this->conversion_funnel($f)['conversion'];
        $funnel = $this->pipeline_funnel($f)['funnel'];
        $acts = $this->db->query(
            "SELECT COUNT(*) AS activities, SUM(purpose_achieved = 1) AS purpose_achieved
             FROM tblcallevents WHERE appointmentdatetime BETWEEN ? AND ?", [$from, $to]
        )->row_array();
        $props = $this->db->query(
            "SELECT COUNT(*) AS proposals_sent FROM proposal WHERE sdatet BETWEEN ? AND ?", [$from, $to]
        )->row_array();
        $activeBds = $this->db->query(
            "SELECT COUNT(*) AS active_bds FROM user_details WHERE status = 'active' AND type_id = 3"
        )->row_array();
        return [
            'period'          => ['from' => $from, 'to' => $to],
            'conversion'      => $conv,
            'pipeline_funnel' => $funnel,
            'activities'      => $acts,
            'proposals'       => $props,
            'workforce'       => $activeBds,
            'generated_at'    => date('Y-m-d H:i:s'),
        ];
    }


    // 13) RP vs non-RP meeting split (tblcallevents.mtype = 'rp' | 'norp') in a date range.
    public function rp_meeting_split($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT COALESCE(NULLIF(LOWER(mtype), ''), 'unspecified') AS meeting_type,
                       COUNT(*) AS meetings,
                       SUM(purpose_achieved = 1) AS purpose_achieved
                FROM tblcallevents
                WHERE appointmentdatetime BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY LOWER(mtype) ORDER BY meetings DESC";
        return ['range' => [$from, $to], 'by_meeting_type' => $this->db->query($sql, $params)->result_array()];
    }

    // 14) Future / upcoming tasks (appointmentdatetime >= now), by action and by BD.
    public function future_tasks($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $aSql = "SELECT a.name AS action, COUNT(tc.id) AS upcoming
                 FROM tblcallevents tc LEFT JOIN action a ON a.id = tc.actiontype_id
                 WHERE tc.appointmentdatetime >= NOW()";
        $ap = [];
        if (!empty($f['bd_id'])) { $aSql .= " AND tc.user_id = ?"; $ap[] = (int)$f['bd_id']; }
        $aSql .= " GROUP BY tc.actiontype_id ORDER BY upcoming DESC";
        $byAction = $this->db->query($aSql, $ap)->result_array();

        $bSql = "SELECT tc.user_id, u.name, COUNT(tc.id) AS upcoming
                 FROM tblcallevents tc LEFT JOIN user_details u ON u.user_id = tc.user_id
                 WHERE tc.appointmentdatetime >= NOW()";
        $bp = [];
        if (!empty($f['bd_id'])) { $bSql .= " AND tc.user_id = ?"; $bp[] = (int)$f['bd_id']; }
        $bSql .= " GROUP BY tc.user_id ORDER BY upcoming DESC LIMIT ?";
        $bp[] = $limit;
        return ['by_action' => $byAction, 'by_bd' => $this->db->query($bSql, $bp)->result_array()];
    }

    // 15) Team conversion between dates: activities that advanced a stage / won / lost, per BD.
    public function team_conversion($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 50;
        $sql = "SELECT tc.user_id, u.name,
                       COUNT(*) AS activities,
                       SUM(tc.nstatus_id > tc.status_id) AS stage_advanced,
                       SUM(tc.nstatus_id = 12) AS won,
                       SUM(tc.nstatus_id = 13) AS lost
                FROM tblcallevents tc LEFT JOIN user_details u ON u.user_id = tc.user_id
                WHERE tc.appointmentdatetime BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND tc.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY tc.user_id ORDER BY won DESC, stage_advanced DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'by_bd' => $this->db->query($sql, $params)->result_array()];
    }

    // 16) Proposal closure overview: outcome (won/lost/open) of leads that have a proposal.
    public function proposal_closure_overview($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT COUNT(DISTINCT p.id) AS proposals,
                       SUM(ic.cstatus = 12) AS lead_won,
                       SUM(ic.cstatus = 13) AS lead_lost,
                       SUM(ic.cstatus NOT IN (12,13)) AS lead_open
                FROM proposal p
                LEFT JOIN init_call ic ON ic.id = p.init_id
                WHERE p.sdatet BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND p.user_id = ?"; $params[] = (int)$f['bd_id']; }
        return ['range' => [$from, $to], 'overview' => $this->db->query($sql, $params)->row_array()];
    }


    // Indian financial-year quarter (Apr-Jun=Q1 .. Jan-Mar=Q4). Jan-Mar belongs to the FY that began the previous April.
    private function _financialQuarter() {
        $m = (int)date('n');
        if ($m >= 4 && $m <= 6)   return ['q' => 'Q1', 'fy' => (int)date('Y')];
        if ($m >= 7 && $m <= 9)   return ['q' => 'Q2', 'fy' => (int)date('Y')];
        if ($m >= 10 && $m <= 12) return ['q' => 'Q3', 'fy' => (int)date('Y')];
        return ['q' => 'Q4', 'fy' => (int)date('Y') - 1];
    }

    // 17) Quarter target vs achievement for ONE user (current financial quarter).
    //     Mirrors Menu_model::GetCurrentQuarterQuarterTarget. Returns the full
    //     target_vs_achievement row (targets AND achievement columns).
    public function quarter_target($f = []) {
        if (empty($f['user_id'])) return ['__error' => 'user_id required'];
        $fq = $this->_financialQuarter();
        $q  = !empty($f['quarter']) ? $f['quarter'] : $fq['q'];
        $fy = !empty($f['fy_year']) ? (int)$f['fy_year'] : $fq['fy'];
        $sql = "SELECT tva.*, allreview.reviewtype
                FROM target_vs_achievement tva
                LEFT JOIN allreview ON allreview.id = tva.review_id
                WHERE tva.currentQuarter = ?
                  AND allreview.reviewtype IN ('Quarterly','Self Quarterly','Querterly')
                  AND YEAR(tva.created_at) = ?
                  AND tva.user_id = ?
                ORDER BY tva.id DESC LIMIT 1";
        $row = $this->db->query($sql, [$q, $fy, (int)$f['user_id']])->row_array();
        return ['quarter' => $q, 'fy_year' => $fy, 'user_id' => (int)$f['user_id'], 'target' => $row];
    }

    // 18) Team quarter targets: current-quarter target rows across all users.
    public function team_quarter_targets($f = []) {
        $fq = $this->_financialQuarter();
        $q     = !empty($f['quarter']) ? $f['quarter'] : $fq['q'];
        $fy    = !empty($f['fy_year']) ? (int)$f['fy_year'] : $fq['fy'];
        $limit = isset($f['limit']) ? (int)$f['limit'] : 200;
        $sql = "SELECT tva.user_id, u.name,
                       tva.no_of_meeting, tva.proposal_send, tva.proposals_sent_and_closure,
                       tva.twetenty_closure_funnel, tva.potential_funnel_for_fy,
                       tva.to_be_nurtured_for_fy, tva.fifity_new_lead_funnel,
                       tva.anchor_client_meeting, tva.currentQuarter, tva.start_date, tva.end_date
                FROM target_vs_achievement tva
                LEFT JOIN allreview ON allreview.id = tva.review_id
                LEFT JOIN user_details u ON u.user_id = tva.user_id
                WHERE tva.currentQuarter = ?
                  AND allreview.reviewtype IN ('Quarterly','Self Quarterly','Querterly')
                  AND YEAR(tva.created_at) = ?
                ORDER BY u.name ASC LIMIT ?";
        $rows = $this->db->query($sql, [$q, $fy, $limit])->result_array();
        return ['quarter' => $q, 'fy_year' => $fy, 'count' => count($rows), 'targets' => $rows];
    }


    // ========================================================================
    // PARITY / GAP-CLOSING tools (AllPageLinks report domains)
    // All read-only, parameterized. Approval codes 0=pending,1=approved,2=reject,3=suspect.
    // ========================================================================

    // expense_summary (cash_expense) — manager/admin/account approval pipeline + amounts.
    public function expense_summary($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT u.user_id, u.name,
                       COUNT(*) AS forms,
                       SUM(ce.expense) AS total_expense,
                       SUM(CASE WHEN ce.verify='1' AND ce.admin_apr='1' AND ce.account_apr='1' THEN ce.expense ELSE 0 END) AS fully_approved_amount,
                       SUM(ce.verify='0') AS manager_pending,
                       SUM(ce.verify='1') AS manager_approved,
                       SUM(ce.verify='2') AS manager_rejected,
                       SUM(ce.verify='1' AND ce.admin_apr='0') AS admin_pending,
                       SUM(ce.verify='1' AND ce.admin_apr='1' AND ce.account_apr='0') AS account_pending
                FROM cash_expense ce
                LEFT JOIN user_details u ON u.user_id = ce.user_id
                WHERE u.name IS NOT NULL";
        $params = [];
        if (!empty($f['bd_id'])) { $sql .= " AND ce.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY ce.user_id ORDER BY total_expense DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['count' => count($rows), 'expense_by_user' => $rows];
    }

    // travel_advance_summary (travel_advance) — cluster/admin/account approval pipeline.
    public function travel_advance_summary($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT u.user_id, u.name,
                       COUNT(*) AS requests,
                       SUM(ta.verify='0') AS verify_pending,
                       SUM(ta.cluster_apr='1') AS cluster_approved,
                       SUM(ta.admin_apr='1') AS admin_approved,
                       SUM(ta.account_apr='1') AS account_approved,
                       SUM(ta.cluster_apr='0' OR ta.admin_apr='0' OR ta.account_apr='0') AS in_pipeline
                FROM travel_advance ta
                LEFT JOIN user_details u ON u.user_id = ta.user_id
                WHERE u.name IS NOT NULL";
        $params = [];
        if (!empty($f['bd_id'])) { $sql .= " AND ta.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY ta.user_id ORDER BY requests DESC LIMIT ?";
        $params[] = $limit;
        return ['count' => 0, 'advance_by_user' => $this->db->query($sql, $params)->result_array()];
    }

    // handover_summary (client_handover) — count + recent in a date range.
    public function handover_summary($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT COUNT(*) AS handovers, COUNT(DISTINCT client_id) AS clients
                FROM client_handover WHERE CAST(sdatet AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['client_id'])) { $sql .= " AND client_id = ?"; $params[] = (int)$f['client_id']; }
        $summary = $this->db->query($sql, $params)->row_array();
        $recent = $this->db->query(
            "SELECT client_id, CAST(sdatet AS DATE) AS handover_date FROM client_handover
             WHERE CAST(sdatet AS DATE) BETWEEN ? AND ? ORDER BY sdatet DESC LIMIT 50", [$from, $to])->result_array();
        return ['range' => [$from, $to], 'summary' => $summary, 'recent' => $recent];
    }

    // bd_reviews (bd_wise_reviews + review_types) — reviews per reviewed user.
    public function bd_reviews($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT bwr.to_uid AS user_id, u.name, rt.name AS review_type,
                       COUNT(*) AS reviews,
                       ROUND(AVG(NULLIF(bwr.session_time, 0)), 1) AS avg_session_time,
                       SUM(bwr.review_status = 1) AS completed,
                       SUM(bwr.review_status = 0) AS pending
                FROM bd_wise_reviews bwr
                LEFT JOIN review_types rt ON rt.id = bwr.review_type_id
                LEFT JOIN user_details u ON u.user_id = bwr.to_uid
                WHERE CAST(bwr.created_at AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['user_id']))        { $sql .= " AND bwr.to_uid = ?";         $params[] = (int)$f['user_id']; }
        if (!empty($f['review_type_id'])) { $sql .= " AND bwr.review_type_id = ?"; $params[] = (int)$f['review_type_id']; }
        $sql .= " GROUP BY bwr.to_uid, bwr.review_type_id ORDER BY reviews DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'reviews' => $this->db->query($sql, $params)->result_array()];
    }

    // bd_requests (bdrequest) — request volume by status in a date range.
    public function bd_requests($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT status, COUNT(*) AS requests
                FROM bdrequest
                WHERE CAST(sdatet AS DATE) BETWEEN ? AND ?
                GROUP BY status ORDER BY requests DESC";
        $rows = $this->db->query($sql, [$from, $to])->result_array();
        $total = $this->db->query("SELECT COUNT(*) AS total FROM bdrequest WHERE CAST(sdatet AS DATE) BETWEEN ? AND ?", [$from, $to])->row_array();
        return ['range' => [$from, $to], 'total' => $total['total'], 'by_status' => $rows];
    }

    // mom_status (mom_data) — minutes-of-meeting approval status. approved_status 0=pending,1=approved,2=reject.
    public function mom_status($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT u.user_id, u.name,
                       COUNT(*) AS moms,
                       SUM(md.approved_status = 0) AS pending,
                       SUM(md.approved_status = 1) AS approved,
                       SUM(md.approved_status = 2) AS rejected
                FROM mom_data md
                LEFT JOIN user_details u ON u.user_id = md.user_id
                WHERE 1=1";
        $params = [];
        if (!empty($f['bd_id'])) { $sql .= " AND md.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY md.user_id ORDER BY moms DESC LIMIT ?";
        $params[] = $limit;
        return ['by_user' => $this->db->query($sql, $params)->result_array()];
    }

    // companies_missing_contact — companies with no primary contact (data hygiene).
    public function companies_missing_contact($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT cm.id AS company_id, cm.compname AS company, cm.city, cm.state
                FROM company_master cm
                LEFT JOIN company_contact_master ccm ON ccm.company_id = cm.id
                WHERE ccm.id IS NULL
                ORDER BY cm.id DESC LIMIT ?";
        $rows = $this->db->query($sql, [$limit])->result_array();
        $cnt = $this->db->query("SELECT COUNT(*) AS missing FROM company_master cm
                LEFT JOIN company_contact_master ccm ON ccm.company_id = cm.id WHERE ccm.id IS NULL")->row_array();
        return ['companies_missing_contact' => $cnt['missing'], 'sample' => $rows];
    }

    // focus_funnels (init_call.focus_funnel) — leads flagged as current-year focus funnel.
    public function focus_funnels($f = []) {
        $sql = "SELECT s.name AS stage, COUNT(ic.id) AS focus_leads
                FROM init_call ic LEFT JOIN status s ON s.id = ic.cstatus
                WHERE ic.focus_funnel IS NOT NULL AND ic.focus_funnel <> '' AND ic.focus_funnel <> '0'";
        $params = [];
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $sql .= " AND ic.cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        $sql .= " GROUP BY ic.cstatus ORDER BY s.id";
        $rows = $this->db->query($sql, $params)->result_array();
        $total = array_sum(array_map(function($r){ return (int)$r['focus_leads']; }, $rows));
        return ['total_focus_leads' => $total, 'by_stage' => $rows];
    }


    // ========================================================================
    // DASHBOARD METRIC bundles (mirror the Excel tile groups, from base tables)
    // Counts computed from confirmed base tables. The exact "fresh/re/anchor"
    // and view-bucketed tiles are computed view-side in the web app and are
    // intentionally omitted here rather than guessed.
    // ========================================================================

    // meeting_metrics -> mirrors Reports::MeetingsDatas tile group (tblcallevents).
    public function meeting_metrics($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $where = "WHERE CAST(e.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $where .= " AND e.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql = "SELECT
                  COUNT(*)                                   AS total_meetings,
                  SUM(e.mtype = 'rp')                        AS total_rp_meetings,
                  SUM(e.mtype = 'norp')                      AS total_no_rp_meetings,
                  SUM(e.purpose_achieved = 1)                AS purpose_achieved_meetings,
                  SUM(e.purpose_achieved = 0 OR e.purpose_achieved IS NULL) AS not_achieved_meetings
                FROM tblcallevents e $where";
        $summary = $this->db->query($sql, $params)->row_array();
        $byType = $this->db->query(
            "SELECT a.name AS action_type, COUNT(*) AS meetings
             FROM tblcallevents e LEFT JOIN action a ON a.id = e.actiontype_id
             $where GROUP BY e.actiontype_id ORDER BY meetings DESC", $params
        )->result_array();
        return ['range' => [$from, $to], 'metrics' => $summary, 'by_action_type' => $byType];
    }

    // proposal_task_metrics -> mirrors Reports::ProposalDetailsData tile group (proposal).
    public function proposal_task_metrics($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $where = "WHERE CAST(p.sdatet AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $where .= " AND p.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql = "SELECT
                  COUNT(*)              AS total_proposals,
                  SUM(p.apr = 1)        AS proposal_approved,
                  SUM(p.apr = 2)        AS proposal_rejected,
                  SUM(p.apr = 0 OR p.apr IS NULL) AS pending_for_approval
                FROM proposal p $where";
        $metrics = $this->db->query($sql, $params)->row_array();
        return ['range' => [$from, $to], 'metrics' => $metrics];
    }


    // ========================================================================
    // PARITY batch 2 — gap report families (grounded in Report_model tables)
    // ========================================================================

    // star_ratings -> DayManagementStarRating (sales_star_rating: date, star, user_id).
    public function star_ratings($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT ssr.user_id, u.name,
                       COUNT(*) AS ratings,
                       ROUND(AVG(ssr.star), 2) AS avg_star,
                       SUM(ssr.star >= 4) AS four_plus,
                       SUM(ssr.star <= 2) AS two_or_less
                FROM sales_star_rating ssr
                LEFT JOIN user_details u ON u.user_id = ssr.user_id
                WHERE CAST(ssr.date AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND ssr.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY ssr.user_id ORDER BY avg_star DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'by_user' => $this->db->query($sql, $params)->result_array()];
    }

    // funnel_transfers -> FunnelTransferDetails (funnel_transfer_log: cid, from_uid, to_uid, by_uid, new_status, remarks, created_at).
    public function funnel_transfers($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT ftl.id, cm.compname AS company, ftl.from_uid, ftl.to_uid, ftl.by_uid,
                       ftl.new_status, CAST(ftl.created_at AS DATE) AS transfer_date
                FROM funnel_transfer_log ftl
                LEFT JOIN company_master cm ON cm.id = ftl.cid
                WHERE CAST(ftl.created_at AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['to_uid']))   { $sql .= " AND ftl.to_uid = ?";   $params[] = (int)$f['to_uid']; }
        if (!empty($f['from_uid'])) { $sql .= " AND ftl.from_uid = ?"; $params[] = (int)$f['from_uid']; }
        $sql .= " ORDER BY ftl.created_at DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        $total = $this->db->query("SELECT COUNT(*) AS total FROM funnel_transfer_log WHERE CAST(created_at AS DATE) BETWEEN ? AND ?", [$from, $to])->row_array();
        return ['range' => [$from, $to], 'total_transfers' => $total['total'], 'transfers' => $rows];
    }

    // planner_summary -> TeamPlannerReport (task_plan_for_today: user_id, date, taskcnt, approvel_status).
    // approvel_status (sic): 0=pending, 1=approved, 2=rejected.
    public function planner_summary($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT tpft.user_id, u.name,
                       COUNT(*) AS planners,
                       SUM(tpft.taskcnt) AS tasks_planned,
                       SUM(tpft.approvel_status = 1) AS approved,
                       SUM(tpft.approvel_status = 0) AS pending,
                       SUM(tpft.approvel_status = 2) AS rejected
                FROM task_plan_for_today tpft
                LEFT JOIN user_details u ON u.user_id = tpft.user_id
                WHERE CAST(tpft.date AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND tpft.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY tpft.user_id ORDER BY planners DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'by_user' => $this->db->query($sql, $params)->result_array()];
    }

    // same_status_leads -> SameStatusSinceDays: open leads idle >= N days, with current status.
    public function same_status_leads($f = []) {
        $minDays = isset($f['min_days']) ? (int)$f['min_days'] : 30;
        $limit   = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT cm.id AS lead_id, cm.compname AS company, s.name AS current_status,
                       ic.mainbd AS bd_id,
                       DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) AS days_since_activity
                FROM init_call ic
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN status s ON s.id = ic.cstatus
                LEFT JOIN tblcallevents t ON t.cid_id = ic.id
                WHERE ic.cstatus NOT IN (12, 13)";
        $params = [];
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?";     $params[] = (int)$f['bd_id']; }
        if (!empty($f['cluster_id'])) { $sql .= " AND ic.cluster_id = ?"; $params[] = (int)$f['cluster_id']; }
        $sql .= " GROUP BY ic.id HAVING days_since_activity >= ? ORDER BY days_since_activity DESC LIMIT ?";
        $params[] = $minDays; $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['min_days' => $minDays, 'count' => count($rows), 'leads' => $rows];
    }


    // ========================================================================
    // PARITY batch 3 — close remaining gap families. LineManager scope = CM/ACM
    // (init_call.clm_id = cluster manager, init_call.acm_co_id = assistant CM).
    // ========================================================================

    // rp_lead_calling -> LineManagerCallingonRPLeads (CM/ACM). RP-lead call activity.
    public function rp_lead_calling($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT ic.mainbd AS bd_id, u.name AS bd_name,
                       COUNT(*) AS rp_calls,
                       COUNT(DISTINCT ic.id) AS rp_leads,
                       SUM(t.purpose_achieved = 'yes') AS purpose_achieved,
                       SUM(t.purpose_achieved = 'no')  AS not_achieved
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN user_details u ON u.user_id = ic.mainbd
                WHERE UPPER(t.mtype) = 'RP'
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['manager_id'])) {
            $sql .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)";
            $params[] = (int)$f['manager_id']; $params[] = (int)$f['manager_id'];
        }
        if (!empty($f['bd_id'])) { $sql .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY ic.mainbd ORDER BY rp_calls DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'by_bd' => $this->db->query($sql, $params)->result_array()];
    }

    // special_remarks_leads -> SpecialRemarksLeadsCurrentFY / AdminRemarks.
    public function special_remarks_leads($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT cm.id AS lead_id, cm.compname AS company, ic.mainbd AS bd_id,
                       s.name AS current_status, t.special_remarks,
                       CAST(t.appointmentdatetime AS DATE) AS remark_date
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN status s ON s.id = ic.cstatus
                WHERE t.special_remarks IS NOT NULL AND t.special_remarks <> ''
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " ORDER BY t.appointmentdatetime DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        return ['range' => [$from, $to], 'count' => count($rows), 'remarks' => $rows];
    }

    // calling_outcome_conversion -> RMCMCallingOutcomeANDRPProposalConversion.
    public function calling_outcome_conversion($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT
                  COUNT(*) AS rp_meetings,
                  SUM(t.purpose_achieved = 'yes') AS purpose_achieved,
                  COUNT(DISTINCT ic.id) AS rp_leads,
                  COUNT(DISTINCT p.init_id) AS leads_with_proposal
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN proposal p ON p.init_id = ic.id
                WHERE UPPER(t.mtype) = 'RP'
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }
        $row = $this->db->query($sql, $params)->row_array();
        return ['range' => [$from, $to], 'outcome' => $row];
    }

    // status_change_requests -> AllStatusChangedRequiredRequest (status_changed_required).
    public function status_change_requests($f = []) {
        $sql = "SELECT current_status, change_on_status,
                       COUNT(*) AS requests,
                       SUM(request_accept_by IS NULL OR request_accept_by = 0) AS pending,
                       SUM(request_accept_by IS NOT NULL AND request_accept_by <> 0) AS accepted
                FROM status_changed_required
                GROUP BY current_status, change_on_status
                ORDER BY requests DESC LIMIT 200";
        return ['by_transition' => $this->db->query($sql)->result_array()];
    }

    // user_time_spent -> TodaysUserTotalTimeSpentByUrlPath (user_activity).
    public function user_time_spent($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT ua.user_id, u.name,
                       COUNT(*) AS events,
                       COUNT(DISTINCT ua.url_path) AS distinct_screens
                FROM user_activity ua
                LEFT JOIN user_details u ON u.user_id = ua.user_id
                WHERE CAST(ua.event_time AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND ua.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY ua.user_id ORDER BY events DESC LIMIT ?";
        $params[] = $limit;
        return ['range' => [$from, $to], 'by_user' => $this->db->query($sql, $params)->result_array()];
    }

    // leave_summary -> LeaveManagement (leave_management).
    public function leave_summary($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT lm.leave_by AS user_id, u.name, COUNT(*) AS leaves
                FROM leave_management lm
                LEFT JOIN user_details u ON u.user_id = lm.leave_by
                GROUP BY lm.leave_by ORDER BY leaves DESC LIMIT ?";
        return ['by_user' => $this->db->query($sql, [$limit])->result_array()];
    }

    // vendor_requests -> VendorRequestDetails (buy_requests).
    public function vendor_requests($f = []) {
        $sql = "SELECT COUNT(*) AS total,
                       SUM(am_approved_status = 1)    AS am_approved,
                       SUM(am_approved_status = 0)    AS am_pending,
                       SUM(admin_approved_status = 1) AS admin_approved,
                       SUM(admin_approved_status = 0) AS admin_pending
                FROM buy_requests";
        return ['summary' => $this->db->query($sql)->row_array()];
    }

    // attention_log -> GetAllCompulsiveAndNeedYourAttentionLog (compulsive_log_test).
    public function attention_log($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 50;
        $total = $this->db->query("SELECT COUNT(*) AS total FROM compulsive_log_test")->row_array();
        $sample = $this->db->query("SELECT * FROM compulsive_log_test ORDER BY id DESC LIMIT ?", [$limit])->result_array();
        return ['total' => $total['total'], 'recent' => $sample];
    }

    // artwork_status -> ArtworksHandling (artworks_handling).
    public function artwork_status($f = []) {
        $sql = "SELECT COUNT(*) AS total,
                       SUM(bd_status = 1)  AS bd_done,
                       SUM(fm_status = 1)  AS fm_done,
                       SUM(pro_status = 1) AS pro_done
                FROM artworks_handling";
        return ['summary' => $this->db->query($sql)->row_array()];
    }

    // schools -> PreIdentifySchools / ProgramDetail (spd = school/project directory).
    public function schools($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $byState = $this->db->query(
            "SELECT sstate AS state, COUNT(*) AS schools FROM spd GROUP BY sstate ORDER BY schools DESC LIMIT 50"
        )->result_array();
        $sql = "SELECT id, project_code, sname AS school, scity AS city, sstate AS state, status FROM spd";
        $params = [];
        if (!empty($f['state'])) { $sql .= " WHERE sstate = ?"; $params[] = $f['state']; }
        $sql .= " ORDER BY id DESC LIMIT ?";
        $params[] = $limit;
        return ['by_state' => $byState, 'schools' => $this->db->query($sql, $params)->result_array()];
    }


    // ========================================================================
    // PARITY batch 4 — final gap closure.
    // ========================================================================

    // task_check_status -> TeamTaskCheck / TaskCheckLive / Live*Task family.
    // tblcallevents.approved_status: 0=pending, 1=approved, 2=rejected.
    public function task_check_status($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sql = "SELECT a.name AS action_type,
                       COUNT(*) AS tasks,
                       SUM(t.approved_status = 1) AS approved,
                       SUM(t.approved_status = 0 OR t.approved_status IS NULL) AS pending,
                       SUM(t.approved_status = 2) AS rejected
                FROM tblcallevents t
                LEFT JOIN action a ON a.id = t.actiontype_id
                WHERE CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND t.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY t.actiontype_id ORDER BY tasks DESC";
        return ['range' => [$from, $to], 'by_action_type' => $this->db->query($sql, $params)->result_array()];
    }

    // company_removal_logs -> CheckRemoveCompanyLogsBetweenDate (remove_company_log).
    public function company_removal_logs($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $total = $this->db->query(
            "SELECT COUNT(*) AS total FROM remove_company_log WHERE CAST(request_date AS DATE) BETWEEN ? AND ?",
            [$from, $to])->row_array();
        $rows = $this->db->query(
            "SELECT request_id, task_id, reamrks AS remarks, CAST(request_date AS DATE) AS request_date
             FROM remove_company_log WHERE CAST(request_date AS DATE) BETWEEN ? AND ?
             ORDER BY request_date DESC LIMIT ?", [$from, $to, $limit])->result_array();
        return ['range' => [$from, $to], 'total' => $total['total'], 'recent' => $rows];
    }

    // travel_cluster_summary -> TravelClusterDetails / TeamVisitInTravelCluster (schema-agnostic).
    public function travel_cluster_summary($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 50;
        $total = $this->db->query("SELECT COUNT(*) AS total FROM travel_cluster")->row_array();
        $recent = $this->db->query("SELECT * FROM travel_cluster ORDER BY id DESC LIMIT ?", [$limit])->result_array();
        return ['total_clusters' => $total['total'], 'recent' => $recent];
    }


    // ========================================================================
    // METRIC bundles (exact named tiles from the dashboard model methods).
    // Funnel cstatus map (GetFunnelDetails): 1=open 2=reachout 3=tentative
    // 4=will_do_later 5=not_interested 6=positive 7=closure 8=open_rpem
    // 9=very_positive 10=ttd_reachout 11=wno_reachout 12=positive_nap
    // 13=very_positive_nap 14=on_boarded.
    // ========================================================================

    // funnel_metrics -> faithful port of getAllCompanyBYRollesMaiing (role-scoped funnel).
    // cstatus 1-14 named stages; FY funnels compared to financial-year START YEAR;
    // quarter funnels compared to "Q"+quarter. Scope = role-based on user_id's type_id.
    public function funnel_metrics($f = []) {
        // financial year start year + current quarter (mirrors Menu_model helpers)
        $mo = (int)date('n'); $yr = (int)date('Y');
        $cfyear = ($mo >= 4) ? $yr : $yr - 1;
        $q = ($mo >= 4 && $mo <= 6) ? 1 : (($mo >= 7 && $mo <= 9) ? 2 : (($mo >= 10 && $mo <= 12) ? 3 : 4));
        $cq = 'Q' . $q;

        // role-scoped WHERE (mirrors the $text switch on the scope user's type_id)
        $text = "1=1"; $mainBD = 'init_call.mainbd'; $joinInside = '';
        if (!empty($f['user_id'])) {
            $uid = (int)$f['user_id'];
            $ud = $this->db->query("SELECT type_id, inside_sales FROM user_details WHERE user_id = ?", [$uid])->row();
            $tid = $ud ? (int)$ud->type_id : 0;
            switch ($tid) {
                case 1:  $text = "(u1.sadmin_id = $uid OR u1.user_id = $uid)"; break;
                case 2:  $text = "(u1.admin_id = $uid OR u1.user_id = $uid)"; break;
                case 3:  $text = "u1.user_id = $uid"; break;
                case 4:  $text = "(u1.pst_co = $uid OR u1.user_id = $uid)"; break;
                case 13: $text = "(u1.aadmin = $uid OR u1.user_id = $uid)"; break;
                case 15: $text = "(u1.sales_co = '$uid')"; break;
                case 19: $text = "(u1.ash_nae_co = '$uid' OR u1.user_id = $uid)"; break;
                case 20: $text = "(u1.ash_w_co = '$uid' OR u1.user_id = $uid)"; break;
                case 21: $text = "(u1.ash_s_co = '$uid' OR u1.user_id = $uid)"; break;
                case 22: $text = "(u1.rm_east_co = '$uid' OR u1.user_id = $uid)"; break;
                case 23: $text = "(u1.rm_north_co = '$uid' OR u1.user_id = $uid)"; break;
                case 24: $text = "(u1.acm_co = $uid OR u1.user_id = $uid)"; break;
                case 25: $mainBD = 'init_call.creator_id'; $text = "(u1.admin_id = $uid OR u1.user_id = $uid)"; break;
                default: $text = "(u1.admin_id = $uid OR u1.user_id = $uid)"; break;
            }
            if ($ud && $ud->inside_sales === 'yes') { $joinInside = " OR init_call.insidebd = $uid"; }
        }
        $join  = "LEFT JOIN user_details u1 ON u1.user_id = $mainBD$joinInside";
        $where = "WHERE $text AND u1.status = 'active'";

        $stage = $this->db->query("SELECT
            COUNT(init_call.id) AS total,
            COUNT(CASE WHEN init_call.cstatus=1 THEN 1 END) AS open,
            COUNT(CASE WHEN init_call.cstatus=2 THEN 1 END) AS reachout,
            COUNT(CASE WHEN init_call.cstatus=3 THEN 1 END) AS tentative,
            COUNT(CASE WHEN init_call.cstatus=4 THEN 1 END) AS will_do_later,
            COUNT(CASE WHEN init_call.cstatus=5 THEN 1 END) AS not_interested,
            COUNT(CASE WHEN init_call.cstatus=6 THEN 1 END) AS positive,
            COUNT(CASE WHEN init_call.cstatus=7 THEN 1 END) AS closure,
            COUNT(CASE WHEN init_call.cstatus=8 THEN 1 END) AS open_rpem,
            COUNT(CASE WHEN init_call.cstatus=9 THEN 1 END) AS very_positive,
            COUNT(CASE WHEN init_call.cstatus=10 THEN 1 END) AS ttd_reachout,
            COUNT(CASE WHEN init_call.cstatus=11 THEN 1 END) AS wno_reachout,
            COUNT(CASE WHEN init_call.cstatus=12 THEN 1 END) AS positive_nap,
            COUNT(CASE WHEN init_call.cstatus=13 THEN 1 END) AS very_positive_nap,
            COUNT(CASE WHEN (init_call.cstatus=14 OR init_call.upsell_client='yes') THEN 1 END) AS on_boarded
            FROM init_call $join $where")->row_array();

        $flags = $this->db->query("SELECT
            COUNT(CASE WHEN init_call.focus_funnel='yes' THEN 1 END) AS focus_funnel,
            COUNT(CASE WHEN init_call.upsell_client='yes' THEN 1 END) AS upsell_client,
            COUNT(CASE WHEN init_call.keycompany='yes' THEN 1 END) AS key_client,
            COUNT(CASE WHEN init_call.pkclient='yes' THEN 1 END) AS positive_key_client,
            COUNT(CASE WHEN init_call.priorityc='yes' THEN 1 END) AS priority_calling,
            COUNT(CASE WHEN init_call.topspender='yes' THEN 1 END) AS top_spender,
            COUNT(CASE WHEN init_call.potential='yes' THEN 1 END) AS potential_client
            FROM init_call $join $where")->row_array();

        $fy = $this->db->query("SELECT
            COUNT(CASE WHEN init_call.q1_twetenty_closure_funnel='$cfyear' THEN 1 END) AS twetenty_closure_funnel,
            COUNT(CASE WHEN init_call.potential_funnel_for_fy='$cfyear' THEN 1 END) AS potential_funnel_for_fy,
            COUNT(CASE WHEN init_call.to_be_nurtured_for_fy='$cfyear' THEN 1 END) AS to_be_nurtured_for_fy,
            COUNT(CASE WHEN init_call.fifity_new_lead_funnel='$cfyear' THEN 1 END) AS fifity_new_lead_funnel,
            COUNT(CASE WHEN init_call.anchor_clients='yes' THEN 1 END) AS anchor_clients,
            COUNT(CASE WHEN init_call.need_to_be_monitored IS NOT NULL THEN 1 END) AS need_to_be_monitored
            FROM init_call $join $where")->row_array();

        $quarter = $this->db->query("SELECT
            COUNT(CASE WHEN init_call.in_quarter='20 Closure Funnel in $cq' THEN 1 END) AS current_quarter_twetenty_closure_funnel,
            COUNT(CASE WHEN init_call.in_quarter='Potential Funnel For $cq' THEN 1 END) AS current_quarter_potential_funnel_for_fy,
            COUNT(CASE WHEN init_call.in_quarter='To be Nurtured for $cq' THEN 1 END) AS current_quarter_to_be_nurtured_for_fy,
            COUNT(CASE WHEN init_call.prospecting_funnel='$cq' THEN 1 END) AS total_prospecting_funnel,
            COUNT(CASE WHEN init_call.proposal_to_be_sent_review='$cq' THEN 1 END) AS total_proposal_to_be_sent_review,
            COUNT(CASE WHEN init_call.proposal_to_be_sent_target='$cq' THEN 1 END) AS total_proposal_to_be_sent_target,
            COUNT(CASE WHEN init_call.closure_pipeline='$cq' THEN 1 END) AS total_closure_pipeline
            FROM init_call $join $where")->row_array();

        $partner = $this->db->query("SELECT
            COUNT(init_call.id) AS total,
            COUNT(CASE WHEN partner_master.id=1 THEN 1 END) AS corporate,
            COUNT(CASE WHEN partner_master.id=2 THEN 1 END) AS psu,
            COUNT(CASE WHEN partner_master.id=3 THEN 1 END) AS ngo,
            COUNT(CASE WHEN partner_master.id=4 THEN 1 END) AS private_school,
            COUNT(CASE WHEN partner_master.id=13 THEN 1 END) AS mnc,
            COUNT(CASE WHEN partner_master.id=16 THEN 1 END) AS dmft,
            COUNT(CASE WHEN partner_master.id=17 THEN 1 END) AS elected_representatives
            FROM init_call $join
            LEFT JOIN company_master ON company_master.id=init_call.cmpid_id
            LEFT JOIN partner_master ON partner_master.id=company_master.partnerType_id
            $where")->row_array();

        return [
            'financial_year_start' => $cfyear,
            'current_quarter'      => $cq,
            'by_stage'             => $stage,
            'flags'                => $flags,
            'fy_funnels'           => $fy,
            'current_quarter_funnels' => $quarter,
            'partner_mix'          => $partner,
        ];
    }

    // closure_overview_metrics -> Reports::MeetingDoneProposalOverviewClosureStatus.
    public function closure_overview_metrics($f = []) {
        $w = "WHERE 1=1"; $p = [];
        if (!empty($f['bd_id'])) { $w .= " AND ic.mainbd = ?"; $p[] = (int)$f['bd_id']; }
        $sql = "SELECT
                  COUNT(DISTINCT ic.id) AS leads,
                  COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN ic.id END) AS proposal_sent,
                  COUNT(DISTINCT CASE WHEN p.id IS NULL THEN ic.id END)     AS proposal_not_sent,
                  COUNT(DISTINCT CASE WHEN ic.cstatus IN (7, 14) THEN ic.id END) AS closure_done,
                  COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND ic.cstatus NOT IN (7, 14) THEN ic.id END) AS proposal_sent_closure_pending
                FROM init_call ic LEFT JOIN proposal p ON p.init_id = ic.id
                $w";
        return ['overview' => $this->db->query($sql, $p)->row_array()];
    }

    // task_funnel_metrics -> Reports::GetTeamTaskOnSelfOrOtherFunnelTask (tblcallevents).
    public function task_funnel_metrics($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $w = "WHERE CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?"; $p = [$from, $to];
        if (!empty($f['bd_id'])) { $w .= " AND t.user_id = ?"; $p[] = (int)$f['bd_id']; }
        $sql = "SELECT
                  COUNT(*) AS total_task,
                  SUM(t.status_id IS NOT NULL AND t.status_id <> 0) AS complete_task,
                  SUM(t.status_id IS NULL OR t.status_id = 0)       AS pending_task,
                  SUM(t.nstatus_id IS NOT NULL AND t.nstatus_id <> 0 AND t.nstatus_id <> t.status_id) AS status_change_task
                FROM tblcallevents t $w";
        return ['range' => [$from, $to], 'metrics' => $this->db->query($sql, $p)->row_array()];
    }


    // planner_logs -> Menu::GetAllPlannerLogPlannedByUsers (planner_log: re-planned tasks).
    public function planner_logs($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT pl.to_user AS user_id, u.name, COUNT(*) AS replanned_tasks
                FROM planner_log pl
                LEFT JOIN user_details u ON u.user_id = pl.to_user
                WHERE CAST(pl.re_created_at AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND pl.to_user = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY pl.to_user ORDER BY replanned_tasks DESC LIMIT ?";
        $params[] = $limit;
        $byUser = $this->db->query($sql, $params)->result_array();
        $recent = $this->db->query(
            "SELECT pl.to_user AS user_id, pl.task_id, cm.id AS lead_id,
                    CAST(pl.org_task_date AS DATE) AS original_date,
                    CAST(pl.re_created_at AS DATE) AS replanned_on, pl.remarks
             FROM planner_log pl
            LEFT JOIN init_call ic  ON ic.id = pl.init_id
            LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
             WHERE CAST(pl.re_created_at AS DATE) BETWEEN ? AND ?
             ORDER BY pl.re_created_at DESC LIMIT 50", [$from, $to])->result_array();
        return ['range' => [$from, $to], 'by_user' => $byUser, 'recent' => $recent];
    }

    // planner_live_status -> Reports::TodaysTaskPlannerLive / TaskPlannerLive.
    // Per active user for a date: planned? day started/closed? autotask logged?
    public function planner_live_status($f = []) {
        $date = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $limit = isset($f['limit']) ? (int)$f['limit'] : 300;
        $sql = "SELECT u.user_id, u.name,
                       MAX(CASE WHEN tpft.id  IS NOT NULL THEN 1 ELSE 0 END) AS has_planned,
                       MAX(CASE WHEN aut.id   IS NOT NULL THEN 1 ELSE 0 END) AS has_autotask,
                       MAX(CASE WHEN ud.ustart IS NOT NULL THEN 1 ELSE 0 END) AS day_started,
                       MAX(CASE WHEN ud.uclose IS NOT NULL THEN 1 ELSE 0 END) AS day_closed
                FROM user_details u
                LEFT JOIN task_plan_for_today tpft ON tpft.user_id = u.user_id AND CAST(tpft.date AS DATE) = ?
                LEFT JOIN autotask_time aut        ON aut.user_id  = u.user_id AND CAST(aut.date AS DATE)  = ?
                LEFT JOIN user_day ud              ON ud.user_id   = u.user_id AND CAST(ud.ustart AS DATE) = ?
                WHERE u.status = 'active'";
        $params = [$date, $date, $date];
        if (!empty($f['bd_id'])) { $sql .= " AND u.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql .= " GROUP BY u.user_id ORDER BY has_planned ASC, day_closed ASC, u.name ASC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        $summary = [
            'users'        => count($rows),
            'planned'      => 0, 'not_planned'  => 0,
            'day_closed'   => 0, 'day_open'     => 0,
        ];
        foreach ($rows as $r) {
            $summary[$r['has_planned'] ? 'planned' : 'not_planned']++;
            $summary[$r['day_closed']  ? 'day_closed' : 'day_open']++;
        }
        return ['date' => $date, 'summary' => $summary, 'users' => $rows];
    }


    // ========================================================================
    // MoM CONTENT tools — real minutes text from tblcallevents.mom (actiontype_id=6),
    // with actontaken + purpose_achieved and a server-side quality verdict.
    // ========================================================================

    // mom_text -> per-meeting MoM content + verdict (GOOD >=12 words / PARTIAL / VAGUE).
    public function mom_text($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? (int)$f['limit'] : 100;
        $sql = "SELECT t.id AS task_id, cm.id AS lead_id, cm.compname AS company,
                       t.user_id AS bd_id, u.name AS bd_name, s.name AS stage,
                       CAST(t.appointmentdatetime AS DATE) AS meeting_date,
                       t.mom, t.actontaken, t.purpose_achieved
                FROM tblcallevents t
                LEFT JOIN init_call ic    ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u   ON u.user_id = t.user_id
                LEFT JOIN status s         ON s.id = ic.cstatus
                WHERE t.actiontype_id = 6
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND t.user_id = ?"; $params[] = (int)$f['bd_id']; }
        if (isset($f['verdict']) && in_array($f['verdict'], ['GOOD','PARTIAL','VAGUE'])) {
            // filtering applied in PHP below (verdict is derived), keep SQL simple
        }
        $sql .= " ORDER BY t.appointmentdatetime DESC LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        $vagueWords = ['na','n/a','-','.','nil','none','done','ok'];
        foreach ($rows as &$r) {
            $mom = trim((string)($r['mom'] ?? ''));
            $wc  = ($mom === '') ? 0 : count(preg_split('/\s+/', $mom));
            if ($mom === '' || in_array(strtolower($mom), $vagueWords, true) || $wc <= 1) {
                $verdict = 'VAGUE';
            } elseif ($wc >= 12) {
                $verdict = 'GOOD';
            } else {
                $verdict = 'PARTIAL';
            }
            $r['word_count'] = $wc;
            $r['verdict'] = $verdict;
        }
        unset($r);
        if (isset($f['verdict']) && in_array($f['verdict'], ['GOOD','PARTIAL','VAGUE'], true)) {
            $rows = array_values(array_filter($rows, function ($x) use ($f) { return $x['verdict'] === $f['verdict']; }));
        }
        return ['range' => [$from, $to], 'count' => count($rows), 'moms' => $rows];
    }

    // mom_quality -> good/partial/vague rollup + action-taken & purpose-achieved fill rates.
    public function mom_quality($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $wc    = "(LENGTH(TRIM(t.mom)) - LENGTH(REPLACE(TRIM(t.mom), ' ', '')) + 1)";
        $vague = "(t.mom IS NULL OR TRIM(t.mom) = '' OR LOWER(TRIM(t.mom)) IN ('na','n/a','-','.','nil','none','done','ok') OR $wc <= 1)";
        $good  = "(NOT $vague AND $wc >= 12)";
        $sql = "SELECT COUNT(*) AS total_moms,
                       SUM($good) AS good,
                       SUM(NOT $good AND NOT $vague) AS partial,
                       SUM($vague) AS vague,
                       SUM(t.actontaken IS NOT NULL AND TRIM(t.actontaken) <> '' AND LOWER(TRIM(t.actontaken)) NOT IN ('na','-')) AS action_taken_filled,
                       SUM(t.purpose_achieved = 'yes') AS purpose_achieved_yes
                FROM tblcallevents t
                WHERE t.actiontype_id = 6
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $sql .= " AND t.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $overall = $this->db->query($sql, $params)->row_array();

        $byBd = $this->db->query(
            "SELECT t.user_id AS bd_id, u.name AS bd_name,
                    COUNT(*) AS moms,
                    SUM($good) AS good,
                    SUM(NOT $good AND NOT $vague) AS partial,
                    SUM($vague) AS vague
             FROM tblcallevents t
             LEFT JOIN user_details u ON u.user_id = t.user_id
             WHERE t.actiontype_id = 6
               AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?
             GROUP BY t.user_id ORDER BY moms DESC LIMIT 100",
            [$from, $to])->result_array();

        return ['range' => [$from, $to], 'overall' => $overall, 'by_bd' => $byBd];
    }


    // ========================================================================
    // MEETING-TO-MONEY ASSURANCE MODULE (mtm_*)
    // Gate A meeting quality | Gate B proposal SLA | Gate C manager closure
    // + closure scorecard + coordinator. cstatus map: 6=positive 7=closure
    // 9=very_positive 12=positive_nap 13=very_positive_nap 14=on_boarded.
    // ========================================================================

    // mtm_meeting_quality -> Gate A. Score = RP 40% + Fit 20% + Purpose 20% + MoM 20%;
    // quality >= 0.70; DQ8 = 3+ junk (<0.70) meetings in the window.
    public function mtm_meeting_quality($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $where = "meet.mtype IS NOT NULL AND meet.mtype <> '' AND CAST(meet.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $where .= " AND meet.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $score = "(0.40*(UPPER(meet.mtype) IN ('RP','RPCLOSE','CHANGE RP'))"
               . "+0.20*(ic.potential='yes' OR ic.anchor_clients='yes')"
               . "+0.20*(meet.purpose_achieved='yes')"
               . "+0.20*(CASE WHEN mom.mom IS NULL OR TRIM(mom.mom)='' THEN 0 "
               . "WHEN (LENGTH(TRIM(mom.mom))-LENGTH(REPLACE(TRIM(mom.mom),' ',''))+1)>=12 THEN 1 ELSE 0.5 END))";
        $sql = "SELECT bd_id, bd_name, COUNT(*) AS meetings,
                       ROUND(AVG(score),3) AS avg_score,
                       SUM(score>=0.70) AS quality_meetings,
                       SUM(score<0.70)  AS junk_meetings,
                       (SUM(score<0.70)>=3) AS dq8_risk
                FROM (
                    SELECT meet.user_id AS bd_id, u.name AS bd_name, $score AS score
                    FROM tblcallevents meet
                    LEFT JOIN init_call ic ON ic.id = meet.cid_id
                    LEFT JOIN tblcallevents mom ON mom.aftertask = meet.id AND mom.actiontype_id = 6
                    LEFT JOIN user_details u ON u.user_id = meet.user_id
                    WHERE $where
                ) q
                GROUP BY bd_id, bd_name
                ORDER BY junk_meetings DESC, avg_score ASC";
        $rows = $this->db->query($sql, $params)->result_array();
        $tot = ['meetings'=>0,'quality_meetings'=>0,'junk_meetings'=>0];
        foreach ($rows as $r) { $tot['meetings']+=$r['meetings']; $tot['quality_meetings']+=$r['quality_meetings']; $tot['junk_meetings']+=$r['junk_meetings']; }
        return ['gate'=>'A_meeting_quality','range'=>[$from,$to],'totals'=>$tot,'by_bd'=>$rows];
    }

    // mtm_committed_not_sent -> Gate B. MoM (actiontype_id=6) -> proposal within SLA days.
    public function mtm_committed_not_sent($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sla = isset($f['sla_days']) ? (int)$f['sla_days'] : 5;
        $limit = isset($f['limit']) ? (int)$f['limit'] : 200;
        $where = "mom.actiontype_id = 6 AND CAST(mom.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['bd_id'])) { $where .= " AND mom.user_id = ?"; $params[] = (int)$f['bd_id']; }
        $sql = "SELECT bd_id, bd_name, lead_id, company, committed_date, sent_date,
                       DATEDIFF(COALESCE(sent_date, CURDATE()), committed_date) AS days_elapsed,
                       $sla AS sla_days,
                       CASE
                         WHEN sent_date IS NOT NULL AND DATEDIFF(sent_date, committed_date) <= $sla THEN 'SENT'
                         WHEN sent_date IS NOT NULL THEN 'SENT_LATE'
                         WHEN DATEDIFF(CURDATE(), committed_date) > $sla THEN 'BREACH'
                         WHEN DATEDIFF(CURDATE(), committed_date) >= 3 THEN 'WARN'
                         ELSE 'OK'
                       END AS status
                FROM (
                    SELECT mom.user_id AS bd_id, u.name AS bd_name, cm.id AS lead_id,
                           cm.compname AS company, CAST(mom.appointmentdatetime AS DATE) AS committed_date,
                           (SELECT MIN(CAST(p.sdatet AS DATE)) FROM proposal p
                              WHERE p.init_id = mom.cid_id
                                AND CAST(p.sdatet AS DATE) >= CAST(mom.appointmentdatetime AS DATE)) AS sent_date
                    FROM tblcallevents mom
                    LEFT JOIN init_call ic ON ic.id = mom.cid_id
                    LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                    LEFT JOIN user_details u ON u.user_id = mom.user_id
                    WHERE $where
                ) q
                ORDER BY (sent_date IS NULL) DESC, days_elapsed DESC
                LIMIT $limit";
        $rows = $this->db->query($sql, $params)->result_array();
        $sum = ['sent'=>0,'sent_late'=>0,'warn'=>0,'breach'=>0,'ok'=>0];
        foreach ($rows as $r) { $k=strtolower($r['status']); if(isset($sum[$k])) $sum[$k]++; }
        return ['gate'=>'B_proposal_sla','range'=>[$from,$to],'sla_days'=>$sla,'summary'=>$sum,'leads'=>$rows];
    }

    // mtm_manager_adherence -> Gate C. Post-proposal live leads + manager touch/idle.
    public function mtm_manager_adherence($f = []) {
        $idleFlag = isset($f['week_days']) ? (int)$f['week_days'] : 7;
        $limit = isset($f['limit']) ? (int)$f['limit'] : 300;
        // post-proposal & live: has a proposal, status in positive/closure pipeline
        $where = "p.id IS NOT NULL AND ic.cstatus IN (6,7,9,12,13)";
        $params = [];
        if (!empty($f['manager_id'])) { $where .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        $sql = "SELECT ic.clm_id AS manager_id, cm.id AS lead_id, cm.compname AS company,
                       s.name AS status,
                       CAST(MAX(t.appointmentdatetime) AS DATE) AS last_touch,
                       DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) AS idle_days,
                       (MAX(t.appointmentdatetime) IS NULL OR DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) > $idleFlag) AS no_weekly_touch
                FROM init_call ic
                JOIN (SELECT DISTINCT init_id FROM proposal) p ON p.init_id = ic.id
                LEFT JOIN tblcallevents t ON t.cid_id = ic.id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN status s ON s.id = ic.cstatus
                WHERE $where
                GROUP BY ic.id
                ORDER BY no_weekly_touch DESC, idle_days DESC
                LIMIT $limit";
        $rows = $this->db->query($sql, $params)->result_array();
        $sum = ['post_proposal_leads'=>count($rows), 'no_weekly_touch'=>0];
        foreach ($rows as $r) { if ($r['no_weekly_touch']) $sum['no_weekly_touch']++; }
        return ['gate'=>'C_manager_closure','week_days'=>$idleFlag,'summary'=>$sum,'leads'=>$rows];
    }

    // mtm_closure_scorecard -> monthly manager efficiency KPIs.
    public function mtm_closure_scorecard($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $sla = isset($f['sla_days']) ? (int)$f['sla_days'] : 5;
        $sql = "SELECT ic.clm_id AS manager_id, mgr.name AS manager_name,
                       COUNT(DISTINCT ic.id) AS proposals_owned,
                       COUNT(DISTINCT CASE WHEN psent.gap IS NOT NULL AND psent.gap <= $sla THEN ic.id END) AS sent_on_time,
                       COUNT(DISTINCT CASE WHEN ic.cstatus IN (7,14) THEN ic.id END) AS won
                FROM init_call ic
                JOIN (SELECT DISTINCT init_id FROM proposal) p ON p.init_id = ic.id
                LEFT JOIN user_details mgr ON mgr.user_id = ic.clm_id
                LEFT JOIN (
                    SELECT mom.cid_id,
                           DATEDIFF((SELECT MIN(CAST(pr.sdatet AS DATE)) FROM proposal pr
                                       WHERE pr.init_id = mom.cid_id
                                         AND CAST(pr.sdatet AS DATE) >= CAST(mom.appointmentdatetime AS DATE)),
                                    CAST(mom.appointmentdatetime AS DATE)) AS gap
                    FROM tblcallevents mom WHERE mom.actiontype_id = 6
                ) psent ON psent.cid_id = ic.id
                WHERE CAST(ic.id AS UNSIGNED) > 0
                GROUP BY ic.clm_id
                ORDER BY proposals_owned DESC
                LIMIT 200";
        $rows = $this->db->query($sql)->result_array();
        foreach ($rows as &$r) {
            $owned = max(1, (int)$r['proposals_owned']);
            $r['on_time_pct'] = round(100 * $r['sent_on_time'] / $owned, 1);
            $r['win_pct']     = round(100 * $r['won'] / $owned, 1);
        }
        unset($r);
        return ['scorecard'=>'closure_efficiency','sla_days'=>$sla,'by_manager'=>$rows,
                'targets'=>['on_time_pct'=>90,'win_trend'=>'up','cycle_time'=>'down']];
    }

    // mtm_coordinator -> runs all 3 gates + scorecard and returns the combined leak pack.
    public function mtm_coordinator($f = []) {
        $a = $this->mtm_meeting_quality($f);
        $b = $this->mtm_committed_not_sent($f);
        $c = $this->mtm_manager_adherence($f);
        return [
            'meeting_to_money_assurance' => true,
            'range' => isset($a['range']) ? $a['range'] : null,
            'gate_a_meeting_quality' => [
                'meetings' => $a['totals']['meetings'],
                'quality'  => $a['totals']['quality_meetings'],
                'junk'     => $a['totals']['junk_meetings'],
            ],
            'gate_b_proposal_sla' => [
                'breach' => $b['summary']['breach'],
                'warn'   => $b['summary']['warn'],
                'sent'   => $b['summary']['sent'] + $b['summary']['sent_late'],
            ],
            'gate_c_manager_closure' => [
                'post_proposal_leads' => $c['summary']['post_proposal_leads'],
                'no_weekly_touch'     => $c['summary']['no_weekly_touch'],
            ],
            'one_line_test' => 'Right person met -> funded prospect -> proposal out on time -> manager driving a dated next step.',
        ];
    }


    // ========================================================================
    // DAILY HUDDLE + QUARTER-END SPRINT modules.
    // ========================================================================

    // daily_huddle_pack -> the SC scoreboard + per-BD pre-call readiness for a date.
    public function daily_huddle_pack($f = []) {
        $date = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $yest = date('Y-m-d', strtotime($date . ' -1 day'));
        $sql = "SELECT u.user_id AS bd_id, u.name AS bd_name,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND t.mtype IS NOT NULL AND t.mtype<>'' AND CAST(t.appointmentdatetime AS DATE)=?) AS meetings_today,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND UPPER(t.mtype) IN ('RP','RPCLOSE','CHANGE RP') AND CAST(t.appointmentdatetime AS DATE)=?) AS rp_today,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND (t.approved_status=0 OR t.approved_status IS NULL) AND CAST(t.appointmentdatetime AS DATE) < ?) AS pending_over_24h,
            ((SELECT COUNT(*) FROM task_plan_for_today p WHERE p.user_id=u.user_id AND CAST(p.date AS DATE)=?) > 0) AS planned_today,
            ((SELECT COUNT(*) FROM user_day d WHERE d.user_id=u.user_id AND CAST(d.ustart AS DATE)=?) > 0) AS day_started,
            (SELECT ROUND(AVG(ssr.star),1) FROM sales_star_rating ssr WHERE ssr.user_id=u.user_id AND CAST(ssr.date AS DATE)=?) AS star_yesterday
            FROM user_details u
            WHERE u.status='active' AND u.type_id=3";
        $params = [$date, $date, $date, $date, $date, $yest];
        if (!empty($f['bd_id']))      { $sql .= " AND u.user_id = ?"; $params[] = (int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $sql .= " AND u.user_id IN (SELECT DISTINCT mainbd FROM init_call WHERE clm_id = ? OR acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        $sql .= " ORDER BY u.name ASC";
        $rows = $this->db->query($sql, $params)->result_array();
        $board = ['bds'=>count($rows),'meetings_today'=>0,'rp_today'=>0,'not_planned'=>0,'pending_over_24h'=>0];
        foreach ($rows as $r) {
            $board['meetings_today'] += $r['meetings_today'];
            $board['rp_today']       += $r['rp_today'];
            $board['pending_over_24h']+= $r['pending_over_24h'];
            if (!$r['planned_today']) $board['not_planned']++;
        }
        return ['date'=>$date,'scoreboard'=>$board,'by_bd'=>$rows];
    }

    // sprint_closure_churn -> Quarter-End Sprint, Closure Arm: live-deal churn list, ranked.
    public function sprint_closure_churn($f = []) {
        $limit = isset($f['limit']) ? (int)$f['limit'] : 200;
        $where = "ic.cstatus IN (6,7,9,12,13)";
        $params = [];
        if (!empty($f['manager_id'])) { $where .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        if (!empty($f['bd_id']))      { $where .= " AND ic.mainbd = ?"; $params[]=(int)$f['bd_id']; }
        $sql = "SELECT cm.id AS lead_id, cm.compname AS company, ic.mainbd AS bd_id, u.name AS bd_name,
                       s.name AS status, ic.cstatus,
                       ((SELECT COUNT(*) FROM proposal p WHERE p.init_id=ic.id) > 0) AS has_proposal,
                       CAST(MAX(t.appointmentdatetime) AS DATE) AS last_activity,
                       DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) AS idle_days,
                       (CASE ic.cstatus WHEN 7 THEN 5 WHEN 9 THEN 4 WHEN 13 THEN 4 WHEN 6 THEN 3 WHEN 12 THEN 3 ELSE 1 END
                        + ((SELECT COUNT(*) FROM proposal p WHERE p.init_id=ic.id) > 0) * 2) AS closeability,
                       (DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) > 7 OR MAX(t.appointmentdatetime) IS NULL) AS awaiting_verdict
                FROM init_call ic
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u ON u.user_id = ic.mainbd
                LEFT JOIN status s ON s.id = ic.cstatus
                LEFT JOIN tblcallevents t ON t.cid_id = ic.id
                WHERE $where
                GROUP BY ic.id
                ORDER BY closeability DESC, idle_days DESC
                LIMIT $limit";
        $rows = $this->db->query($sql, $params)->result_array();
        $sum = ['live_deals'=>count($rows),'with_proposal'=>0,'awaiting_verdict'=>0];
        foreach ($rows as $r) { if ($r['has_proposal']) $sum['with_proposal']++; if ($r['awaiting_verdict']) $sum['awaiting_verdict']++; }
        return ['arm'=>'closure','summary'=>$sum,'churn_list'=>$rows];
    }

    // sprint_cluster_plan -> Quarter-End Sprint, Cluster Arm: open pipeline by geography/cluster.
    public function sprint_cluster_plan($f = []) {
        $where = "ic.cstatus IN (1,2,3)";
        $params = [];
        if (!empty($f['manager_id'])) { $where .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        $byDistrict = $this->db->query(
            "SELECT cm.state, cm.district, COUNT(*) AS open_leads
             FROM init_call ic LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
             WHERE $where AND cm.district IS NOT NULL AND cm.district <> ''
             GROUP BY cm.state, cm.district ORDER BY open_leads DESC LIMIT 30", $params)->result_array();
        $byCluster = $this->db->query(
            "SELECT cl.id AS cluster_id, cl.clustername, cl.travelType, COUNT(*) AS open_leads
             FROM init_call ic LEFT JOIN cluster cl ON cl.id = ic.cluster_id
             WHERE $where AND ic.cluster_id IS NOT NULL AND ic.cluster_id <> 0
             GROUP BY ic.cluster_id ORDER BY open_leads DESC LIMIT 20", $params)->result_array();
        $unset = $this->db->query(
            "SELECT COUNT(*) AS open_leads_no_cluster
             FROM init_call ic WHERE $where AND (ic.cluster_id IS NULL OR ic.cluster_id = 0)", $params)->row_array();
        return ['arm'=>'cluster','open_pipeline_by_district'=>$byDistrict,'top_travel_clusters'=>$byCluster,'unclustered'=>$unset['open_leads_no_cluster']];
    }


    // ========================================================================
    // PERFORMANCE DASHBOARD module (per-BD scorecard + 4-category funnel).
    // ========================================================================

    // bd_performance_scorecard -> per-BD: meetings, RP, proposals (sent/approved/
    // pending/value), closures. Mirrors the Performance Dashboard "Summary" block.
    public function bd_performance_scorecard($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $onDate = !empty($f['rp_on_date']) ? $f['rp_on_date'] : date('Y-m-d');
        $sql = "SELECT u.user_id AS bd_id, u.name AS bd_name,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND t.mtype IS NOT NULL AND t.mtype<>'' AND t.appointmentdatetime BETWEEN ? AND ?) AS total_meetings,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND UPPER(t.mtype) IN ('RP','RPCLOSE','CHANGE RP') AND t.appointmentdatetime BETWEEN ? AND ?) AS rp_meetings,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND (UPPER(t.mtype)='NORP' OR UPPER(t.mtype)='NO RP') AND t.appointmentdatetime BETWEEN ? AND ?) AS norp_meetings,
            (SELECT COUNT(*) FROM tblcallevents t WHERE t.user_id=u.user_id AND UPPER(t.mtype) IN ('RP','RPCLOSE','CHANGE RP') AND CAST(t.appointmentdatetime AS DATE)=?) AS rp_on_date,
            (SELECT COUNT(*) FROM proposal p WHERE p.user_id=u.user_id AND p.sdatet BETWEEN ? AND ?) AS proposals_sent,
            (SELECT COUNT(*) FROM proposal p WHERE p.user_id=u.user_id AND p.apr=1 AND p.sdatet BETWEEN ? AND ?) AS proposals_approved,
            (SELECT COUNT(*) FROM proposal p WHERE p.user_id=u.user_id AND (p.apr=0 OR p.apr IS NULL) AND p.sdatet BETWEEN ? AND ?) AS proposals_pending,
            (SELECT COALESCE(SUM(p.pbudgetme),0) FROM proposal p WHERE p.user_id=u.user_id AND p.apr=1 AND p.sdatet BETWEEN ? AND ?) AS approved_value,
            (SELECT COUNT(DISTINCT ic.id) FROM init_call ic WHERE ic.mainbd=u.user_id AND ic.cstatus IN (7,14)) AS closure_done
            FROM user_details u
            WHERE u.status='active' AND u.type_id=3";
        $params = [$from,$to, $from,$to, $from,$to, $onDate, $from,$to, $from,$to, $from,$to, $from,$to];
        if (!empty($f['bd_id']))      { $sql .= " AND u.user_id = ?"; $params[] = (int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $sql .= " AND u.user_id IN (SELECT DISTINCT mainbd FROM init_call WHERE clm_id = ? OR acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        $sql .= " ORDER BY rp_meetings DESC, u.name ASC";
        $rows = $this->db->query($sql, $params)->result_array();
        $tot = ['bds'=>count($rows),'total_meetings'=>0,'rp_meetings'=>0,'norp_meetings'=>0,'proposals_sent'=>0,'proposals_approved'=>0,'proposals_pending'=>0,'approved_value'=>0,'closure_done'=>0];
        foreach ($rows as $r) foreach (['total_meetings','rp_meetings','norp_meetings','proposals_sent','proposals_approved','proposals_pending','approved_value','closure_done'] as $k) $tot[$k]+=$r[$k];
        return ['date_from'=>$from,'date_to'=>$to,'totals'=>$tot,'by_bd'=>$rows,
                '_note'=>'approved_value = SUM(proposal.pbudgetme WHERE apr=1) [grounded from ProposalDetails]. Per-school counts not included: proposal school-count column unconfirmed.'];
    }

    // bd_category_funnel -> per-BD 4-category funnel (Q1 Closure / To-Be-Nurture /
    // Anchor / Upsell) x RP-done/pending + Proposal-done/pending. Mirrors the
    // Performance Dashboard "BD_4 Categories" sheet. Funnel flags vs FY start year.
    public function bd_category_funnel($f = []) {
        $fq = $this->_financialQuarter();
        $cfy = (string)$fq['fy'];
        $cats = [
            'q1_closure'    => "ic.q1_twetenty_closure_funnel = '$cfy'",
            'to_be_nurture' => "ic.to_be_nurtured_for_fy = '$cfy'",
            'anchor_client' => "ic.anchor_clients = 'yes'",
            'upsell_client' => "ic.upsell_client = 'yes'",
        ];
        $params = [];
        $scope = "u.status='active' AND u.type_id=3";
        if (!empty($f['bd_id']))      { $scope .= " AND u.user_id = ?"; $params[]=(int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $scope .= " AND u.user_id IN (SELECT DISTINCT mainbd FROM init_call WHERE clm_id = ? OR acm_co_id = ?)"; $params[]=(int)$f['manager_id']; $params[]=(int)$f['manager_id']; }
        // build per-category conditional aggregates over leads owned by each BD
        $sel = ["u.user_id AS bd_id", "u.name AS bd_name"];
        foreach ($cats as $key => $cond) {
            $rp  = "EXISTS (SELECT 1 FROM tblcallevents t WHERE t.cid_id=ic.id AND UPPER(t.mtype) IN ('RP','RPCLOSE','CHANGE RP'))";
            $pr  = "EXISTS (SELECT 1 FROM proposal p WHERE p.init_id=ic.id)";
            $sel[] = "SUM(CASE WHEN $cond THEN 1 ELSE 0 END) AS {$key}_leads";
            $sel[] = "SUM(CASE WHEN ($cond) AND $rp THEN 1 ELSE 0 END) AS {$key}_rp_done";
            $sel[] = "SUM(CASE WHEN ($cond) AND NOT $rp THEN 1 ELSE 0 END) AS {$key}_rp_pending";
            $sel[] = "SUM(CASE WHEN ($cond) AND $pr THEN 1 ELSE 0 END) AS {$key}_proposal_done";
            $sel[] = "SUM(CASE WHEN ($cond) AND NOT $pr THEN 1 ELSE 0 END) AS {$key}_proposal_pending";
        }
        $sql = "SELECT " . implode(",\n            ", $sel) . "
            FROM user_details u
            LEFT JOIN init_call ic ON ic.mainbd = u.user_id
            WHERE $scope
            GROUP BY u.user_id, u.name
            HAVING (q1_closure_leads + to_be_nurture_leads + anchor_client_leads + upsell_client_leads) > 0
            ORDER BY u.name ASC";
        $rows = $this->db->query($sql, $params)->result_array();
        return ['financial_year_start'=>$cfy,'categories'=>array_keys($cats),'by_bd'=>$rows];
    }


    // ========================================================================
    // GROUNDED REPORT TOOLS — ported faithfully from stemapp.in/Reports SQL.
    // Schema fact: company_master.id = lead id (cid); leads join via
    // init_call.cmpid_id = company_master.id. Proposal value = proposal.pbudgetme.
    // ========================================================================

    // _scopeUsers -> reproduces the report "$text" reports-to scoping (type_id
    // switch) as a superset: a user is in scope if manager_id appears in ANY of
    // their coordinator columns, or it is the user themself. alias = user_details alias.
    private function _scopeUsers($alias, $managerId) {
        if (empty($managerId) || $managerId === 'all') return "1=1";
        $m = (int)$managerId;
        // Resolve the manager's role, then scope by the exact coordinator column
        // the CRM uses for that role (port of the get_userbyid + type_id switch).
        $u = $this->db->query("SELECT type_id FROM user_details WHERE user_id = ? LIMIT 1", [$m])->row_array();
        $t = $u ? (int)$u['type_id'] : 0;
        $colMap = [
            1  => 'sadmin_id',   // SuperAdmin
            2  => 'admin_id',    // Admin
            4  => 'pst_co',      // PST
            13 => 'aadmin',      // Cluster Manager
            15 => 'sales_co',    // Sales Coordinator
            19 => 'ash_nae_co',  // Assistant Sales Head (NAE)
            20 => 'ash_w_co',    // Assistant Sales Head (W)
            21 => 'ash_s_co',    // Assistant Sales Head (S)
            22 => 'rm_east_co',  // Regional Manager
            23 => 'rm_north_co', // RM North
            24 => 'acm_co',      // Assistant Cluster Manager (ACM)
        ];
        // type 3 (Sales Person/BD) and any unmapped role -> the user themself
        if (isset($colMap[$t])) return "$alias." . $colMap[$t] . " = $m";
        return "$alias.user_id = $m";
    }

    // user_attendance -> present / WFO / WF-field / both, for a date. Port of
    // GetTodaysAllUserByReportingManager (userworkfrom.ID 1=Office,2=Field,3=Both).
    public function user_attendance($f = []) {
        $date = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $scope = $this->_scopeUsers('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $sql = "SELECT u1.user_id, u1.name, user_type.name AS type_name,
                       uwf.TYPE AS start_from, uwf.ID AS wffo_id,
                       user_day.sdatet AS start_date, user_day.uclose AS uclose_date,
                       user_day.slatitude, user_day.slongitude, user_day.clatitude, user_day.clongitude
                FROM user_details u1
                LEFT JOIN user_type ON user_type.id = u1.type_id
                LEFT JOIN user_day ON u1.user_id = user_day.user_id AND CAST(user_day.ustart AS DATE) = ?
                LEFT JOIN userworkfrom uwf ON uwf.ID = user_day.wffo
                WHERE u1.status='active' AND $scope AND user_day.id IS NOT NULL";
        if (!empty($f['mode'])) {
            $map = ['office'=>1,'field'=>2,'both'=>3];
            if (isset($map[strtolower($f['mode'])])) $sql .= " AND uwf.ID = " . $map[strtolower($f['mode'])];
        }
        $sql .= " GROUP BY u1.user_id ORDER BY u1.user_id DESC";
        $rows = $this->db->query($sql, [$date])->result_array();
        $sum = ['present'=>count($rows),'work_from_office'=>0,'work_from_field'=>0,'field_and_office'=>0];
        foreach ($rows as $r) {
            if ($r['wffo_id']==1) $sum['work_from_office']++;
            elseif ($r['wffo_id']==2) $sum['work_from_field']++;
            elseif ($r['wffo_id']==3) $sum['field_and_office']++;
        }
        return ['date'=>$date,'summary'=>$sum,'users'=>$rows];
    }

    // planner_live_board -> planner-live summary counts for a working date (cdate)
    // and the planner target date (planner_date). Port of DayendManagementCheckingDatas.
    public function planner_live_board($f = []) {
        $cdate = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $pdate = !empty($f['planner_date']) ? $f['planner_date'] : $cdate;
        $scope = $this->_scopeUsers('ud_scope', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $scopeU1 = str_replace('ud_scope.', 'u1.', $scope);
        $sql = "SELECT
            (SELECT COUNT(*) FROM user_details ud_scope WHERE $scope AND ud_scope.status='active') AS total_user,
            COUNT(DISTINCT CASE WHEN ud.id IS NOT NULL THEN u1.user_id END) AS present_user,
            COUNT(DISTINCT CASE WHEN ud.id IS NOT NULL AND ud.uclose IS NULL THEN u1.user_id END) AS present_not_closed,
            COUNT(DISTINCT CASE WHEN ud.id IS NOT NULL AND ud.uclose IS NOT NULL THEN u1.user_id END) AS present_closed,
            ((SELECT COUNT(*) FROM user_details ud_scope WHERE $scope AND ud_scope.status='active')
              - COUNT(DISTINCT CASE WHEN ud.id IS NOT NULL THEN u1.user_id END)) AS absent_user,
            COUNT(DISTINCT CASE WHEN aut.id IS NOT NULL THEN aut.user_id END) AS planning_set,
            COUNT(DISTINCT CASE WHEN aut.id IS NULL THEN u1.user_id END) AS planner_not_set
            FROM (SELECT * FROM user_details WHERE status='active' AND $scopeU1) u1
            LEFT JOIN autotask_time aut ON aut.user_id = u1.user_id AND DATE(aut.date) = ?
            LEFT JOIN user_day ud ON ud.user_id = u1.user_id AND DATE(ud.ustart) = ?";
        $row = $this->db->query($sql, [$pdate, $cdate])->row_array();
        return ['date'=>$cdate,'planner_date'=>$pdate,'board'=>$row];
    }

    // day_ceremony_live -> per-user day start/close status for a date (DayStartLive /
    // DayEndLive board): started, closed, not-closed, with GPS + work-from.
    public function day_ceremony_live($f = []) {
        $date = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $scope = $this->_scopeUsers('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $sql = "SELECT u1.user_id, u1.name, ut.name AS user_role,
                       uwf.TYPE AS work_from, ud.ustart AS day_start, ud.uclose AS day_close,
                       ud.slatitude, ud.slongitude, ud.clatitude, ud.clongitude,
                       CASE WHEN ud.ustart IS NOT NULL THEN 'Yes' ELSE 'No' END AS started,
                       CASE WHEN ud.uclose IS NOT NULL THEN 'Yes' ELSE 'No' END AS closed
                FROM user_details u1
                LEFT JOIN user_type ut ON ut.id = u1.type_id
                LEFT JOIN user_day ud ON ud.user_id = u1.user_id AND DATE(ud.ustart) = ?
                LEFT JOIN userworkfrom uwf ON uwf.ID = ud.wffo
                WHERE u1.status='active' AND $scope AND ud.id IS NOT NULL
                GROUP BY u1.user_id ORDER BY ud.uclose IS NULL DESC, u1.name";
        $rows = $this->db->query($sql, [$date])->result_array();
        $sum = ['present'=>count($rows),'day_started'=>0,'day_closed'=>0,'started_not_closed'=>0];
        foreach ($rows as $r) {
            if ($r['started']=='Yes') $sum['day_started']++;
            if ($r['closed']=='Yes') $sum['day_closed']++;
            if ($r['started']=='Yes' && $r['closed']=='No') $sum['started_not_closed']++;
        }
        return ['date'=>$date,'summary'=>$sum,'users'=>$rows];
    }

    // calling_outcome_rp_proposal -> per-lead (cid) RP-to-proposal conversion:
    // meetings, proposals shared, planned vs connected follow-up calls after the
    // first RP. Port of RMCMCallingOutcomeANDRPProposalConversionDatas.
    public function calling_outcome_rp_proposal($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $scope = $this->_scopeUsers('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $bd = !empty($f['bd_id']) ? " AND ic.mainbd = " . (int)$f['bd_id'] : "";
        $sql = "SELECT cm.id AS cid, cm.compname, s1.name AS current_status, u1.name AS mainbd_name,
                       COUNT(DISTINCT ce.id) AS total_meeting,
                       COUNT(DISTINCT p.id) AS proposal_shared,
                       COUNT(DISTINCT CASE WHEN UPPER(ce.mtype) IN ('RP','RPCLOSE','CHANGE RP') THEN ce.id END) AS rp_meetings,
                       COUNT(DISTINCT CASE WHEN ce.actontaken='yes' AND ce.purpose_achieved='yes' THEN ce.id END) AS calls_connected,
                       COUNT(DISTINCT CASE WHEN ce.purpose_achieved='yes' THEN ce.id END) AS purpose_achieved_meetings
                FROM init_call ic
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u1 ON u1.user_id = ic.mainbd
                LEFT JOIN status s1 ON s1.id = ic.cstatus
                LEFT JOIN tblcallevents ce ON ce.cid_id = ic.id AND ce.appointmentdatetime BETWEEN ? AND ?
                LEFT JOIN proposal p ON p.init_id = ic.id
                WHERE $scope $bd
                GROUP BY cm.id
                HAVING total_meeting > 0 OR proposal_shared > 0
                ORDER BY proposal_shared DESC, total_meeting DESC";
        $rows = $this->db->query($sql, [$from, $to])->result_array();
        $sum = ['leads'=>count($rows),'total_meetings'=>0,'proposals_shared'=>0,'rp_meetings'=>0,'calls_connected'=>0];
        foreach ($rows as $r) { $sum['total_meetings']+=$r['total_meeting']; $sum['proposals_shared']+=$r['proposal_shared']; $sum['rp_meetings']+=$r['rp_meetings']; $sum['calls_connected']+=$r['calls_connected']; }
        $sum['rp_to_proposal_pct'] = $sum['rp_meetings'] ? round($sum['proposals_shared']*100.0/$sum['rp_meetings'],1) : 0;
        return ['date_from'=>$from,'date_to'=>$to,'summary'=>$sum,'by_lead'=>$rows];
    }

    // closure_pipeline_overview -> full closure-pipeline rollup (pending-for-RP,
    // RP complete, proposal sent/pending, closure, direct closure, closure after
    // proposal, ₹ budgets). Port of getAllCompanyBYRollesMaiingClosurePipeLine.
    public function closure_pipeline_overview($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $scope = $this->_scopeUsers('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $bd = !empty($f['bd_id']) ? " AND init_call.mainbd = " . (int)$f['bd_id'] : "";
        $sql = "SELECT
            COUNT(DISTINCT init_call.id) AS total_funnel_leads,
            COUNT(DISTINCT CASE WHEN tblcm.id IS NULL THEN init_call.id END) AS pending_for_rp_meeting,
            COUNT(DISTINCT CASE WHEN tblcm.id IS NOT NULL THEN init_call.id END) AS rp_meeting_done,
            COUNT(DISTINCT CASE WHEN tblcm.id IS NOT NULL AND p1.id IS NOT NULL THEN init_call.id END) AS proposal_sent,
            COUNT(DISTINCT CASE WHEN tblcm.id IS NOT NULL AND p1.id IS NULL THEN init_call.id END) AS pending_for_proposal_sent,
            COUNT(DISTINCT CASE WHEN tblcm.id IS NULL AND p1.id IS NOT NULL THEN init_call.id END) AS proposal_sent_without_meeting,
            COUNT(DISTINCT CASE WHEN init_call.cstatus IN (7,14) THEN init_call.id END) AS total_closure,
            COUNT(DISTINCT CASE WHEN init_call.cstatus IN (7,14) AND p1.id IS NULL THEN init_call.id END) AS direct_closure_without_proposal,
            COUNT(DISTINCT CASE WHEN p1.id IS NOT NULL AND init_call.cstatus IN (7,14) THEN init_call.id END) AS closure_after_proposal,
            COUNT(DISTINCT CASE WHEN p1.id IS NOT NULL AND init_call.cstatus NOT IN (7,14) THEN init_call.id END) AS not_closure_after_proposal,
            COUNT(DISTINCT CASE WHEN p1.id IS NOT NULL AND init_call.cstatus IN (4,5) THEN init_call.id END) AS wdl_or_ni_after_proposal,
            SUM(DISTINCT CASE WHEN p1.id IS NOT NULL THEN p1.pbudgetme END) AS budget_proposal_sent,
            SUM(DISTINCT CASE WHEN p1.id IS NOT NULL AND init_call.cstatus IN (7,14) THEN p1.pbudgetme END) AS budget_closure,
            SUM(DISTINCT CASE WHEN p1.id IS NOT NULL AND init_call.cstatus NOT IN (7,14) THEN p1.pbudgetme END) AS budget_not_closure
            FROM init_call
            LEFT JOIN user_details u1 ON u1.user_id = init_call.mainbd
            LEFT JOIN tblcallevents tblcm ON tblcm.cid_id = init_call.id AND UPPER(tblcm.mtype) IN ('RP','RPCLOSE','CHANGE RP')
            LEFT JOIN (SELECT id, init_id, pbudgetme, user_id FROM proposal) p1 ON p1.init_id = init_call.id
            WHERE $scope $bd AND CAST(init_call.createDate AS DATE) BETWEEN ? AND ?";
        $row = $this->db->query($sql, [$from, $to])->row_array();
        return ['date_from'=>$from,'date_to'=>$to,'closure_pipeline'=>$row];
    }

    // travel_cluster_meeting -> per travel cluster: leads, base/outStation, planned/
    // complete/RP/scheduled/barg/virtual meetings, proposal sent, closure. Port of
    // getAllCompanyBYRollesMaiingBYMeeting (actiontype 3=scheduled,4=barg,22=virtual).
    public function travel_cluster_meeting($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $scope = $this->_scopeUsers('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all');
        $bd = !empty($f['bd_id']) ? " AND ic.mainbd = " . (int)$f['bd_id'] : "";
        $sql = "SELECT cl.id AS cluster_id, cl.clustername, cl.travelType,
                       COUNT(DISTINCT ic.id) AS total_leads,
                       COUNT(DISTINCT CASE WHEN cl.travelType='base' THEN ic.id END) AS base_location,
                       COUNT(DISTINCT CASE WHEN cl.travelType='outStation' THEN ic.id END) AS outstation_location,
                       COUNT(DISTINCT CASE WHEN ce.id IS NOT NULL THEN ic.id END) AS planned_meeting,
                       COUNT(DISTINCT CASE WHEN ce.id IS NOT NULL AND ce.nextCFID != 0 THEN ic.id END) AS complete_meeting,
                       COUNT(DISTINCT CASE WHEN ce.id IS NULL THEN ic.id END) AS pending_for_meeting,
                       COUNT(DISTINCT CASE WHEN UPPER(ce.mtype) IN ('RP','RPCLOSE','CHANGE RP') THEN ic.id END) AS rp_complete_meeting,
                       COUNT(DISTINCT CASE WHEN ce.actiontype_id = 3 THEN ic.id END) AS scheduled_meeting,
                       COUNT(DISTINCT CASE WHEN ce.actiontype_id = 4 THEN ic.id END) AS barg_in_meeting,
                       COUNT(DISTINCT CASE WHEN ce.actiontype_id = 22 THEN ic.id END) AS virtual_meeting,
                       COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND p.apr IN (0,1) THEN ic.id END) AS proposal_sent,
                       COUNT(DISTINCT CASE WHEN ic.cstatus IN (7,14) THEN ic.id END) AS closure
                FROM init_call ic
                LEFT JOIN user_details u1 ON u1.user_id = ic.mainbd
                LEFT JOIN cluster cl ON cl.id = ic.cluster_id
                LEFT JOIN tblcallevents ce ON ce.cid_id = ic.id AND ce.appointmentdatetime BETWEEN ? AND ?
                LEFT JOIN proposal p ON p.init_id = ic.id
                WHERE $scope $bd AND ic.cluster_id IS NOT NULL AND ic.cluster_id <> 0
                GROUP BY ic.cluster_id
                ORDER BY total_leads DESC";
        $rows = $this->db->query($sql, [$from, $to])->result_array();
        return ['date_from'=>$from,'date_to'=>$to,'by_cluster'=>$rows];
    }


    // ============================================================
    // v3.1 ADD-ONS — event-level drill-down (company_id/cid + actor + status change)
    // meeting_detail, repeat_company_summary, activity_log, lead_timeline
    // ============================================================

    private function _mtypeMap($v) {
        $map = ['rp'=>'rp','no_rp'=>'no rp','only_got_detail'=>'only got detail','change_rp'=>'change rp'];
        $k = strtolower((string)$v);
        return isset($map[$k]) ? $map[$k] : $k;
    }

    // One row per MEETING (actiontype 3/4/22) with cid, company, stage change + visit_seq/is_repeat.
    public function meeting_detail($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? max(1, min((int)$f['limit'], 8000)) : 2000;
        $sql = "SELECT t.id AS meeting_id,
                       CAST(t.appointmentdatetime AS DATE) AS event_date,
                       a.name AS action_type,
                       COALESCE(NULLIF(LOWER(t.mtype),''),'unspecified') AS mtype,
                       ic.mainbd AS bd_id, ub.name AS bd_name,
                       ic.id AS lead_id, cm.id AS company_id, cm.compname AS company_name,
                       cm.city, cm.state, t.purpose_achieved,
                       so.name AS old_stage, sn.name AS new_stage
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details ub ON ub.user_id = ic.mainbd
                LEFT JOIN action a ON a.id = t.actiontype_id
                LEFT JOIN status so ON so.id = t.status_id
                LEFT JOIN status sn ON sn.id = t.nstatus_id
                WHERE t.actiontype_id IN (3,4,22)
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (isset($f['mtype']) && $f['mtype'] !== '') { $sql .= " AND LOWER(t.mtype) = ?"; $params[] = $this->_mtypeMap($f['mtype']); }
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $sql .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[] = (int)$f['manager_id']; $params[] = (int)$f['manager_id']; }
        $sql .= " ORDER BY cm.id, t.appointmentdatetime LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        $seq = [];
        foreach ($rows as &$r) {
            $c = $r['company_id'];
            $seq[$c] = isset($seq[$c]) ? $seq[$c] + 1 : 1;
            $r['visit_seq'] = $seq[$c];
            $r['is_repeat'] = $seq[$c] > 1 ? 1 : 0;
        }
        unset($r);
        return ['range' => [$from, $to], 'count' => count($rows), 'meetings' => $rows];
    }

    // One row per COMPANY met >= min_meetings times (default mtype=rp). The repeat-company list, with cid.
    public function repeat_company_summary($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $min   = isset($f['min_meetings']) ? max(2, (int)$f['min_meetings']) : 2;
        $limit = isset($f['limit']) ? (int)$f['limit'] : 1000;
        $mt    = $this->_mtypeMap(isset($f['mtype']) && $f['mtype'] !== '' ? $f['mtype'] : 'rp');
        $sql = "SELECT cm.id AS company_id, cm.compname AS company_name,
                       ic.mainbd AS bd_id, u.name AS bd_name,
                       COUNT(*) AS rp_meeting_count,
                       MIN(CAST(t.appointmentdatetime AS DATE)) AS first_meeting_date,
                       MAX(CAST(t.appointmentdatetime AS DATE)) AS last_meeting_date,
                       s.name AS current_status
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u ON u.user_id = ic.mainbd
                LEFT JOIN status s ON s.id = ic.cstatus
                WHERE LOWER(t.mtype) = ?
                  AND CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$mt, $from, $to];
        if (!empty($f['bd_id']))      { $sql .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $sql .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[] = (int)$f['manager_id']; $params[] = (int)$f['manager_id']; }
        $sql .= " GROUP BY cm.id HAVING rp_meeting_count >= ? ORDER BY rp_meeting_count DESC LIMIT ?";
        $params[] = $min; $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        $repeat_visits = 0; foreach ($rows as $r) { $repeat_visits += ((int)$r['rp_meeting_count'] - 1); }
        return ['range' => [$from, $to], 'mtype' => $mt, 'min_meetings' => $min,
                'repeat_companies' => count($rows), 'repeat_visits' => $repeat_visits, 'companies' => $rows];
    }

    // RAW EVENT LOG — one row per tblcallevents record, attributed to the PERFORMER (BD or line manager).
    public function activity_log($f = []) {
        list($from, $to) = $this->_dateRange($f);
        $limit = isset($f['limit']) ? max(1, min((int)$f['limit'], 8000)) : 500;
        $sql = "SELECT t.id AS event_id, t.appointmentdatetime AS event_datetime,
                       cm.id AS company_id, cm.compname AS company_name, ic.id AS lead_id,
                       t.user_id AS user_id, u.name AS user_name, u.type_id AS user_role,
                       a.name AS action_type, t.actontaken AS result, t.purpose_achieved,
                       so.name AS old_stage, sn.name AS new_stage,
                       t.special_remarks AS remark, t.mom AS mom_text
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u ON u.user_id = t.user_id          -- PERFORMER, not lead owner
                LEFT JOIN action a ON a.id = t.actiontype_id
                LEFT JOIN status so ON so.id = t.status_id
                LEFT JOIN status sn ON sn.id = t.nstatus_id
                WHERE CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['company_id'])) { $sql .= " AND cm.id = ?";       $params[] = (int)$f['company_id']; }
        if (!empty($f['lead_id']))    { $sql .= " AND ic.id = ?";       $params[] = (int)$f['lead_id']; }
        if (!empty($f['bd_id']))      { $sql .= " AND t.user_id = ?";   $params[] = (int)$f['bd_id']; }
        if (!empty($f['manager_id'])) { $sql .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[] = (int)$f['manager_id']; $params[] = (int)$f['manager_id']; }
        if (!empty($f['action_type'])){ $sql .= " AND t.actiontype_id = ?"; $params[] = (int)$f['action_type']; }
        $sql .= " ORDER BY cm.id, t.appointmentdatetime LIMIT ?";
        $params[] = $limit;
        $rows = $this->db->query($sql, $params)->result_array();
        foreach ($rows as &$r) { $r['status_changed'] = (!empty($r['new_stage']) && $r['new_stage'] !== $r['old_stage']) ? 1 : 0; }
        unset($r);
        return ['range' => [$from, $to], 'count' => count($rows), 'events' => $rows];
    }

    // Chronological full history for ONE company/lead (same columns + manager_touch flag).
    public function lead_timeline($f = []) {
        if (empty($f['lead_id']) && empty($f['company_id'])) return ['__error' => 'lead_id or company_id required'];
        $from = !empty($f['date_from']) ? $f['date_from'] : '2000-01-01';
        $to   = !empty($f['date_to'])   ? $f['date_to'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        $sql = "SELECT t.id AS event_id, t.appointmentdatetime AS event_datetime,
                       cm.id AS company_id, cm.compname AS company_name, ic.id AS lead_id,
                       t.user_id AS user_id, u.name AS user_name, u.type_id AS user_role,
                       a.name AS action_type, t.actontaken AS result, t.purpose_achieved,
                       so.name AS old_stage, sn.name AS new_stage,
                       t.special_remarks AS remark, t.mom AS mom_text,
                       (t.user_id <> ic.mainbd) AS manager_touch
                FROM tblcallevents t
                JOIN init_call ic ON ic.id = t.cid_id
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u ON u.user_id = t.user_id
                LEFT JOIN action a ON a.id = t.actiontype_id
                LEFT JOIN status so ON so.id = t.status_id
                LEFT JOIN status sn ON sn.id = t.nstatus_id
                WHERE CAST(t.appointmentdatetime AS DATE) BETWEEN ? AND ?";
        $params = [$from, $to];
        if (!empty($f['lead_id']))    { $sql .= " AND ic.id = ?"; $params[] = (int)$f['lead_id']; }
        if (!empty($f['company_id'])) { $sql .= " AND cm.id = ?"; $params[] = (int)$f['company_id']; }
        $sql .= " ORDER BY t.appointmentdatetime";
        $rows = $this->db->query($sql, $params)->result_array();
        return ['range' => [$from, $to], 'count' => count($rows), 'timeline' => $rows];
    }

    // ============================================================
    // v3.2 ADD-ON — Planner re-plan log (port of GetAllTodaysPlannerLog)
    // Counts how many times each task was re-planned (duplicate_count),
    // with cid + company + to_user + status + time_difference, role-scoped
    // by the requesting user's type_id (same hierarchy columns as the CRM).
    // ============================================================
    public function planner_replan_log($f = []) {
        $uid = isset($f['user_id']) ? (int)$f['user_id'] : 0;
        if (!$uid) return ['__error' => 'user_id required'];
        $tdate = !empty($f['date']) ? $f['date'] : date('Y-m-d');
        $limit = isset($f['limit']) ? max(1, min((int)$f['limit'], 5000)) : 1000;

        // resolve requesting user's role (type_id)
        $u = $this->db->query("SELECT type_id FROM user_details WHERE user_id = ? LIMIT 1", [$uid])->row_array();
        $uyid = $u ? (int)$u['type_id'] : 0;

        // role -> visibility column (mirrors GetAllTodaysPlannerLog)
        $colMap = [13=>'aadmin',4=>'pst_co',15=>'sales_co',19=>'ash_nae_co',20=>'ash_w_co',
                   21=>'ash_s_co',22=>'rm_east_co',23=>'rm_north_co',24=>'acm_co',1=>'sadmin_id',2=>'admin_id'];
        if ($uyid == 3)              { $text = "u1.user_id = ?";                 $tp = [$uid]; }
        elseif (isset($colMap[$uyid])) { $c = $colMap[$uyid]; $text = "(u1.$c = ? OR u1.user_id = ?)"; $tp = [$uid, $uid]; }
        else                         { $text = "u1.admin_id = ?";               $tp = [$uid]; }
        // user_wise override: only the user's own re-plans
        $uw = isset($f['user_wise']) ? $f['user_wise'] : null;
        if ($uw === 'user_wise' || $uw === true || $uw === 1 || $uw === '1') { $text = "u1.user_id = ?"; $tp = [$uid]; }

        $sql = "SELECT
            plog.init_id,
            plog.task_id,
            company_master.id AS cid,
            company_master.compname AS company_name,
            plog.to_user,
            tbc.actiontype_id,
            action.name AS task_name,
            init_call.cstatus,
            init_call.mainbd,
            init_call.apst,
            init_call.clm_id,
            status.name AS current_status,
            MIN(plog.org_task_date) AS org_task_date,
            plog.remarks,
            ( SELECT IFNULL(MAX(plog1.re_created_at), plog.re_created_at)
              FROM planner_log plog1
              WHERE plog1.task_id = plog.task_id AND plog1.to_user = plog.to_user
                AND plog1.re_created_at > MIN(plog.re_created_at) ) AS last_create_date,
            ( SELECT COUNT(*) FROM planner_log plog3 WHERE plog3.task_id = plog.task_id ) AS duplicate_count,
            CONCAT(
              TIMESTAMPDIFF(DAY, MIN(plog.org_task_date),
                ( SELECT IFNULL(MAX(plog1.re_created_at), plog.re_created_at) FROM planner_log plog1
                  WHERE plog1.init_id = plog.init_id AND plog1.to_user = plog.to_user
                    AND plog1.re_created_at > MIN(plog.re_created_at) )), ' days ',
              MOD(TIMESTAMPDIFF(HOUR, MIN(plog.org_task_date),
                ( SELECT IFNULL(MAX(plog1.re_created_at), plog.re_created_at) FROM planner_log plog1
                  WHERE plog1.init_id = plog.init_id AND plog1.to_user = plog.to_user
                    AND plog1.re_created_at > MIN(plog.org_task_date) )), 24), ' hours ',
              MOD(TIMESTAMPDIFF(MINUTE, MIN(plog.org_task_date),
                ( SELECT IFNULL(MAX(plog1.re_created_at), plog.re_created_at) FROM planner_log plog1
                  WHERE plog1.init_id = plog.init_id AND plog1.to_user = plog.to_user
                    AND plog1.re_created_at > MIN(plog.org_task_date) )), 60), ' minutes'
            ) AS time_difference,
            u1.name AS to_user_name
            FROM planner_log plog
            LEFT JOIN tblcallevents tbc   ON tbc.id = plog.task_id
            LEFT JOIN action              ON action.id = tbc.actiontype_id
            LEFT JOIN user_details u1     ON u1.user_id = tbc.user_id
            LEFT JOIN init_call           ON init_call.id = plog.init_id
            LEFT JOIN company_master      ON company_master.id = init_call.cmpid_id
            LEFT JOIN status              ON status.id = init_call.cstatus
            WHERE DATE(plog.re_created_at) <= ?
              AND tbc.nextCFID = 0
              AND org_task_date != '0000-00-00 00:00:00'
              AND $text
              AND u1.status = 'active'
              AND (tbc.delete_request = '' OR tbc.delete_request IS NULL)
            GROUP BY plog.task_id
            HAVING duplicate_count >= 1
            ORDER BY duplicate_count DESC, last_create_date DESC
            LIMIT ?";
        $params = array_merge([$tdate], $tp, [$limit]);
        $rows = $this->db->query($sql, $params)->result_array();

        // ---- replan counts ----
        $tasks_in_log = count($rows);
        $total_replan_entries = 0;   // total planner_log rows across these tasks
        $tasks_replanned = 0;        // tasks re-planned at least once (duplicate_count >= 2)
        $extra_replans = 0;          // re-plans beyond the original (sum of duplicate_count - 1)
        $byUser = [];
        foreach ($rows as $r) {
            $dc = (int)$r['duplicate_count'];
            $total_replan_entries += $dc;
            if ($dc >= 2) { $tasks_replanned++; $extra_replans += ($dc - 1); }
            $un = $r['to_user_name'];
            if (!isset($byUser[$un])) $byUser[$un] = ['to_user' => $r['to_user'], 'to_user_name' => $un, 'tasks' => 0, 'replanned_tasks' => 0, 'extra_replans' => 0];
            $byUser[$un]['tasks']++;
            if ($dc >= 2) { $byUser[$un]['replanned_tasks']++; $byUser[$un]['extra_replans'] += ($dc - 1); }
        }
        usort($byUser, function ($a, $b) { return $b['extra_replans'] - $a['extra_replans']; });

        return [
            'as_of_date'   => $tdate,
            'user_id'      => $uid,
            'role_type_id' => $uyid,
            'scope'        => ($uw ? 'user_wise' : 'hierarchy'),
            'summary'      => [
                'tasks_in_log'         => $tasks_in_log,
                'tasks_replanned'      => $tasks_replanned,      // re-planned >= 1 time
                'extra_replans'        => $extra_replans,        // total re-plan actions beyond original
                'total_planner_entries'=> $total_replan_entries,
            ],
            'by_user'      => array_values($byUser),
            'logs'         => $rows,
        ];
    }

    // ---- planner scope helper (parameterised; mirrors _scopeUsers but returns
    //      [condition, binds] for use with bound queries). 'all' => no filter
    //      (1=1), consistent with the MCP's session-less _scopeUsers. type_id 24
    //      maps to ash_nae_co here to match the source app planner reports (the
    //      shared _scopeUsers maps 24 -> acm_co; see note).
    private function _scopeUsersSafe($alias, $managerId) {
        if (empty($managerId) || $managerId === 'all') return ["1=1", []];
        $m = (int)$managerId;
        $u = $this->db->query("SELECT type_id FROM user_details WHERE user_id = ? LIMIT 1", [$m])->row_array();
        $t = $u ? (int)$u['type_id'] : 0;
        $colMap = [
            1  => 'sadmin_id', 2  => 'admin_id', 4  => 'pst_co', 13 => 'aadmin',
            15 => 'sales_co',  19 => 'ash_nae_co', 20 => 'ash_w_co', 21 => 'ash_s_co',
            22 => 'rm_east_co', 23 => 'rm_north_co', 24 => 'ash_nae_co',
        ];
        if (isset($colMap[$t])) return ["$alias." . $colMap[$t] . " = ?", [$m]];
        return ["$alias.user_id = ?", [$m]];
    }

    // count active users under a (parameterised) scope condition
    private function _countActiveUsersSafe($scope, $binds) {
        $sql = "SELECT COUNT(*) AS cnt FROM user_details u1 WHERE u1.status = 'active' AND $scope";
        $row = $this->db->query($sql, $binds)->row();
        return $row ? (int)$row->cnt : 0;
    }

    // count active users with a planner (autotask_time) set for a given date
    private function _getPlannerSetCountForDate($scope, $binds, $date) {
        $sql = "SELECT COUNT(DISTINCT u1.user_id) AS cnt
                FROM user_details u1
                INNER JOIN autotask_time aut ON aut.user_id = u1.user_id AND DATE(aut.date) = ?
                WHERE u1.status = 'active' AND $scope";
        $allBinds = array_merge([$date], $binds);
        $row = $this->db->query($sql, $allBinds)->row();
        return $row ? (int)$row->cnt : 0;
    }

    // today_planner_activity -> same-day planner monitoring with PARITY to the
    // app's UserWholSameDayActivity: presence is read from `date` (cdate) while
    // the planner joins (autotask/tpft/pa/cpr/pmr) key off `planner_date` (pdate,
    // default = cdate). A SECOND autotask join (keyed on ud.ustart) drives
    // start_as_like_planning, exactly like the source. Adds an all-active-users
    // summary. (Superset: also returns special_leave_* which the source omits.)
    public function today_planner_activity($f = []) {
        $cdate = !empty($f['date']) ? substr($f['date'], 0, 10) : date('Y-m-d');
        $pdate = !empty($f['planner_date']) ? substr($f['planner_date'], 0, 10) : $cdate;
        if (!empty($f['user_id'])) { $scope = "u1.user_id = ?"; $scopeBinds = [(int)$f['user_id']]; $userwise = true; }
        else { list($scope, $scopeBinds) = $this->_scopeUsersSafe('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all'); $userwise = false; }
        $limit = isset($f['limit']) ? (int)$f['limit'] : 500;

        $totalUsers       = $this->_countActiveUsersSafe($scope, $scopeBinds);
        $totalPlanningSet = $this->_getPlannerSetCountForDate($scope, $scopeBinds, $pdate);
        $plannerNotSet    = $totalUsers - $totalPlanningSet;

        $sqlUsers = "SELECT
            u1.user_id, u1.type_id AS user_type_id, u1.name AS username,
            ut.name AS user_roles,
            ud.ustart AS user_start, ud.uclose AS user_close,
            ud.usimg, ud.ucimg,
            ud.slatitude, ud.slongitude, ud.clatitude, ud.clongitude,
            uwf_plan.TYPE  AS planning_userworkfrom,
            uwf_start.TYPE AS start_userworkfrom,
            ud.wffo AS user_wffo,
            CASE WHEN ud.ustart IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_start_own_day,
            CASE WHEN ud.uclose IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_close_own_day,
            CASE WHEN aut_p.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS start_planning,
            aut_p.date AS start_planning_on_date,
            aut_p.created_at AS start_planning_on_day,
            CASE WHEN ud.wffo = aut_s.userworkfrom AND aut_s.userworkfrom != 0 AND aut_s.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS start_as_like_planning,
            CASE WHEN tpft.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS request_same_day_task_plan,
            tpft.would_you_want AS request_same_day_would_you_want,
            tpft.request_remarks AS request_same_day_user_remarks,
            tpft.approvel_status AS request_same_day_approvel_status,
            tpft.remarks AS request_same_day_admin_remarks,
            tpft.created_at AS request_same_day_created_at,
            tpft.apr_time AS request_same_day_planner_apr_time,
            CASE WHEN pa.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_create_planner_approved_request,
            pa.request_type AS planner_request_type,
            pa.request_message AS planner_request_message,
            pa.approved_status AS planner_approved_status,
            u2.name AS planner_approved_by,
            pa.created_at AS planner_request_time,
            pa.approved_date AS planner_approved_date,
            CONCAT(TIMESTAMPDIFF(HOUR, pa.created_at, pa.approved_date), ' hours ',
                   MOD(TIMESTAMPDIFF(MINUTE, pa.created_at, pa.approved_date), 60), ' minutes ',
                   MOD(TIMESTAMPDIFF(SECOND, pa.created_at, pa.approved_date), 60), ' seconds') AS time_taken_in_planner_approved,
            CASE WHEN srfl.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS special_request_for_leave,
            srfl.stime AS special_request_start,
            srfl.etime AS special_request_end,
            srfl.prupose AS special_request_prupose,
            srfl.approve_status AS special_request_approve_status,
            u3.name AS special_request_approve_by,
            srfl.approve_date AS special_request_approve_date,
            srfl.approve_remarks AS special_request_approve_admin_remarks,
            srfl.created_at AS special_request_for_create_time,
            CASE WHEN cpr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS request_pending_task_planning,
            cpr.request_type AS request_pending_task_planning_request_type,
            cpr.task_count AS request_pending_task_planning_task_count,
            cpr.request_remarks AS request_pending_task_planning_user_request_remarks,
            cpr.approved AS request_pending_task_planning_approved_status,
            u4.name AS request_pending_task_planning_approved_by,
            cpr.approved_date AS request_pending_task_planning_approve_date,
            cpr.approved_message AS request_pending_task_planning_admin_remarks,
            cpr.created_at AS request_pending_task_planning_create_time,
            CASE WHEN pmr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS pending_meetings_request,
            pmr.request_date AS task_planner_date,
            pmr.task_ids AS pending_meetings_request_task_ids,
            pmr.request_task_count AS pending_meetings_request_task_count,
            pmr.remarks AS pending_meetings_request_user_request_remarks,
            pmr.apr_status AS pending_meetings_request_approved_status,
            u5.name AS pending_meetings_request_approved_by,
            pmr.apr_date AS pending_meetings_request_approve_date,
            pmr.apr_remakrs AS pending_meetings_request_admin_remarks,
            pmr.created_at AS pending_meetings_request_create_time,
            IFNULL(totals.total_complete_task, 0) AS total_complete_task,
            IFNULL(totals.total_pending_task, 0)  AS total_pending_task
        FROM user_day ud
        INNER JOIN user_details u1 ON u1.user_id = ud.user_id
        LEFT JOIN user_type ut ON ut.id = u1.type_id
        LEFT JOIN autotask_time aut_p ON aut_p.user_id = u1.user_id AND DATE(aut_p.date) = ?
        LEFT JOIN autotask_time aut_s ON aut_s.user_id = u1.user_id AND DATE(aut_s.date) = DATE(ud.ustart)
        LEFT JOIN userworkfrom uwf_plan  ON uwf_plan.id  = aut_p.userworkfrom
        LEFT JOIN userworkfrom uwf_start ON uwf_start.id = ud.wffo
        LEFT JOIN task_plan_for_today tpft ON tpft.user_id = u1.user_id AND DATE(tpft.date) = ?
        LEFT JOIN planner_approved pa ON pa.user_id = u1.user_id AND pa.request_date = ?
        LEFT JOIN special_request_for_leave srfl ON srfl.user_id = u1.user_id AND DATE(srfl.date) = ?
        LEFT JOIN create_planner_request cpr ON cpr.request_user_id = u1.user_id AND DATE(cpr.request_date) = ?
        LEFT JOIN pending_meetings_request pmr ON pmr.user_uid = u1.user_id AND DATE(pmr.request_date) = ?
        LEFT JOIN user_details u2 ON u2.user_id = pa.approved_by
        LEFT JOIN user_details u3 ON u3.user_id = srfl.approve_by
        LEFT JOIN user_details u4 ON u4.user_id = cpr.approved_by
        LEFT JOIN user_details u5 ON u5.user_id = pmr.apr_by
        LEFT JOIN (
            SELECT user_id, DATE(appointmentdatetime) AS task_date,
                SUM(CASE WHEN nextCFID != 0 AND (approved_status = 1 OR approved_status = '') THEN 1 ELSE 0 END) AS total_complete_task,
                SUM(CASE WHEN nextCFID = 0 AND (approved_status = 1 OR approved_status = '') THEN 1 ELSE 0 END) AS total_pending_task
            FROM tblcallevents GROUP BY user_id, DATE(appointmentdatetime)
        ) totals ON totals.user_id = u1.user_id AND totals.task_date = DATE(ud.ustart)
        WHERE DATE(ud.ustart) = ? AND u1.status = 'active' AND $scope
        GROUP BY u1.user_id, DATE(ud.ustart)
        LIMIT $limit";
        $binds = array_merge([$pdate, $pdate, $pdate, $pdate, $pdate, $pdate, $cdate], $scopeBinds);
        $rows = $this->db->query($sqlUsers, $binds)->result_array();

        $presentCount = count($rows); $absentCount = $totalUsers - $presentCount;
        $dayClosed = $sameDayReq = $plannerAppr = $specialLeave = $pendingTask = $pendingMeet = 0;
        foreach ($rows as $r) {
            if (($r['user_close_own_day'] ?? 'No') === 'Yes') $dayClosed++;
            if (($r['request_same_day_task_plan'] ?? 'No') === 'Yes') $sameDayReq++;
            if (($r['user_create_planner_approved_request'] ?? 'No') === 'Yes') $plannerAppr++;
            if (($r['special_request_for_leave'] ?? 'No') === 'Yes') $specialLeave++;
            if (($r['request_pending_task_planning'] ?? 'No') === 'Yes') $pendingTask++;
            if (($r['pending_meetings_request'] ?? 'No') === 'Yes') $pendingMeet++;
        }
        $summary = [
            'total_users' => $totalUsers, 'total_present' => $presentCount, 'total_absent' => $absentCount,
            'total_planning_set' => $totalPlanningSet, 'total_planner_not_set' => $plannerNotSet,
            'day_closed' => $dayClosed, 'same_day_task_plan_requested' => $sameDayReq,
            'planner_approval_requested' => $plannerAppr, 'special_leave_requested' => $specialLeave,
            'pending_task_planning_request' => $pendingTask, 'pending_meetings_request' => $pendingMeet,
        ];
        return ['date' => $cdate, 'planner_date' => $pdate, 'scope' => $userwise ? 'user_wise' : 'hierarchy', 'summary' => $summary, 'users' => $rows];
    }

    // next_day_planner_activity -> next-day planner monitoring
    // (Reports/TaskPlannerLive). Presence read from the working date (date);
    // planner-approval / pending-task / pending-meetings / autotask joins key
    // off planner_date. Adds an all-active-users summary. Parameterised scope.
    public function next_day_planner_activity($f = []) {
        $cdate = !empty($f['date']) ? substr($f['date'], 0, 10) : date('Y-m-d');
        $pdate = !empty($f['planner_date']) ? substr($f['planner_date'], 0, 10) : $cdate;
        $limit = isset($f['limit']) ? (int)$f['limit'] : 500;
        if (!empty($f['user_id'])) { $scope = "u1.user_id = ?"; $scopeBinds = [(int)$f['user_id']]; $scopeType = 'user_wise'; }
        else { list($scope, $scopeBinds) = $this->_scopeUsersSafe('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all'); $scopeType = 'hierarchy'; }

        $sql = "SELECT
            u1.user_id, u1.type_id AS user_type_id, u1.name AS username,
            ut.name AS user_roles,
            ud.ustart AS user_start, ud.uclose AS user_close,
            ud.usimg, ud.ucimg,
            ud.slatitude, ud.slongitude, ud.clatitude, ud.clongitude,
            uwf_plan.type  AS planning_userworkfrom,
            uwf_start.type AS start_userworkfrom,
            ud.wffo AS user_wffo,
            CASE WHEN ud.ustart IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_start_own_day,
            CASE WHEN aut.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS start_planning,
            aut.date AS start_planning_on_date,
            aut.created_at AS start_planning_on_day,
            CASE WHEN pa.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_create_planner_approved_request,
            pa.request_type AS planner_request_type,
            pa.request_message AS planner_request_message,
            pa.approved_status AS planner_approved_status,
            u2.name AS planner_approved_by,
            pa.created_at AS planner_request_time,
            pa.approved_date AS planner_approved_date,
            CONCAT(TIMESTAMPDIFF(HOUR, pa.created_at, pa.approved_date), ' hours ',
                   MOD(TIMESTAMPDIFF(MINUTE, pa.created_at, pa.approved_date), 60), ' minutes ',
                   MOD(TIMESTAMPDIFF(SECOND, pa.created_at, pa.approved_date), 60), ' seconds') AS time_taken_in_planner_approved,
            CASE WHEN cpr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS request_pending_task_planning,
            cpr.request_type AS request_pending_task_planning_request_type,
            cpr.task_count AS request_pending_task_planning_task_count,
            cpr.request_remarks AS request_pending_task_planning_user_request_remarks,
            cpr.approved AS request_pending_task_planning_approved_status,
            u4.name AS request_pending_task_planning_approved_by,
            cpr.approved_date AS request_pending_task_planning_approve_date,
            cpr.approved_message AS request_pending_task_planning_admin_remarks,
            cpr.created_at AS request_pending_task_planning_create_time,
            CASE WHEN pmr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS pending_meetings_request,
            pmr.request_date AS task_planner_date,
            pmr.task_ids AS pending_meetings_request_task_ids,
            pmr.request_task_count AS pending_meetings_request_task_count,
            pmr.remarks AS pending_meetings_request_user_request_remarks,
            pmr.apr_status AS pending_meetings_request_approved_status,
            u5.name AS pending_meetings_request_approved_by,
            pmr.apr_date AS pending_meetings_request_approve_date,
            pmr.apr_remakrs AS pending_meetings_request_admin_remarks,
            pmr.created_at AS pending_meetings_request_create_time,
            IFNULL(totals.total_complete_task, 0) AS total_complete_task,
            IFNULL(totals.total_pending_task, 0)  AS total_pending_task
        FROM user_day ud
        INNER JOIN user_details u1 ON u1.user_id = ud.user_id AND u1.status = 'active'
        LEFT JOIN user_type ut ON ut.id = u1.type_id
        LEFT JOIN autotask_time aut ON aut.user_id = u1.user_id AND DATE(aut.date) = ?
        LEFT JOIN userworkfrom uwf_plan  ON uwf_plan.id  = aut.userworkfrom
        LEFT JOIN userworkfrom uwf_start ON uwf_start.id = ud.wffo
        LEFT JOIN planner_approved pa ON pa.user_id = u1.user_id AND pa.request_date = ?
        LEFT JOIN user_details u2 ON u2.user_id = pa.approved_by
        LEFT JOIN create_planner_request cpr ON cpr.request_user_id = u1.user_id AND DATE(cpr.request_date) = ?
        LEFT JOIN user_details u4 ON u4.user_id = cpr.approved_by
        LEFT JOIN pending_meetings_request pmr ON pmr.user_uid = u1.user_id AND DATE(pmr.request_date) = ?
        LEFT JOIN user_details u5 ON u5.user_id = pmr.apr_by
        LEFT JOIN (
            SELECT user_id, DATE(appointmentdatetime) AS task_date,
                SUM(CASE WHEN nextCFID != 0 AND (approved_status = '1' OR approved_status = '') THEN 1 ELSE 0 END) AS total_complete_task,
                SUM(CASE WHEN nextCFID = 0  AND (approved_status = '1' OR approved_status = '') THEN 1 ELSE 0 END) AS total_pending_task
            FROM tblcallevents GROUP BY user_id, DATE(appointmentdatetime)
        ) totals ON totals.user_id = u1.user_id AND totals.task_date = DATE(ud.ustart)
        WHERE DATE(ud.ustart) BETWEEN ? AND ? AND $scope
        ORDER BY u1.user_id
        LIMIT $limit";
        $binds = array_merge([$pdate, $pdate, $pdate, $pdate, $cdate, $cdate], $scopeBinds);
        $rows = $this->db->query($sql, $binds)->result_array();

        $sum = ['present' => count($rows), 'planner_set' => 0, 'planner_approval_requested' => 0,
                'pending_task_planning_request' => 0, 'pending_meetings_request' => 0];
        foreach ($rows as $r) {
            if (($r['start_planning'] ?? 'No') === 'Yes') $sum['planner_set']++;
            if (($r['user_create_planner_approved_request'] ?? 'No') === 'Yes') $sum['planner_approval_requested']++;
            if (($r['request_pending_task_planning'] ?? 'No') === 'Yes') $sum['pending_task_planning_request']++;
            if (($r['pending_meetings_request'] ?? 'No') === 'Yes') $sum['pending_meetings_request']++;
        }
        $totalUsers = $this->_countActiveUsersSafe($scope, $scopeBinds);
        $plannerSet = $this->_getPlannerSetCountForDate($scope, $scopeBinds, $pdate);
        $sum['total_active_users'] = $totalUsers;
        $sum['absent'] = $totalUsers - $sum['present'];
        $sum['planner_not_set'] = $totalUsers - $plannerSet;
        $sum['planner_set'] = $plannerSet;
        return ['date' => $cdate, 'planner_date' => $pdate, 'scope' => $scopeType, 'summary' => $sum, 'users' => $rows];
    }

    // planner_live_report -> UNIFIED, range-aware same-day planner report,
    // grouped BY DAY with a per-day summary. Single date OR start_date+end_date.
    // Replaces the separate same-day report; reuses the parameterised helpers.
    public function planner_live_report($f = []) {
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $startDate = substr($f['start_date'], 0, 10); $endDate = substr($f['end_date'], 0, 10);
        } else {
            $single = !empty($f['date']) ? substr($f['date'], 0, 10) : date('Y-m-d');
            $startDate = $single; $endDate = $single;
        }
        $re = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($re, $startDate) || !preg_match($re, $endDate)) return ['__error' => 'Dates must be Y-m-d.'];
        if ($startDate > $endDate) return ['__error' => 'start_date must be <= end_date.'];
        $limit = isset($f['limit']) ? max(0, (int)$f['limit']) : 5000;

        if (!empty($f['user_id'])) { $scope = "u1.user_id = ?"; $scopeBinds = [(int)$f['user_id']]; $scopeType = 'user_wise'; }
        else { list($scope, $scopeBinds) = $this->_scopeUsersSafe('u1', isset($f['manager_id']) ? $f['manager_id'] : 'all'); $scopeType = 'hierarchy'; }

        $totalUsers = $this->_countActiveUsersSafe($scope, $scopeBinds);

        $sql = "SELECT
            DATE(ud.ustart) AS report_date,
            u1.user_id, u1.type_id AS user_type_id, u1.name AS username, ut.name AS user_roles,
            ud.ustart AS user_start, ud.uclose AS user_close,
            ud.usimg, ud.ucimg, ud.slatitude, ud.slongitude, ud.clatitude, ud.clongitude,
            uwf_plan.TYPE AS planning_userworkfrom, uwf_start.TYPE AS start_userworkfrom, ud.wffo AS user_wffo,
            CASE WHEN ud.ustart IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_start_own_day,
            CASE WHEN ud.uclose IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_close_own_day,
            CASE WHEN ud.wffo = aut.userworkfrom AND aut.userworkfrom != 0 AND aut.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS start_as_like_planning,
            CASE WHEN aut.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS start_planning,
            aut.created_at AS start_planning_on_day,
            CASE WHEN tpft.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS request_same_day_task_plan,
            tpft.would_you_want AS request_same_day_would_you_want,
            tpft.request_remarks AS request_same_day_user_remarks,
            tpft.approvel_status AS request_same_day_approvel_status,
            tpft.remarks AS request_same_day_admin_remarks,
            tpft.created_at AS request_same_day_created_at,
            tpft.apr_time AS request_same_day_planner_apr_time,
            CASE WHEN pa.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS user_create_planner_approved_request,
            pa.request_type AS planner_request_type, pa.request_message AS planner_request_message,
            pa.approved_status AS planner_approved_status, u2.name AS planner_approved_by,
            pa.created_at AS planner_request_time, pa.approved_date AS planner_approved_date,
            CASE WHEN srfl.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS special_request_for_leave,
            srfl.stime AS special_request_start, srfl.etime AS special_request_end,
            srfl.prupose AS special_request_prupose, srfl.approve_status AS special_request_approve_status,
            u3.name AS special_request_approve_by, srfl.approve_date AS special_request_approve_date,
            srfl.approve_remarks AS special_request_approve_admin_remarks, srfl.created_at AS special_request_for_create_time,
            CASE WHEN cpr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS request_pending_task_planning,
            cpr.request_type AS request_pending_task_planning_request_type,
            cpr.task_count AS request_pending_task_planning_task_count,
            cpr.request_remarks AS request_pending_task_planning_user_request_remarks,
            cpr.approved AS request_pending_task_planning_approved_status,
            u4.name AS request_pending_task_planning_approved_by,
            cpr.approved_date AS request_pending_task_planning_approve_date,
            cpr.approved_message AS request_pending_task_planning_admin_remarks,
            cpr.created_at AS request_pending_task_planning_create_time,
            CASE WHEN pmr.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS pending_meetings_request,
            pmr.task_ids AS pending_meetings_request_task_ids,
            pmr.request_task_count AS pending_meetings_request_task_count,
            pmr.remarks AS pending_meetings_request_user_request_remarks,
            pmr.apr_status AS pending_meetings_request_approved_status,
            u5.name AS pending_meetings_request_approved_by,
            pmr.apr_date AS pending_meetings_request_approve_date,
            pmr.apr_remakrs AS pending_meetings_request_admin_remarks,
            pmr.created_at AS pending_meetings_request_create_time,
            IFNULL(totals.total_complete_task, 0) AS total_complete_task,
            IFNULL(totals.total_pending_task, 0)  AS total_pending_task
        FROM user_day ud
        INNER JOIN user_details u1 ON u1.user_id = ud.user_id AND u1.status = 'active'
        LEFT JOIN user_type ut ON ut.id = u1.type_id
        LEFT JOIN autotask_time aut ON aut.user_id = u1.user_id AND DATE(aut.date) = DATE(ud.ustart)
        LEFT JOIN userworkfrom uwf_plan  ON uwf_plan.id  = aut.userworkfrom
        LEFT JOIN userworkfrom uwf_start ON uwf_start.id = ud.wffo
        LEFT JOIN task_plan_for_today tpft ON tpft.user_id = u1.user_id AND DATE(tpft.date) = DATE(ud.ustart)
        LEFT JOIN planner_approved pa ON pa.user_id = u1.user_id AND pa.request_date = DATE(ud.ustart)
        LEFT JOIN user_details u2 ON u2.user_id = pa.approved_by
        LEFT JOIN special_request_for_leave srfl ON srfl.user_id = u1.user_id AND DATE(srfl.date) = DATE(ud.ustart)
        LEFT JOIN user_details u3 ON u3.user_id = srfl.approve_by
        LEFT JOIN create_planner_request cpr ON cpr.request_user_id = u1.user_id AND DATE(cpr.request_date) = DATE(ud.ustart)
        LEFT JOIN user_details u4 ON u4.user_id = cpr.approved_by
        LEFT JOIN pending_meetings_request pmr ON pmr.user_uid = u1.user_id AND DATE(pmr.request_date) = DATE(ud.ustart)
        LEFT JOIN user_details u5 ON u5.user_id = pmr.apr_by
        LEFT JOIN (
            SELECT ce.user_id, DATE(ce.appointmentdatetime) AS task_date,
                SUM(CASE WHEN ce.nextCFID != 0 AND (ce.approved_status = '1' OR ce.approved_status = '') THEN 1 ELSE 0 END) AS total_complete_task,
                SUM(CASE WHEN ce.nextCFID = 0  AND (ce.approved_status = '1' OR ce.approved_status = '') THEN 1 ELSE 0 END) AS total_pending_task
            FROM tblcallevents ce
            WHERE DATE(ce.appointmentdatetime) BETWEEN ? AND ?
            GROUP BY ce.user_id, DATE(ce.appointmentdatetime)
        ) totals ON totals.user_id = u1.user_id AND totals.task_date = DATE(ud.ustart)
        WHERE DATE(ud.ustart) BETWEEN ? AND ? AND $scope
        ORDER BY ud.ustart, u1.user_id" . ($limit > 0 ? " LIMIT $limit" : "");
        $allBinds = array_merge([$startDate, $endDate, $startDate, $endDate], $scopeBinds);
        $rows = $this->db->query($sql, $allBinds)->result_array();

        $daysMap = [];
        foreach ($rows as $row) { $daysMap[$row['report_date']][] = $row; }
        $resultDays = [];
        foreach ($daysMap as $date => $dayRows) {
            $present = count($dayRows);
            $dayClosed = $sameDayTask = $plannerApproval = $specialLeave = $pendingTask = $pendingMeet = 0;
            foreach ($dayRows as $r) {
                if (($r['user_close_own_day'] ?? 'No') === 'Yes') $dayClosed++;
                if (($r['request_same_day_task_plan'] ?? 'No') === 'Yes') $sameDayTask++;
                if (($r['user_create_planner_approved_request'] ?? 'No') === 'Yes') $plannerApproval++;
                if (($r['special_request_for_leave'] ?? 'No') === 'Yes') $specialLeave++;
                if (($r['request_pending_task_planning'] ?? 'No') === 'Yes') $pendingTask++;
                if (($r['pending_meetings_request'] ?? 'No') === 'Yes') $pendingMeet++;
            }
            $plannerSetGlobal = $this->_getPlannerSetCountForDate($scope, $scopeBinds, $date);
            $resultDays[] = [
                'date' => $date,
                'summary' => [
                    'total_users' => $totalUsers, 'present' => $present, 'absent' => $totalUsers - $present,
                    'planner_set' => $plannerSetGlobal, 'planner_not_set' => $totalUsers - $plannerSetGlobal,
                    'day_closed' => $dayClosed, 'same_day_task_plan_requested' => $sameDayTask,
                    'planner_approval_requested' => $plannerApproval, 'special_leave_requested' => $specialLeave,
                    'pending_task_planning_request' => $pendingTask, 'pending_meetings_request' => $pendingMeet,
                ],
                'users' => $dayRows,
            ];
        }
        return ['scope' => $scopeType, 'date_from' => $startDate, 'date_to' => $endDate, 'total_days' => count($resultDays), 'days' => $resultDays];
    }

    // company_details -> single-company 360 view (port of Menu/CompanyDetails/{cid})
    // plus derived KEY ANALYSIS. Requires company_id (= company_master.id = cid).
    // Activities reach the company via tblcallevents.cid_id -> init_call.id ->
    // init_call.cmpid_id = company_master.id. Returns: overview + created-date
    // understanding (days_since_created), team (BD/CM/ACM resolved), classification
    // flags + FY funnels, task-activity summary (by action), status-activity summary
    // (by change-on status), STATUS CONVERSIONS (from->to with counts, all-time +
    // current FY), MEETINGS DONE (3/4/22 + RP split), PROPOSALS (count/value via
    // proposal.pbudgetme), completed/pending tasks, and ACTIVITY-GAP analysis
    // (duration between consecutive activities: avg/min/max gap days, idle days).
    // Unmapped report fields (Partner Name/Budget/Website/Address/PST/ASH/RM rows)
    // are returned verbatim in company_master_raw / lead_raw.
    public function company_details($f = []) {
        if (empty($f['company_id'])) return ['__error' => 'company_id required'];
        $cid = (int)$f['company_id'];

        // financial year window (Apr-Mar; Jan-Mar belongs to the FY that began last April)
        $mo = (int)date('n'); $yr = (int)date('Y');
        $fyStart = ($mo >= 4) ? $yr : $yr - 1;
        $fyFrom  = $fyStart . "-04-01 00:00:00";
        $fyTo    = ($fyStart + 1) . "-03-31 23:59:59";

        $company = $this->db->query("SELECT * FROM company_master WHERE id = ? LIMIT 1", [$cid])->row_array();
        if (!$company) return ['__error' => 'company not found for company_id ' . $cid];

        $leads    = $this->db->query("SELECT * FROM init_call WHERE cmpid_id = ? ORDER BY id", [$cid])->result_array();
        $lead     = !empty($leads) ? $leads[0] : array();
        $lead_ids = array();
        foreach ($leads as $L) { $lead_ids[] = (int)$L['id']; }

        // current status name (status.id = init_call.cstatus)
        $cur_status = null;
        if (!empty($lead['cstatus'])) {
            $s = $this->db->query("SELECT name FROM status WHERE id = ? LIMIT 1", [(int)$lead['cstatus']])->row_array();
            $cur_status = $s ? $s['name'] : null;
        }

        // resolve a user_id -> name
        $resolve = function($uid) {
            $uid = (int)$uid; if (!$uid) return null;
            $u = $this->db->query("SELECT name FROM user_details WHERE user_id = ? LIMIT 1", [$uid])->row_array();
            return $u ? $u['name'] : null;
        };
        $g = function($k) use ($lead) { return isset($lead[$k]) ? $lead[$k] : null; };

        $team = array(
            'main_bd'         => array('id' => (int)$g('mainbd'),    'name' => $resolve($g('mainbd'))),
            'cluster_manager' => array('id' => (int)$g('clm_id'),    'name' => $resolve($g('clm_id'))),
            'assistant_cm'    => array('id' => (int)$g('acm_co_id'), 'name' => $resolve($g('acm_co_id'))),
        );

        $classification = array(
            'top_spender'           => $g('topspender'),
            'upsell_client'         => $g('upsell_client'),
            'focus_funnel'          => $g('focus_funnel'),
            'anchor_clients'        => $g('anchor_clients'),
            'key_company'           => $g('keycompany'),
            'positive_key_client'   => $g('pkclient'),
            'priority_calling'      => $g('priorityc'),
            'potential_client'      => $g('potential'),
            'marked_for_monitoring' => ($g('need_to_be_monitored') !== null && $g('need_to_be_monitored') !== '') ? 'yes' : 'no',
        );
        $funnel = array(
            'q1_20_closure_funnel'  => $g('q1_twetenty_closure_funnel'),
            'potential_funnel_fy'   => $g('potential_funnel_for_fy'),
            'to_be_nurtured_fy'     => $g('to_be_nurtured_for_fy'),
            'fifty_new_lead_funnel' => $g('fifity_new_lead_funnel'),
            'in_quarter'            => $g('in_quarter'),
        );

        // task-activity summary (by action name)
        $task_activity = $this->db->query(
            "SELECT COALESCE(a.name,'(none)') AS action_type, COUNT(*) AS total
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             LEFT JOIN action a ON a.id = t.actiontype_id
             WHERE ic.cmpid_id = ? GROUP BY a.name ORDER BY total DESC", [$cid])->result_array();

        // status-activity summary (by change-on status = nstatus_id)
        $status_activity = $this->db->query(
            "SELECT COALESCE(sn.name,'(none)') AS status, COUNT(*) AS total
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             LEFT JOIN status sn ON sn.id = t.nstatus_id
             WHERE ic.cmpid_id = ? GROUP BY sn.name ORDER BY total DESC", [$cid])->result_array();

        // status conversions (from -> to, only where the status changed)
        $conv_sql = "SELECT COALESCE(so.name,'(none)') AS from_status, COALESCE(sn.name,'(none)') AS to_status, COUNT(*) AS total
                     FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
                     LEFT JOIN status so ON so.id = t.status_id
                     LEFT JOIN status sn ON sn.id = t.nstatus_id
                     WHERE ic.cmpid_id = ? AND t.nstatus_id IS NOT NULL AND t.nstatus_id <> 0
                       AND t.status_id <> t.nstatus_id %s
                     GROUP BY so.name, sn.name ORDER BY total DESC";
        $conv_all = $this->db->query(sprintf($conv_sql, ""), [$cid])->result_array();
        $conv_fy  = $this->db->query(sprintf($conv_sql, "AND t.appointmentdatetime BETWEEN ? AND ?"), [$cid, $fyFrom, $fyTo])->result_array();

        // meetings done (actiontype 3=scheduled,4=barg-in,22=virtual) + RP split
        $meet = $this->db->query(
            "SELECT COUNT(*) AS total_meetings,
                    SUM(CASE WHEN UPPER(t.mtype) IN ('RP','RPCLOSE','CHANGE RP') THEN 1 ELSE 0 END) AS rp_meetings,
                    SUM(CASE WHEN UPPER(t.mtype) IN ('NORP','NO RP') THEN 1 ELSE 0 END) AS no_rp_meetings,
                    SUM(CASE WHEN t.actiontype_id = 3  THEN 1 ELSE 0 END) AS scheduled,
                    SUM(CASE WHEN t.actiontype_id = 4  THEN 1 ELSE 0 END) AS barg_in,
                    SUM(CASE WHEN t.actiontype_id = 22 THEN 1 ELSE 0 END) AS virtual,
                    MAX(t.appointmentdatetime) AS last_meeting
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             WHERE ic.cmpid_id = ? AND t.actiontype_id IN (3,4,22)", [$cid])->row_array();

        // proposals (proposal.init_id -> init_call.id), value via pbudgetme
        $prop = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN p.apr = 1 THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN p.apr = 0 OR p.apr IS NULL THEN 1 ELSE 0 END) AS pending,
                    COALESCE(SUM(p.pbudgetme),0) AS total_value,
                    MAX(p.sdatet) AS last_sent
             FROM proposal p JOIN init_call ic ON ic.id = p.init_id
             WHERE ic.cmpid_id = ?", [$cid])->row_array();

        // completed vs pending tasks
        $tasks = $this->db->query(
            "SELECT COUNT(*) AS total_tasks,
                    SUM(CASE WHEN t.nextCFID != 0 AND (t.approved_status = 1 OR t.approved_status = '') THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN t.nextCFID = 0  AND (t.approved_status = 1 OR t.approved_status = '') THEN 1 ELSE 0 END) AS pending
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             WHERE ic.cmpid_id = ?", [$cid])->row_array();

        // activity-gap analysis (duration between consecutive activities)
        $times = $this->db->query(
            "SELECT t.appointmentdatetime AS dt
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             WHERE ic.cmpid_id = ? AND t.appointmentdatetime IS NOT NULL
             ORDER BY t.appointmentdatetime", [$cid])->result_array();
        $stamps = array();
        foreach ($times as $r) { $ts = strtotime($r['dt']); if ($ts) $stamps[] = $ts; }
        $n = count($stamps);
        $gap = array('total_activities' => $n, 'first_activity' => null, 'last_activity' => null,
                     'avg_gap_days' => null, 'max_gap_days' => null, 'min_gap_days' => null,
                     'days_since_last_activity' => null);
        if ($n > 0) {
            $gap['first_activity'] = date('Y-m-d H:i:s', $stamps[0]);
            $gap['last_activity']  = date('Y-m-d H:i:s', $stamps[$n - 1]);
            $gap['days_since_last_activity'] = round((time() - $stamps[$n - 1]) / 86400, 1);
            if ($n > 1) {
                $diffs = array();
                for ($i = 1; $i < $n; $i++) { $diffs[] = ($stamps[$i] - $stamps[$i - 1]) / 86400; }
                $gap['avg_gap_days'] = round(array_sum($diffs) / count($diffs), 1);
                $gap['max_gap_days'] = round(max($diffs), 1);
                $gap['min_gap_days'] = round(min($diffs), 1);
            }
        }

        // created-date understanding
        $created_on = $g('createDate');
        if ($created_on === null && isset($company['createDate'])) $created_on = $company['createDate'];
        $updated_on = $g('updateddate');
        $days_since_created = ($created_on && strtotime($created_on)) ? round((time() - strtotime($created_on)) / 86400, 1) : null;
        $created = array('created_on' => $created_on, 'last_updated' => $updated_on, 'days_since_created' => $days_since_created);

        // short recent timeline (full log is in lead_timeline / activity_log)
        $recent = $this->db->query(
            "SELECT CAST(t.appointmentdatetime AS DATE) AS date, a.name AS action_type, t.mtype,
                    so.name AS from_status, sn.name AS to_status, t.purpose_achieved, u.name AS by_user
             FROM tblcallevents t JOIN init_call ic ON ic.id = t.cid_id
             LEFT JOIN action a ON a.id = t.actiontype_id
             LEFT JOIN status so ON so.id = t.status_id
             LEFT JOIN status sn ON sn.id = t.nstatus_id
             LEFT JOIN user_details u ON u.user_id = t.user_id
             WHERE ic.cmpid_id = ? ORDER BY t.appointmentdatetime DESC LIMIT 20", [$cid])->result_array();

        return array(
            'company_id'              => $cid,
            'company_name'            => isset($company['compname']) ? $company['compname'] : null,
            'lead_ids'                => $lead_ids,
            'lead_count'              => count($lead_ids),
            'current_status'          => $cur_status,
            'overview'                => array(
                'state'      => isset($company['state']) ? $company['state'] : null,
                'district'   => isset($company['district']) ? $company['district'] : null,
                'city'       => isset($company['city']) ? $company['city'] : null,
                'cluster_id' => $g('cluster_id'),
            ),
            'created'                 => $created,
            'team'                    => $team,
            'classification'          => $classification,
            'funnel'                  => $funnel,
            'task_activity_summary'   => $task_activity,
            'status_activity_summary' => $status_activity,
            'status_conversions'      => array('all_time' => $conv_all, 'current_fy' => $conv_fy, 'fy_window' => array($fyFrom, $fyTo)),
            'meetings'                => $meet,
            'proposals'               => $prop,
            'tasks'                   => $tasks,
            'activity_gap_analysis'   => $gap,
            'recent_activities'       => $recent,
            'company_master_raw'      => $company,
            'lead_raw'                => $lead,
        );
    }


    // sprint_book -> Quarter-End Sprint LIVE engine (port of the BD-Capacity
    // workbook LIVE sheets 6/7/8). Qualifying = ic.cstatus IN (6,7,9,12,13)
    // (Positive / Closure-in-progress / Very Positive / Positive-NAP / Very
    // Positive-NAP) — same set as sprint_closure_churn — DEDUPED BY COMPANY
    // (strongest lead kept). Per company it derives: SLA max-days for the stage,
    // days_overdue vs idle, BREACH flag, a T1/T2/VERIFY buy-signal tier + RAG,
    // and closeability. Returns headline summary (expected closures = qualifying x
    // conversion, indicative ASV), a by-BD load/capacity rollup (8-12 cap), the
    // book itself, and the model assumptions. Filters: manager_id, bd_id, limit;
    // tunable conversion (def 0.35) and avg_ticket Cr (def 0.25).
    public function sprint_book($f = []) {
        $limit  = isset($f['limit'])      ? (int)$f['limit']        : 500;
        $conv   = isset($f['conversion']) ? (float)$f['conversion'] : 0.35;
        $ticket = isset($f['avg_ticket']) ? (float)$f['avg_ticket'] : 0.25;
        $where  = "ic.cstatus IN (6,7,9,12,13)";
        $params = array();
        if (!empty($f['manager_id'])) { $where .= " AND (ic.clm_id = ? OR ic.acm_co_id = ?)"; $params[] = (int)$f['manager_id']; $params[] = (int)$f['manager_id']; }
        if (!empty($f['bd_id']))      { $where .= " AND ic.mainbd = ?"; $params[] = (int)$f['bd_id']; }

        $sql = "SELECT cm.id AS company_id, cm.compname AS company, ic.id AS lead_id,
                       ic.mainbd AS bd_id, u.name AS bd_name, s.name AS stage, ic.cstatus,
                       ((SELECT COUNT(*) FROM proposal p WHERE p.init_id = ic.id) > 0) AS has_proposal,
                       MAX(CASE WHEN LOWER(t.purpose_achieved) = 'yes' THEN 1 ELSE 0 END) AS purpose_achieved,
                       CAST(MAX(t.appointmentdatetime) AS DATE) AS last_activity,
                       DATEDIFF(CURDATE(), MAX(t.appointmentdatetime)) AS idle_days,
                       (CASE ic.cstatus WHEN 7 THEN 5 WHEN 9 THEN 4 WHEN 13 THEN 4 WHEN 6 THEN 3 WHEN 12 THEN 3 ELSE 1 END
                        + ((SELECT COUNT(*) FROM proposal p WHERE p.init_id = ic.id) > 0) * 2) AS closeability
                FROM init_call ic
                LEFT JOIN company_master cm ON cm.id = ic.cmpid_id
                LEFT JOIN user_details u ON u.user_id = ic.mainbd
                LEFT JOIN status s ON s.id = ic.cstatus
                LEFT JOIN tblcallevents t ON t.cid_id = ic.id
                WHERE $where
                GROUP BY ic.id
                ORDER BY closeability DESC, idle_days DESC
                LIMIT $limit";
        $rows = $this->db->query($sql, $params)->result_array();

        // SLA max-days per stage (Touchpoint Model sheet, mapped by cstatus)
        $slaMap = array(6 => 21, 7 => 7, 9 => 14, 12 => 7, 13 => 5);

        $book = array(); $seen = array();
        foreach ($rows as $r) {
            $key = ($r['company_id'] !== null) ? 'c' . $r['company_id'] : 'l' . $r['lead_id'];
            if (isset($seen[$key])) continue;               // dedupe by company, keep strongest
            $seen[$key] = true;

            $cs   = (int)$r['cstatus'];
            $sla  = isset($slaMap[$cs]) ? $slaMap[$cs] : 7;
            $idle = ($r['idle_days'] === null) ? null : (int)$r['idle_days'];
            $over = ($idle === null) ? null : ($idle - $sla);
            $breach  = ($idle === null) ? true : ($over > 0);
            $hasProp = ((int)$r['has_proposal'] === 1);
            $purpose = ((int)$r['purpose_achieved'] === 1);

            if ($hasProp && in_array($cs, array(7, 9, 13), true)) { $tier = 'SEND T1'; $rag = 'RED'; }
            elseif ($hasProp || $purpose)                         { $tier = 'SEND T2'; $rag = 'AMBER'; }
            else                                                  { $tier = 'VERIFY';  $rag = 'GREEN'; }

            $book[] = array(
                'company_id'       => ($r['company_id'] !== null) ? (int)$r['company_id'] : null,
                'company'          => $r['company'],
                'lead_id'          => (int)$r['lead_id'],
                'bd_id'            => (int)$r['bd_id'],
                'bd'               => $r['bd_name'],
                'stage'            => $r['stage'],
                'cstatus'          => $cs,
                'has_proposal'     => $hasProp ? 'yes' : 'no',
                'purpose_achieved' => $purpose ? 'yes' : 'no',
                'last_activity'    => $r['last_activity'],
                'idle_days'        => $idle,
                'sla_max_days'     => $sla,
                'days_overdue'     => $over,
                'sla_status'       => $breach ? 'BREACH' : 'OK',
                'tier'             => $tier,
                'rag'              => $rag,
                'closeability'     => (int)$r['closeability'],
            );
        }

        // by-BD load vs capacity
        $byBd = array();
        foreach ($book as $b) {
            $k = $b['bd_id'];
            if (!isset($byBd[$k])) $byBd[$k] = array('bd_id' => $b['bd_id'], 'bd' => $b['bd'], 'qualifying' => 0, 't1' => 0, 't2' => 0, 'verify' => 0, 'top_stage' => $b['stage'], '_top' => $b['closeability']);
            $byBd[$k]['qualifying']++;
            if ($b['tier'] === 'SEND T1') $byBd[$k]['t1']++;
            elseif ($b['tier'] === 'SEND T2') $byBd[$k]['t2']++;
            else $byBd[$k]['verify']++;
            if ($b['closeability'] > $byBd[$k]['_top']) { $byBd[$k]['_top'] = $b['closeability']; $byBd[$k]['top_stage'] = $b['stage']; }
        }
        foreach ($byBd as $kk => $vv) {
            $byBd[$kk]['within_cap'] = ($vv['qualifying'] <= 12) ? 'YES' : 'OVER';
            $byBd[$kk]['expected_closures'] = (int)round($vv['qualifying'] * $conv);
            unset($byBd[$kk]['_top']);
        }
        $byBd = array_values($byBd);
        usort($byBd, function($a, $b) { return $b['qualifying'] - $a['qualifying']; });

        // headline summary
        $t1 = $t2 = $vf = 0; $stageMix = array();
        foreach ($book as $b) {
            if ($b['tier'] === 'SEND T1') $t1++; elseif ($b['tier'] === 'SEND T2') $t2++; else $vf++;
            $st = ($b['stage'] !== null && $b['stage'] !== '') ? $b['stage'] : '(none)';
            $stageMix[$st] = isset($stageMix[$st]) ? $stageMix[$st] + 1 : 1;
        }
        $qualifying = count($book);
        $expClos = (int)round($qualifying * $conv);
        $asv = round($expClos * $ticket, 1);
        $summary = array(
            'qualifying_leads'      => $qualifying,
            'send_t1'               => $t1,
            'send_t2'               => $t2,
            'verify'                => $vf,
            'bds_engaged'           => count($byBd),
            'stage_mix'             => $stageMix,
            'conversion_assumption' => $conv,
            'expected_closures'     => $expClos,
            'avg_ticket_cr'         => $ticket,
            'indicative_asv_cr'     => $asv,
        );

        return array(
            'as_of'   => date('Y-m-d'),
            'summary' => $summary,
            'by_bd'   => $byBd,
            'book'    => $book,
            'model'   => array(
                'sprint_stages_cstatus'   => array(6, 7, 9, 12, 13),
                'sla_max_days_by_cstatus' => $slaMap,
                'tier_rule'               => 'SEND T1 = proposal out AND cstatus IN (7,9,13); SEND T2 = proposal out (6/12) OR purpose achieved; VERIFY = otherwise',
                'rag'                     => array('SEND T1' => 'RED', 'SEND T2' => 'AMBER', 'VERIFY' => 'GREEN'),
                'capacity_cap_per_bd'     => '8-12 priority leads',
                'conversion_default'      => 0.35,
                'avg_ticket_default_cr'   => 0.25,
            ),
        );
    }

    // touchpoint_model -> STATIC reference: the cold->close touchpoint model
    // (13 SLA statuses with touch count + SLA max days + owner), the 3-week
    // quarter-end sprint cadence, and the BD capacity levers. Port of the
    // BD-Capacity workbook sheets 2 (Touchpoint Model) and 5 (Sprint Plan).
    // No DB access; pure reference constants.
    public function touchpoint_model($f = []) {
        return array(
            'headline' => array(
                'touchpoints_to_close' => '12-15 across 13 SLA statuses (cold -> MoU)',
                'sprint_touchpoints'   => '5-7 (~2/week over 3 weeks; stages 5-8 only)',
            ),
            'stages' => array(
                array('seq' => 1, 'status' => 'Open',              'touchpoint' => 'Identify the right person (RP)',          'touch_count' => 1, 'sla_max_days' => 7,  'owner' => 'BD'),
                array('seq' => 2, 'status' => 'OPEN RPEM',         'touchpoint' => 'First contact - intro call/email to RP',  'touch_count' => 1, 'sla_max_days' => 5,  'owner' => 'BD'),
                array('seq' => 3, 'status' => 'Reachout',          'touchpoint' => 'Connect call + schedule the meeting',     'touch_count' => 2, 'sla_max_days' => 14, 'owner' => 'BD'),
                array('seq' => 4, 'status' => 'Tentative',         'touchpoint' => 'Discovery meeting + MoM + 2 follow-ups',  'touch_count' => 3, 'sla_max_days' => 30, 'owner' => 'BD'),
                array('seq' => 5, 'status' => 'Positive-NAP',      'touchpoint' => 'Proposal sent + 1 follow-up call',        'touch_count' => 2, 'sla_max_days' => 7,  'owner' => 'CM'),
                array('seq' => 6, 'status' => 'Positive',          'touchpoint' => 'Joint BD+CM call + objection handling',   'touch_count' => 2, 'sla_max_days' => 21, 'owner' => 'BD+CM'),
                array('seq' => 7, 'status' => 'Very Positive-NAP', 'touchpoint' => 'RM calling / decision-maker push',        'touch_count' => 1, 'sla_max_days' => 5,  'owner' => 'RM'),
                array('seq' => 8, 'status' => 'Very Positive',     'touchpoint' => 'Final next-steps + verdict push',         'touch_count' => 1, 'sla_max_days' => 14, 'owner' => 'RM+CM'),
                array('seq' => 9, 'status' => 'Closure',           'touchpoint' => 'MoU / Work Order received',               'touch_count' => 1, 'sla_max_days' => 7,  'owner' => 'CM+Ops'),
            ),
            'sprint_cadence' => array(
                array('week' => 'Week 1', 'action' => 'Re-engage call + joint BD+CM call',       'touches' => 2,     'owner' => 'BD+CM', 'exit' => 'Blocker named; decision owner identified'),
                array('week' => 'Week 2', 'action' => 'Objection/clarification + RM push',        'touches' => 2,     'owner' => 'BD+RM', 'exit' => 'Last block removed; verdict date agreed'),
                array('week' => 'Week 3', 'action' => 'Close-or-kill verdict; collect MoU/WO',    'touches' => '1-3', 'owner' => 'RM+CM', 'exit' => 'MoU signed OR released to next-Q'),
            ),
            'capacity' => array(
                'outreaches_per_bd_per_day'     => 3.3,
                'working_days_per_year'         => 242,
                'priority_leads_per_bd_sprint'  => '8-12',
                'sprint_touches_per_bd_3wk'     => '50-60',
                'expected_sprint_conversion'    => '30-40%',
                'sprint_closures_per_bd'        => '3-5',
            ),
        );
    }


    // company_creation_by_bd -> per-BD company/lead creation in a date window
    // (port of GetCompanyInfoCreateBetweenDate). Counts new leads created
    // (init_call.new_lead=1, after_task linked to a completed tblcallevents row
    // tce.nextCFID!=0) per BD, split by admin-approval state and by the linked
    // task's action type (barg-in=4, research=10). Manager scope reproduces the
    // source $text ladder (parameterised). Output columns match the source.
    public function company_creation_by_bd($f = []) {
        $sd = !empty($f['date_from']) ? substr($f['date_from'], 0, 10) : date('Y-m-01');
        $ed = !empty($f['date_to'])   ? substr($f['date_to'], 0, 10)   : date('Y-m-d');
        $limit = isset($f['limit']) ? (int)$f['limit'] : 500;

        $binds = [$sd . ' 00:00:00', $ed . ' 23:59:59'];
        $scope = "";
        if (!empty($f['manager_id'])) {
            $m = (int)$f['manager_id'];
            $u = $this->db->query("SELECT type_id FROM user_details WHERE user_id = ? LIMIT 1", [$m])->row_array();
            $t = $u ? (int)$u['type_id'] : 0;
            switch ($t) {
                case 1:  $scope = " AND u1.sadmin_id = ?";                        $binds[] = $m; break;
                case 2:  $scope = " AND u1.admin_id = ?";                         $binds[] = $m; break;
                case 3:  $scope = " AND u1.user_id = ?";                          $binds[] = $m; break;
                case 13: $scope = " AND (u1.user_id = ? OR u1.aadmin = ?)";       $binds[] = $m; $binds[] = $m; break;
                case 4:  $scope = " AND (u1.user_id = ? OR u1.pst_co = ?)";       $binds[] = $m; $binds[] = $m; break;
                case 19: $scope = " AND u1.ash_nae_co = ?";                       $binds[] = $m; break;
                case 20: $scope = " AND u1.ash_w_co = ?";                         $binds[] = $m; break;
                case 21: $scope = " AND u1.ash_s_co = ?";                         $binds[] = $m; break;
                case 22: $scope = " AND (u1.user_id = ? OR u1.rm_east_co = ?)";   $binds[] = $m; $binds[] = $m; break;
                case 23: $scope = " AND (u1.user_id = ? OR u1.rm_north_co = ?)";  $binds[] = $m; $binds[] = $m; break;
                case 24: $scope = " AND (u1.user_id = ? OR u1.acm_co = ?)";       $binds[] = $m; $binds[] = $m; break;
                case 15: $scope = " AND u1.sales_co = ?";                         $binds[] = $m; break;
                default: $scope = " AND u1.user_id = ?";                          $binds[] = $m; break;
            }
        }

        $sql = "SELECT
            u1.name AS user_name, u1.user_id AS tar_user_id, ut.name AS type_name,
            COUNT(ic.id) AS total_create_company,
            COUNT(CASE WHEN ic.is_admin_approved = 1 THEN ic.id END) AS total_approved,
            COUNT(CASE WHEN ic.is_admin_approved = 0 THEN ic.id END) AS pending_for_approved,
            COUNT(CASE WHEN ic.is_admin_approved = 2 THEN ic.id END) AS need_to_update,
            COUNT(CASE WHEN tce.actiontype_id = 4  THEN ic.id END) AS form_barg_in_meeting,
            COUNT(CASE WHEN tce.actiontype_id = 10 THEN ic.id END) AS form_research,
            u2.name AS admin_name
        FROM user_details u1
        LEFT JOIN init_call ic ON ic.mainbd = u1.user_id
        LEFT JOIN user_details u2 ON u2.user_id = (
            CASE
                WHEN u1.type_id = 3 THEN CASE
                    WHEN u1.acm_co IS NOT NULL AND u1.acm_co != '' THEN u1.acm_co
                    WHEN u1.aadmin IS NOT NULL AND u1.aadmin != '' THEN u1.aadmin
                    ELSE u1.rm_east_co END
                WHEN u1.type_id = 13 THEN CASE
                    WHEN u1.rm_east_co IS NOT NULL AND u1.rm_east_co != '' THEN u1.rm_east_co
                    ELSE u1.aadmin END
                WHEN u1.type_id = 24 THEN u1.aadmin
                WHEN u1.type_id = 22 THEN u1.sales_co
            END)
        LEFT JOIN user_type ut ON ut.id = u1.type_id
        LEFT JOIN tblcallevents tce ON tce.id = ic.after_task
        WHERE ic.created_at BETWEEN ? AND ?
          AND ic.mainbd IS NOT NULL
          AND ic.new_lead = 1
          AND ic.after_task != 0
          AND tce.nextCFID != 0
          $scope
        GROUP BY u1.user_id, u1.name, u2.name
        HAVING total_create_company != 0
        LIMIT $limit";
        $rows = $this->db->query($sql, $binds)->result_array();

        $tot = ['bds' => count($rows), 'total_create_company' => 0, 'total_approved' => 0,
                'pending_for_approved' => 0, 'need_to_update' => 0,
                'form_barg_in_meeting' => 0, 'form_research' => 0];
        foreach ($rows as $r) {
            $tot['total_create_company'] += (int)$r['total_create_company'];
            $tot['total_approved']       += (int)$r['total_approved'];
            $tot['pending_for_approved'] += (int)$r['pending_for_approved'];
            $tot['need_to_update']       += (int)$r['need_to_update'];
            $tot['form_barg_in_meeting'] += (int)$r['form_barg_in_meeting'];
            $tot['form_research']        += (int)$r['form_research'];
        }
        return ['date_from' => $sd, 'date_to' => $ed, 'summary' => $tot, 'rows' => $rows];
    }

}
