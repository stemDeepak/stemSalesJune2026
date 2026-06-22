<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Login hotfix 2026-06-22 — must load LAST in routes.php so it wins over v28/AuthV28.
 * APK v2.26.0 calls POST /api/auth/login; v28 handler returned unauthorized.
 */
$route['api/auth/login'] = 'auth/api_login';
$route['api/login']      = 'auth/api_login';
$route['api/auth']       = 'auth/api_login';
