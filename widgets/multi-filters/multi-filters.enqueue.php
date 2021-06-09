<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// add dependencies
require_once( __DIR__ . '/../combobox/combobox.enqueue.php' );
require_once( __DIR__ . '/../filter-inputs/filter-inputs.enqueue.php' );

// enqueue multi-filters files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvMultiFiltersHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/multi-filters.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'pdqcsv-multi-filters', plugin_dir_url(__FILE__) . 'multi-filters.js', ['pdqcsv-combobox','pdqcsv-filter-inputs','jquery-ui-dialog','jquery-ui-button','jquery-ui-mouse'] );

// add the css
wp_enqueue_style( 'pdqcsv-dialog', plugin_dir_url(__FILE__) . '../dialog.css', ['pdqcsv-jquery-ui-theme'] );
wp_enqueue_style( 'pdqcsv-multi-filters', plugin_dir_url(__FILE__) . 'multi-filters.css' );
