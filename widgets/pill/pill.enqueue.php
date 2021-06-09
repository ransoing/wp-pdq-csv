<?php

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

// enqueue pill files

// make the HTML template available to the javascript
?>
<script>
    var pdqcsvPillHtml = <?php echo json_encode( file_get_contents(__DIR__ . '/pill.html') ) ?>;
</script>
<?php

// add the js
wp_enqueue_script( 'pdqcsv-pill', plugin_dir_url(__FILE__) . 'pill.js' );

// add the css
wp_enqueue_style( 'pdqcsv-pill', plugin_dir_url(__FILE__) . 'pill.css' );
