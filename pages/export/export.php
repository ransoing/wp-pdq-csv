<?php

namespace pdqcsv\pages\export;
use pdqcsv\pages as common;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../common-functions.php' );
require_once( __DIR__ . '/export-functions.php' );
require_once( __DIR__ . '/../../util/db.php' )
?>

<script>
	var pdqcsvExportStatuses = <?php echo json_encode( \pdqcsv\util\getExportStatuses() ) ?>;
</script>

<div class="wrap">
    <?php common\outputHeader( 'Export data' ) ?>

    <div class="pdqcsv-settings-loading" style="display: none">
        <p>Restoring export settings...</p>
        <div class="lds-hourglass"></div>
    </div>

    <p class="pdqcsv-settings-error" style="display: none">Error: couldn't load the export settings.</p>

    <div class="pdqcsv-export-content" style="display: none">
        <div class="pdqcsv-start-choices">
            <div class="ui-widget">
                <label>Choose object type to export</label><br>
                <select name="object-type" data-combobox>
                    <optgroup label="Standard items">
                        <option value="post" data-singular-name="Post">Posts</option>
                        <option value="page" data-singular-name="Page">Pages</option>
                        <option value="user" data-singular-name="User">Users</option>
                        <option value="attachment" data-singular-name="Media">Media</option>
                        <option value="taxonomy" data-singular-name="Taxonomy">Taxonomies</option>
                        <option value="comment" data-singular-name="Comment">Comments</option>
                    </optgroup>
                    <optgroup label="Other items">
                        <?php displayOptionsFromPostTypes( getPostTypes(true), ['post','page','user','attachment','taxonomy','comment'] ) ?>
                    </optgroup>
                    <optgroup label="Custom post types">
                        <?php displayOptionsFromPostTypes( getPostTypes(false) ) ?>
                    </optgroup>
                    <!-- <optgroup label="Other">
                        <option value="pdqcsv-tables" data-singular-name="Table">Database tables</option>
                    </optgroup> -->
                </select>
            </div>
            <div class="pdqcsv-or">&ndash;OR&ndash;</div>
            <div>
                <button class="button pdqcsv-load-settings">Load saved export settings</button>
            </div>
        </div>

        <div id="builder-form-wrapper"></div>
    </div>

</div>
