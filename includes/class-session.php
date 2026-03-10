<?php

if (!defined('ABSPATH')) exit;

class WCR_Session {

public function __construct(){

add_action('init', [$this,'capture']);

}

public function capture(){

if(!function_exists('WC')) return;

if(isset($_POST['wcr_delivery_date']))
WC()->session->set('wcr_delivery_date', sanitize_text_field($_POST['wcr_delivery_date']));

if(isset($_POST['wcr_delivery_time']))
WC()->session->set('wcr_delivery_time', sanitize_text_field($_POST['wcr_delivery_time']));

}

}
