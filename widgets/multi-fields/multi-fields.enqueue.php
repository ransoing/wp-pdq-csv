<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// add dependencies
require_once( __DIR__ . '/../combobox/combobox.enqueue.php' );
require_once( __DIR__ . '/../pill/pill.enqueue.php' );

// enqueue multi-fields files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvMultiFieldsHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/multi-fields.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'pdqcsv-multi-fields', plugin_dir_url(__FILE__) . 'multi-fields.js', ['pdqcsv-combobox','pdqcsv-pill','jquery-ui-dialog','jquery-ui-button','jquery-ui-mouse','jquery-ui-sortable'] );

// add the css
wp_enqueue_style( 'pdqcsv-dialog', plugin_dir_url(__FILE__) . '../dialog.css', ['pdqcsv-jquery-ui-theme'] );
wp_enqueue_style( 'pdqcsv-multi-fields', plugin_dir_url(__FILE__) . 'multi-fields.css' );
