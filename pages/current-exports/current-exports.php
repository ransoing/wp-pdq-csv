<?php

namespace pdqcsv\pages\current_exports;
use pdqcsv\pages as common;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../common-functions.php' );
require_once( __DIR__ . '/current-exports-functions.php' );
require_once( __DIR__ . '/../../util/db.php' );


?>

<script>
	var pdqcsvExportStatuses = <?php echo json_encode( \pdqcsv\util\getExportStatuses() ) ?>;
</script>

<div class="wrap">
	<?php
	common\outputHeader( 'Current exports' );
	?>
	<div class="notice notice-info">
	<p>Downloading a CSV also removes the information from the server.</p>
	</div>
	<?php

	// get a list of all current exports
	$records = \pdqcsv\util\getAllDbExportsRecords( true );

	// get usernames associated with the user ids
	$usernames = array();
	foreach( $records as $record ) {
		if ( !isset($usernames[$record->created_by_user_id]) ) {
			$usernames[$record->created_by_user_id] = get_userdata( $record->created_by_user_id )->display_name;
		}

		$record->created_by_user = $usernames[$record->created_by_user_id];
	}

	// show a table of all exports
	?>

	<table class="wp-list-table widefat fixed pdqcsv">
		<thead>
			<tr>
				<th>Export type</th>
				<th>Time started</th>
				<th>Created by</th>
				<th>Status</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( sizeof($records) === 0 ) : ?>
			<tr><td colspan="4">No current exports found.</td></tr>
			<?php else :
			foreach( $records as $record ) : ?>
			<tr class="export-row" data-initial-status="<?php echo esc_attr($record->status_step) ?>">
				<td>
					<strong><?php echo esc_html($record->object_type) ?></strong><br>
					<div class="row-actions">
						<span class="export-link"><a href="javascript:void(0)" class="pdqcsv-download-csv" data-id="<?php echo esc_attr($record->id) ?>">Download CSV</a> | </span>
						<span><a href="javascript:void(0)" class="pdqcsv-show-queries" data-id="<?php echo esc_attr($record->id) ?>">Show debug info</a> | </span>
						<span class="delete"><a href="javascript:void(0)" class="submitdelete" data-id="<?php echo esc_attr($record->id) ?>">Delete</a></span>
					</div>
				</td>
				<td><?php echo esc_html($record->start_time) ?></td>
				<td><?php echo $record->created_by_user ? esc_html($record->created_by_user) : '(User no longer exists)' ?></td>
				<td class="status" data-status-for="<?php echo esc_attr($record->id) ?>"></td>
			</tr>
			<tr class="debug-info-row" data-id="<?php echo esc_attr($record->id) ?>">
				<td colspan="4"><pre class="pdqcsv-info-details"><?php echo esc_textarea("# Database queries used: \n\n " . str_replace( "\\n", "\n", $record->queries ) ) ?></pre></td>
			</tr>
			<?php endforeach; endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th>Export type</th>
				<th>Time started</th>
				<th>Created by</th>
				<th>Status</th>
			</tr>
		</tfoot>
	</table>

</div>
