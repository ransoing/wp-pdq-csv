<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvComboboxHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/combobox.html') ) ?>;
</script>
<?php

// add the js
$jqueryUiFeatures = array( 'jquery-ui-widget', 'jquery-ui-autocomplete', 'jquery-ui-tooltip' );
wp_enqueue_script( 'pdqcsv-combobox', plugin_dir_url(__FILE__) . 'combobox.js', $jqueryUiFeatures );

// add the css
wp_enqueue_style( 'pdqcsv-combobox', plugin_dir_url(__FILE__) . 'combobox.css' );
