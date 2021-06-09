<?php
/**
 * This file handles operations done on the custom database table used by the plugin.
 */

namespace pdqcsv\util;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

define( 'PDQCSV_DB_SETTINGS_TABLE_NAME', 'pdqcsv_saved_export_settings' );
define( 'PDQCSV_DB_EXPORTS_TABLE_NAME', 'pdqcsv_exports' );

define( 'PDQCSV_EXPORT_STATUS_STARTED', 'started' );
define( 'PDQCSV_EXPORT_STATUS_MAKING_TEMP_TABLE', 'making_temp_table' );
define( 'PDQCSV_EXPORT_STATUS_FILTERING_OBJECTS', 'filtering_objects' );
define( 'PDQCSV_EXPORT_STATUS_COMPILING_RESULTS', 'compiling_results' );
define( 'PDQCSV_EXPORT_STATUS_DROPPING_TEMP_TABLE', 'dropping_temp_table' );
define( 'PDQCSV_EXPORT_STATUS_DONE', 'done' );

/**
 * Returns export status strings in the order that they happen, and friendly labels to describe what the export process
 * does right before getting that status. This makes it easy to add or remove statuses. The current friendly status description is then
 * the description for the item after the current status.
 */
function getExportStatuses() {
    return [
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_STARTED, 'description' => 'Started' ],
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_MAKING_TEMP_TABLE, 'description' => 'Preparing' ],
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_FILTERING_OBJECTS, 'description' => 'Filtering objects' ],
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_COMPILING_RESULTS, 'description' => 'Collecting data' ],
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_DROPPING_TEMP_TABLE, 'description' => 'Cleaning up' ],
        (object)[ 'statusCode' => PDQCSV_EXPORT_STATUS_DONE, 'description' => 'Finished' ]
    ];
}

/////////////////////////////////////////////////////////////////////////
// settings table functions

function getDbSettingsTableName() {
    global $wpdb;
    return $wpdb->prefix . PDQCSV_DB_SETTINGS_TABLE_NAME;
}

/** Creates the custom database table used for saving export settings */
function createDbSettingsTable() {
    global $wpdb;
    $tableName = getDbSettingsTableName();
    $query = "CREATE TABLE IF NOT EXISTS $tableName (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `version` VARCHAR(20) NOT NULL,
        `created_by_user_id` INT NOT NULL,
        `last_modified` DATETIME NOT NULL,
        `settings` MEDIUMTEXT NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET=utf8;";

    $wpdb->query( $query );
}

/** Removes the custom database table that stores saved export settings */
function removeDbSettingsTable() {
    global $wpdb;
    $tableName = getDbSettingsTableName();
    $wpdb->query( "DROP TABLE IF EXISTS $tableName" );
}

/** Gets all export settings entries. Returns only the fields named in the argument. Does not sanitize. Sorts by name. */
function getAllDbSettingsRecords( $fieldList = array() ) {
    global $wpdb;
    if ( sizeof($fieldList) === 0 ) {
        array_push( $fieldList, '*' );
    }
    $records = $wpdb->get_results( "SELECT " . join(', ', $fieldList) . " FROM " . getDbSettingsTableName() . " ORDER BY name;" );
    return fixRecordTypes( $records );
}

/** Gets all fields for a single export settings entry, identified by ID */
function getDbSettingsRecordById( int $id ) {
    global $wpdb;
    $records = $wpdb->get_results( "SELECT * FROM " . getDbSettingsTableName() . " WHERE id=" . intval($id) . ";" );
    return fixRecordTypes( $records )[0];
}

/** Deletes one record from the export settings table, identified by ID */
function deleteDbSettingsRecord( int $id ) {
    global $wpdb;
    $wpdb->query( "DELETE FROM " . getDbSettingsTableName() . " WHERE id = " . intval($id) . ";" );
}

function addnewDbSettingsRecord( $exportRecord ) {
    global $wpdb;
    $exportRecord->settings = json_encode( $exportRecord->settings );
    $wpdb->insert( getDbSettingsTableName(), (array)$exportRecord );
}

function updateDbSettingsRecord( $exportRecord ) {
    global $wpdb;
    if ( isset($exportRecord->settings) ) {
        $exportRecord->settings = json_encode( $exportRecord->settings );
    }
    $id = $exportRecord->id;
    unset( $exportRecord->id );
    $wpdb->update( getDbSettingsTableName(), (array)$exportRecord, ['id' => $id] );
}

