<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// add dependencies
require_once( __DIR__ . '/../multi-fields/multi-fields.enqueue.php' );
require_once( __DIR__ . '/../multi-filters/multi-filters.enqueue.php' );
require_once( __DIR__ . '/../../widgets/export-settings-dialog/export-settings-dialog.enqueue.php' );

// enqueue csv-builder-form files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvCsvBuilderFormHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/csv-builder-form.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'momentjs', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js' );
wp_enqueue_script( 'pdqcsv-csv-builder-form', plugin_dir_url(__FILE__) . 'csv-builder-form.js', ['pdqcsv-multi-fields', 'pdqcsv-multi-filters', 'pdqcsv-export-settings-dialog', 'jquery-ui-button', 'jquery-ui-dialog', 'momentjs'], PDQCSV_VERSION );

// add the css
wp_enqueue_style( 'pdqcsv-spinner', plugin_dir_url(__FILE__) . '../spinner.css', [], PDQCSV_VERSION );
wp_enqueue_style( 'pdqcsv-csv-builder-form', plugin_dir_url(__FILE__) . 'csv-builder-form.css', [], PDQCSV_VERSION );
