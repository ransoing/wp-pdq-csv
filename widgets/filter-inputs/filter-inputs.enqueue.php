<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// add dependencies
require_once( __DIR__ . '/../combobox/combobox.enqueue.php' );

// enqueue filter-inputs files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvFilterInputsHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/filter-inputs.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'pdqcsv-filter-inputs', plugin_dir_url(__FILE__) . 'filter-inputs.js', ['pdqcsv-combobox'] );

// add the css
wp_enqueue_style( 'pdqcsv-filter-inputs', plugin_dir_url(__FILE__) . 'filter-inputs.css' );