/** When results are retrieved from the database, some types should be numbers or JSON when they are not. Fix this. */
function fixRecordTypes( &$records ) {
    foreach( $records as $record ) {
        if ( isset($record->id) ) {
            $record->id = intval( $record->id );
        }
        if ( isset($record->created_by_user_id) ) {
            $record->created_by_user_id = intval( $record->created_by_user_id );
        }
        if ( isset($record->settings) ) {
            $record->settings = json_decode( $record->settings );
        }
    }
    return $records;
}


/////////////////////////////////////////////////////////////////////////
// exports table functions

function getDbExportsTableName() {
    global $wpdb;
    return $wpdb->prefix . PDQCSV_DB_EXPORTS_TABLE_NAME;
}

function createDbExportsTable() {
    global $wpdb;
    $tableName = getDbExportsTableName();
    $query = "CREATE TABLE IF NOT EXISTS $tableName (
        `id` INT NOT NULL AUTO_INCREMENT,
        `temp_table_name` VARCHAR(100) NOT NULL,
        `results_table_name` VARCHAR(100) NOT NULL,
        `status_step` VARCHAR(100) NOT NULL,
        `created_by_user_id` INT NOT NULL,
        `object_type` VARCHAR(100) NOT NULL,
        `start_time` DATETIME NOT NULL,
        `queries` LONGTEXT NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET=utf8;";

    $wpdb->query( $query );
}

/** Removes the exports table and all tables mentioned from it (temp tables and results tables) */
function removeDbExportsTable() {
    global $wpdb;
    // get temp and results tables and drop them
    $records = getAllDbExportsRecords();
    foreach( $records as $record ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$record->temp_table_name}" );
        $wpdb->query( "DROP TABLE IF EXISTS {$record->results_table_name}" );
    }
    // drop the exports table
    $wpdb->query( "DROP TABLE IF EXISTS " . getDbExportsTableName() );
}

/** Removes a single export record */
function removeDbExportRecord( $id ) {
    global $wpdb;
    $record = getSingleDbExportsRecord( $id );
    $wpdb->query( "DROP TABLE IF EXISTS {$record->temp_table_name}" ); // this shouldn't exist but try dropping it just to be sure
    $wpdb->query( "DROP TABLE IF EXISTS {$record->results_table_name}" );
    $wpdb->delete( getDbExportsTableName(), ['id' => $id] );
}

/** Gets all export settings entries. Sorts by time. */
function getAllDbExportsRecords( $getQueries = false ) {
    global $wpdb;
    $columns = $getQueries ? '*' : '`id`, `temp_table_name`, `results_table_name`, `status_step`, `created_by_user_id`, `object_type`, `start_time`';
    return $wpdb->get_results( "SELECT {$columns} FROM " . getDbExportsTableName() . " ORDER BY start_time DESC;" );
}

/** Returns the record for one exports row, identified by ID */
function getSingleDbExportsRecord( $id ) {
    global $wpdb;
    return $wpdb->get_results( "SELECT `id`, `temp_table_name`, `results_table_name`, `status_step`, `created_by_user_id`, `object_type`, `start_time` FROM " . getDbExportsTableName() . " WHERE id = {$id};" )[0];
}

/** Creates a new entry in the exports table, and returns the data that it inserted, as an object. */
function createNewDbExportsRecord( $objectType ) {
    global $wpdb;
    $postType = get_post_type_object( $objectType );
    $randomString = substr( md5(rand()), 0, 7 ); // generate a random hexadecimal string
    $data = array(
        'temp_table_name' => $wpdb->prefix . 'pdqcsv_temp_' . $randomString,
        'results_table_name' => $wpdb->prefix . 'pdqcsv_results_' . $randomString,
        'status_step' => PDQCSV_EXPORT_STATUS_STARTED,
        'created_by_user_id' => get_current_user_id(),
        'object_type' => isset($postType) ? $postType->labels->name : $objectType,
        'start_time' => date( 'Y-m-d H:i:s' )
    );
    $wpdb->insert( getDbExportsTableName(), $data );
    $data['id'] = $wpdb->insert_id;
    return (object)$data;
}

/** For informational purposes, stores the queries to be executed in the exports table */
function setDbExportQueries( $recordId, $queriesArray ) {
    global $wpdb;
    $wpdb->update( getDbExportsTableName(), ['queries' => implode(';\n\n\n\n', $queriesArray) . ';'], ['id' => $recordId] );
}

/** Returns the query needed to change the status_step of an exports record */
function buildQueryToSetExportsRecordStep( $recordId, $newStep ) {
    return "UPDATE " . getDbExportsTableName() . " SET status_step = '{$newStep}' WHERE id = {$recordId}";
}
