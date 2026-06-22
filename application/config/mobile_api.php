<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Mobile API token signing secret.
| Override via environment variable STEM_MOBILE_API_SECRET on production.
*/
$config['mobile_api_secret'] = getenv('STEM_MOBILE_API_SECRET') ?: 'stem-mobile-api-change-me-in-production';
$config['mobile_api_token_ttl'] = 60 * 60 * 24 * 30; // 30 days
