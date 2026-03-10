<?php

if (!defined('ABSPATH')) exit;

class WCR_Admin {

public function __construct(){

add_action('admin_menu', [$this,'menu']);

}

public function menu(){

add_submenu_page(
'woocommerce',
'Catering Rules',
'Catering Rules',
'manage_options',
'wcr-settings',
[$this,'page']
);

}

public function page(){

?>

<div class="wrap">

<h1>Catering regler</h1>

<p>Her kan du styre lukkedage.</p>

<form method="post">

<textarea name="wcr_closed_days" style="width:400px;height:200px;"></textarea>

<p>

<button class="button button-primary">

Gem

</button>

</p>

</form>

</div>

<?php

}

}
