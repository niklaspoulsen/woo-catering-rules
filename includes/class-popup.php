<?php

if (!defined('ABSPATH')) exit;

class WCR_Popup {

public function __construct(){

add_action('wp_enqueue_scripts', [$this,'assets']);
add_action('wp_footer', [$this,'popup']);

}

public function assets(){

wp_enqueue_style(
'wcr-style',
WCR_URL . 'assets/style.css'
);

wp_enqueue_script(
'wcr-js',
WCR_URL . 'assets/script.js',
['jquery'],
null,
true
);

}

public function popup(){

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

<input
type="text"
name="wcr_delivery_date"
class="wcr-datepicker"
value="<?php echo esc_attr($date); ?>"
placeholder="dd/mm/yyyy"
/>

<label>Tid</label>

<select name="wcr_delivery_time">

<option value="">Vælg tidspunkt</option>

<?php

$times=[
"08:00","08:15","08:30","08:45",
"09:00","09:15","09:30","09:45",
"10:00","10:15","10:30","10:45",
"11:00","11:15","11:30","11:45",
"12:00","12:15","12:30","12:45",
"13:00","13:15","13:30","13:45",
"14:00","14:15","14:30","14:45",
"15:00","15:15","15:30","15:45",
"16:00"
];

foreach($times as $t){

$sel = ($time==$t) ? "selected" : "";

echo "<option value='$t' $sel>$t</option>";

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
