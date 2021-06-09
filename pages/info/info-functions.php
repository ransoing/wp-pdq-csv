<?php

namespace pdqcsv\pages\info;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

function enqueueScriptsAndStyles() {
    // enqueue jqueryui theme
    require_once( __DIR__ . '/../common-functions.php' );
    \pdqcsv\pages\enqueueCommonCss();

    // enqueue files for this page
    wp_enqueue_script( 'pdqcsv-page-info', plugin_dir_url(__FILE__) . 'info.js' );
    wp_enqueue_style( 'pdqcsv-page-info', plugin_dir_url(__FILE__) . 'info.css', ['pdqcsv-jquery-ui'] );
}
