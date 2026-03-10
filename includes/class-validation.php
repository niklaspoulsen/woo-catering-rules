<?php
if (!defined('ABSPATH')) exit;

class WCR_Validation {

function __construct(){

add_filter('woocommerce_add_to_cart_validation',[$this,'validate'],10,3);

}

function validate($passed,$product_id,$qty){

$date=WC()->session->get('wcr_delivery_date');
$time=WC()->session->get('wcr_delivery_time');

if(!$date){

wc_add_notice("Vælg leveringsdato","error");
return false;

}

if(!$time){

wc_add_notice("Vælg leveringstid","error");
return false;

}

return $passed;

}

}
