<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Feature_flags extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Mobile_api_lib', null, 'api');
    }

    public function list()
    {
        $this->api->require_user();
        $this->api->mobile_ok(['flags' => []]);
    }
}
