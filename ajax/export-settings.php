<?php

namespace pdqcsv\ajax;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../util/db.php' );

/**
 * Gets basic info on all saved export settings.
 * Returns an object in this format:
 * [
 *   {
 *     'id': number,
 *     'name': string
 *   }, {
 *     ...
 *   }, ...
 * ]
 */
function getExportSettings() {
    echo json_encode( \pdqcsv\util\getAllDbSettingsRecords(['id', 'name']) );
    wp_die();
}

/**
 * Gets details on a single instance of export settings.
 * 
 * Requires GET param: `id`
 * 
 * Returns an object in this format:
 * {
 *   id: number,
 *   name: string,
 *   version: string,
 *   created_by_user_id: number,
 *   last_modified: string,
 *   settings: {
 *      objectType: string,
 *      fields: {
 *          field: string,
 *          csvLabel: string
 *      }[],
 *      filters: {
 *          field: string,
 *          rule: string,
 *          value: string,
 *          cast: string
 *      }[]
 *   }
 * }
 */
function getExportSettingDetails() {
    echo json_encode( \pdqcsv\util\getDbSettingsRecordById($_GET['id']) );
    wp_die();
}

/** Saves export settings to the database.
 * 
 * Requires JSON to be passed via POST - the JSON object should be the same format as what is returned by `getExportSettingDetails`.
 * If `id` is undefined, it creates a new entry, overwriting any provided values. Otherwise it overwrites an old entry (then only `name` and `settings` are required)
 */
function saveExportSettings() {
    $body = json_decode( file_get_contents('php://input') );
    if ( $body === NULL ) {
        throw new \Exception( 'Request body not formatted properly' );
    }
    if ( isset($body->id) ) {
        $body->last_modified = date( 'Y-m-d H:i:s' );
        \pdqcsv\util\updateDbSettingsRecord( $body );
    } else if ( isset($body->name) && isset($body->settings) ) {
        $body->version = PDQCSV_VERSION;
        $body->created_by_user_id = get_current_user_id();
        $body->last_modified = date( 'Y-m-d H:i:s' );
        \pdqcsv\util\addnewDbSettingsRecord( $body );
    }
    wp_die();
}

/** Deletes one export setting record from the database.
 * 
 * Requires POST param: `id`. Expects POST data to be in x-form-www-urlencoded format.
 */
function deleteExportSetting() {
    \pdqcsv\util\deleteDbSettingsRecord( sanitize_text_field($_POST['id']) );
    wp_die();
}
