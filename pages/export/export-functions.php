<?php

namespace pdqcsv\pages\export;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

function enqueueScriptsAndStyles() {
    // load local dependencies
    require_once( __DIR__ . '/../../widgets/csv-builder-form/csv-builder-form.enqueue.php' );
    require_once( __DIR__ . '/../../widgets/combobox/combobox.enqueue.php' );

    // enqueue jqueryui theme
    require_once( __DIR__ . '/../common-functions.php' );
    \pdqcsv\pages\enqueueCommonCss();

    // enqueue files for this page
    wp_enqueue_script( 'pdqcsv-page-export', plugin_dir_url(__FILE__) . 'export.js', ['pdqcsv-csv-builder-form','pdqcsv-combobox'], PDQCSV_VERSION );
    wp_enqueue_style( 'pdqcsv-page-export', plugin_dir_url(__FILE__) . 'export.css', ['pdqcsv-jquery-ui'], PDQCSV_VERSION );
}

/** Takes an array of post types and outputs <option> elements */
function displayOptionsFromPostTypes( $postTypes, $exceptList = array() ) {
    foreach( $postTypes as $postType ) {
        if ( !in_array($postType->name, $exceptList) ) {
            echo "<option value='{$postType->name}' data-singlular-name='{$postType->labels->singular_name}'>{$postType->label}</option>";
        }
    }
}

/** Gets all post types, either built into wordpress core or not. Sorts the post types by name. */
function getPostTypes( $isBuiltIn ) {
    $customPostTypes = get_post_types( array('_builtin' => $isBuiltIn), 'objects' );
    usort( $customPostTypes, function($a, $b) {
        return strcmp( $a->label, $b->label );
    });

    return $customPostTypes;
}
