<?php
/**
 * @package PDQ CSV
 * @version 1.0.1
 */
/*
Plugin Name: PDQ CSV
Description: A pretty darn quick CSV exporter
Version: 1.0.1
Author: Ransom Christofferson (on behalf of Truckers Against Trafficking)
Author URI: https://ransomchristofferson.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/


namespace pdqcsv;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

/** The user capability required to use this plugin */
define( 'PDQCSV_REQUIRED_PERMISSION', 'export' );
/** The name of the plugin's page as it appears in the URL */
define( 'PDQCSV_EXPORT_PAGE_SLUG', 'wp-pdq-csv' );
/** The name of the plugin's export settings page as it appears in the URL */
define( 'PDQCSV_MANAGE_PAGE_SLUG', 'wp-pdq-csv-manage' );
/** The name of the page that shows the current exports, as it appears in the URL */
define( 'PDQCSV_CURRENT_EXPORTS_PAGE_SLUG', 'wp-pdq-csv-current-exports' );
/** The name of the page that shows debug info, as it appears in the URL */
define( 'PDQCSV_DEBUG_PAGE_SLUG', 'wp-pdq-csv-info' );
/** A page slug to use to mark whether the user intends to download a CSV. This is not actually associated with a page, since it directly sends CSV data */
define( 'PDQCSV_DOWNLOAD_CSV_SLUG', 'wp-pdq-csv-download-csv' );
/** The current version number of the plugin */
define( 'PDQCSV_VERSION', '1.0.1' );
/** A GET parameter used to determine whether the plugin is currently exporting */
define( 'PDQCSV_EXPORTING_QUERY_KEY', 'is-exporting' );


// check whether the user is performing a CSV download. This must be done on 'init', because otherwise wordpress will output content,
// and that will mess up the file download
add_action( 'init', function() {
	if ( is_admin() && $_GET['page'] === PDQCSV_DOWNLOAD_CSV_SLUG && current_user_can(PDQCSV_REQUIRED_PERMISSION) ) {
		require( __DIR__ . '/util/download-csv.php' );
		util\downloadCsv();
		die(); // dont' output anything else after sending csv data!
	}
});

add_action( 'admin_menu', function() {
	// add a link to the admin page in the menu
	$adminPage = add_menu_page( 'WP PDQ CSV', 'WP PDQ CSV', PDQCSV_REQUIRED_PERMISSION, PDQCSV_EXPORT_PAGE_SLUG, function() {
		require_once( "pages/export/export.php" );
	}, 'dashicons-editor-table' );

	// add a submenu link which goes to the same page as the top-level menu item. Use no handler because this is already covered by the add_menu_page call
	add_submenu_page( PDQCSV_EXPORT_PAGE_SLUG, 'Export data', 'Export data', PDQCSV_REQUIRED_PERMISSION, PDQCSV_EXPORT_PAGE_SLUG );

	// add actions that apply to this page, when the page is loaded
	add_action( "load-{$adminPage}", function() {
		require_once( "pages/export/export-functions.php" );
		add_action( 'admin_enqueue_scripts', "\pdqcsv\\pages\\export\\enqueueScriptsAndStyles" );
	});

	// add submenu links for other pages
	$pages = [
		(object)[
			'slug' => PDQCSV_MANAGE_PAGE_SLUG,
			'title' => 'Manage saved export settings',
			'filePathSegment' => 'manage'
		], (object)[
			'slug' => PDQCSV_CURRENT_EXPORTS_PAGE_SLUG,
			'title' => 'Current exports',
			'filePathSegment' => 'current-exports'
		], (object)[
			'slug' => PDQCSV_DEBUG_PAGE_SLUG,
			'title' => 'Debug info',
			'filePathSegment' => 'info'
		]
	];
	foreach( $pages as $page ) {
		$pageIdentifier = add_submenu_page( PDQCSV_EXPORT_PAGE_SLUG, $page->title, $page->title, PDQCSV_REQUIRED_PERMISSION, $page->slug, function() use ($page) {
			require_once( "pages/{$page->filePathSegment}/{$page->filePathSegment}.php" );
		});
		add_action( "load-{$pageIdentifier}", function() use ($page) {
			require_once( "pages/{$page->filePathSegment}/{$page->filePathSegment}-functions.php" );
			$namespace = str_replace( '-', '_', $page->filePathSegment );
			add_action( 'admin_enqueue_scripts', "\pdqcsv\\pages\\{$namespace}\\enqueueScriptsAndStyles" );
		});
	}
	
});


