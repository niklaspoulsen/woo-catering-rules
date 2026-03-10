<?php

if (!defined('ABSPATH')) exit;

class WCR_Order {

public function __construct(){

add_action(
'woocommerce_checkout_create_order',
[$this,'save'],
10,
2
);

}

public function save($order){

$date = WC()->session->get('wcr_delivery_date');
$time = WC()->session->get('wcr_delivery_time');

if($date)
$order->update_meta_data('_delivery_date',$date);

if($time)
$order->update_meta_data('_delivery_time',$time);

}

}
