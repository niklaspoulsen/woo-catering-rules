<?php

class WCR_Popup {

    public function __construct() {

        add_action('wp_enqueue_scripts', [$this,'assets']);
        add_action('wp_footer', [$this,'render_popup']);

    }

    public function assets(){

        wp_enqueue_script(
            'wcr-popup',
            WCR_URL . 'assets/js/popup.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_enqueue_style(
            'wcr-popup',
            WCR_URL . 'assets/css/popup.css'
        );

    }

    public function render_popup(){

        if(!class_exists('WooCommerce')) return;

        include WCR_PATH . 'templates/popup.php';

    }

}
