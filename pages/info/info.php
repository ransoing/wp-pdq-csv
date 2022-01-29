<?php

namespace pdqcsv\pages\info;
use pdqcsv\pages as common;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../common-functions.php' );
global $wpdb;
?>

<div class="wrap">
    <?php common\outputHeader( 'Info' ) ?>

    <table class="wp-list-table widefat fixed striped pdqcsv">
		<thead>
			<tr>
				<th>Property</th>
				<th>Value</th>
			</tr>
		</thead>
		<tbody>
            <tr>
                <td><strong>PDQ CSV plugin activation error</strong></td>
                <td><?php echo get_option( 'pdqcsv_activation_error' ) ?></td>
            </tr>
			<tr>
				<td><strong>PHP timeout limit</strong></td>
				<td><?php echo ini_get('max_execution_time') ?> seconds</td>
            </tr>
            <tr>
                <td><strong>PHP memory limit</strong></td>
                <td><?php echo ini_get('memory_limit') ?></td>
            </tr>
            <?php
            $mysqlVars = [
                (object)['varName' => 'max_execution_time', 'label' => 'MySQL SELECT max execution time', 'units' => 'milliseconds'],
                (object)['varName' => 'max_join_size', 'label' => 'MySQL max join size', 'units' => 'rows'],
                (object)['varName' => 'max_prepared_stmt_count', 'label' => 'MySQL max prepared statement count', 'units' => 'statements'],
                (object)['varName' => 'sql_select_limit', 'label' => 'MySQL SELECT max rows returned', 'units' => 'rows']
            ];

            foreach( $mysqlVars as $mysqlVar ) {
                ?>
                <tr>
                    <td><strong><?php echo esc_html($mysqlVar->label) ?></strong></td>
                    <td>
                        <?php
                        $result = $wpdb->get_var( "SHOW VARIABLES LIKE '{$mysqlVar->varName}'", 1 );
                        if ( empty($result) ) {
                            echo "Variable not found";
                        } else {
                            echo esc_html( "{$result} {$mysqlVar->units}" );
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
		</tbody>
		<tfoot>
			<tr>
				<th>Property</th>
				<th>Value</th>
			</tr>
		</tfoot>
	</table>

</div>
