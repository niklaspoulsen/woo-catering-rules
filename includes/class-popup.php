<?php
if (!defined('ABSPATH')) exit;

class WCR_Popup {

function __construct(){

add_action('wp_enqueue_scripts', [$this,'assets']);
add_action('wp_footer', [$this,'render']);

}

function assets(){

wp_enqueue_style('wcr-style',WCR_URL.'assets/style.css');
wp_enqueue_script('wcr-js',WCR_URL.'assets/script.js',['jquery'],null,true);

}

function render(){

if(!is_shop() && !is_product()) return;

$date = WC()->session->get('wcr_delivery_date');
$time = WC()->session->get('wcr_delivery_time');

?>

<div id="wcr-popup">

<div class="wcr-overlay"></div>

<div class="wcr-modal">

<h2>Vælg leveringstid</h2>

<form method="post">

<label>Dato</label>

<input type="text" name="wcr_delivery_date" value="<?php echo esc_attr($date); ?>" placeholder="dd/mm/yyyy">

<label>Tid</label>

<select name="wcr_delivery_time">

<option value="">Vælg</option>

<?php

for($h=8;$h<=16;$h++){

foreach(["00","15","30","45"] as $m){

$t=sprintf("%02d:%s",$h,$m);

$sel=($time==$t)?"selected":"";

echo "<option value='$t' $sel>$t</option>";

}

}

?>

</select>

<button type="submit">Færdig</button>

</form>

</div>

</div>

<?php

}

}
