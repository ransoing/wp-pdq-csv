<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// add dependencies
require_once( __DIR__ . '/../combobox/combobox.enqueue.php' );

// enqueue export-settings-dialog files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvExportSettingsDialogHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/export-settings-dialog.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'pdqcsv-export-settings-dialog', plugin_dir_url(__FILE__) . 'export-settings-dialog.js', ['pdqcsv-combobox','jquery-ui-dialog','jquery-ui-mouse','jquery-ui-tabs'], PDQCSV_VERSION );

// add the css
wp_enqueue_style( 'pdqcsv-dialog', plugin_dir_url(__FILE__) . '../dialog.css', ['pdqcsv-jquery-ui-theme'], PDQCSV_VERSION );
wp_enqueue_style( 'pdqcsv-spinner', plugin_dir_url(__FILE__) . '../spinner.css', [], PDQCSV_VERSION );
wp_enqueue_style( 'pdqcsv-export-settings-dialog', plugin_dir_url(__FILE__) . 'export-settings-dialog.css', [], PDQCSV_VERSION );
