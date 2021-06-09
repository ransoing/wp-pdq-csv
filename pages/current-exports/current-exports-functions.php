<?php

namespace pdqcsv\pages\current_exports;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

function enqueueScriptsAndStyles() {
    // enqueue jqueryui theme
    require_once( __DIR__ . '/../common-functions.php' );
    \pdqcsv\pages\enqueueCommonCss();

    // enqueue files for this page
    wp_enqueue_script( 'pdqcsv-page-current-exports', plugin_dir_url(__FILE__) . 'current-exports.js', [], PDQCSV_VERSION );
    wp_enqueue_style( 'pdqcsv-page-current-exports', plugin_dir_url(__FILE__) . 'current-exports.css', ['pdqcsv-jquery-ui'], PDQCSV_VERSION );
}
