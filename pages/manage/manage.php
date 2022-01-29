<?php

namespace pdqcsv\pages\manage;
use pdqcsv\pages as common;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../common-functions.php' );
require_once( __DIR__ . '/manage-functions.php' );
require_once( __DIR__ . '/../../util/db.php' );

?>

<div class="wrap">
	<?php
	common\outputHeader( 'Manage saved export settings' );

	// get a list of all export settings
	$records = \pdqcsv\util\getAllDbSettingsRecords( ['id', 'name', 'created_by_user_id', 'last_modified'] );

	// get usernames associated with the user ids
	$usernames = array();
	foreach( $records as $record ) {
		if ( !isset($usernames[$record->created_by_user_id]) ) {
			$usernames[$record->created_by_user_id] = get_userdata( $record->created_by_user_id )->display_name;
		}

		$record->created_by_user = $usernames[$record->created_by_user_id];
	}

	// show a table of all export settings
	?>

	<table class="wp-list-table widefat fixed striped pdqcsv">
		<thead>
			<tr>
				<th>Name</th>
				<th>Created by</th>
				<th>Last modified</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( sizeof($records) === 0 ) : ?>
			<tr><td colspan="3">No saved export settings found.</td></tr>
			<?php else :
			foreach( $records as $record ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $record->name ) ?></strong><br>
					<div class="row-actions">
						<span class="export"><a href="./admin.php?page=wp-pdq-csv&export-settings=<?php echo esc_attr($record->id) ?>">Load export settings</a> | </span>
						<span class="delete"><a href="javascript:void(0)" class="submitdelete" data-id="<?php echo esc_attr($record->id) ?>">Delete</a></span>
					</div>
				</td>
				<td><?php echo $record->created_by_user ? esc_html( $record->created_by_user ) : '(User no longer exists)' ?></td>
				<td><?php echo esc_html( $record->last_modified ) ?></td>
			</tr>
			<?php endforeach; endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th>Name</th>
				<th>Created by</th>
				<th>Last modified</th>
			</tr>
		</tfoot>
	</table>

</div>