// register ajax functions, but only if the user has the proper permissions.
// Only require the needed files when absolutely necessary to avoid slowing down wordpress
add_action( 'init', function() {
	if ( wp_doing_ajax() && current_user_can(PDQCSV_REQUIRED_PERMISSION) ) {
		// create an array to easily define ajax actions. Each element in the array is: [ ajax-action-name, required-php-file, function-name ]
		$actions = [
			[ 'wp_ajax_pdqcsv_getUserFields',  			__DIR__ . '/ajax/get-fields.php', 		'\pdqcsv\ajax\getUserFields' ],
			[ 'wp_ajax_pdqcsv_getPostTypeFields', 		__DIR__ . '/ajax/get-fields.php', 		'\pdqcsv\ajax\getPostTypeFields' ],
			[ 'wp_ajax_pdqcsv_getCommentFields', 		__DIR__ . '/ajax/get-fields.php', 		'\pdqcsv\ajax\getCommentFields' ],
			[ 'wp_ajax_pdqcsv_getTaxonomyFields', 		__DIR__ . '/ajax/get-fields.php', 		'\pdqcsv\ajax\getTaxonomyFields' ],
			[ 'wp_ajax_pdqcsv_getExampleValues',		__DIR__ . '/ajax/get-fields.php',		'\pdqcsv\ajax\getExampleValues' ],
			[ 'wp_ajax_pdqcsv_getExportSettings', 		__DIR__ . '/ajax/export-settings.php',	'\pdqcsv\ajax\getExportSettings' ],
			[ 'wp_ajax_pdqcsv_getExportSettingDetails', __DIR__ . '/ajax/export-settings.php',	'\pdqcsv\ajax\getExportSettingDetails' ],
			[ 'wp_ajax_pdqcsv_saveExportSettings',		__DIR__ . '/ajax/export-settings.php',	'\pdqcsv\ajax\saveExportSettings' ],
			[ 'wp_ajax_pdqcsv_deleteExportSetting',		__DIR__ . '/ajax/export-settings.php',	'\pdqcsv\ajax\deleteExportSetting' ],
			[ 'wp_ajax_pdqcsv_export',					__DIR__ . '/ajax/export.php',			'\pdqcsv\ajax\export' ],
			[ 'wp_ajax_pdqcsv_getCurrentExports',		__DIR__ . '/ajax/export.php',			'\pdqcsv\ajax\getCurrentExports' ],
			[ 'wp_ajax_pdqcsv_getExportById',			__DIR__ . '/ajax/export.php',			'\pdqcsv\ajax\getExportById' ],
			[ 'wp_ajax_pdqcsv_deleteExport',			__DIR__ . '/ajax/export.php',			'\pdqcsv\ajax\deleteExport' ]
		];

		foreach( $actions as $action ) {
			add_action( $action[0], function() use ($action) {
				require_once( $action[1] );
				call_user_func( $action[2] );
			});
		}
	}
});

// create hooks for installing/uninstalling

function onActivation() {
	ob_start();
	require_once( __DIR__ . '/util/db.php' );
	util\createDbSettingsTable();
	util\createDbExportsTable();
	update_option( 'pdqcsv_activation_error', ob_get_contents() );
}
register_activation_hook( __FILE__, '\pdqcsv\onActivation' );

function onUninstall() {
	require_once( __DIR__ . '/util/db.php' );
	util\removeDbSettingsTable();
	util\removeDbExportsTable();
}
register_uninstall_hook( __FILE__, '\pdqcsv\onUninstall' );
