<?php

namespace pdqcsv\ajax;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

require_once( __DIR__ . '/../util/db.php' );


/**
 * Gets all current exports. Returns an array of objects:
 * {
 *  id: number,
 *  temp_table_name: string,
 *  results_table_name: string,
 *  status_step: string,
 *  created_by_user_id: number,
 *  object_type: string,
 *  start_time: string
 * }[]
 */
function getCurrentExports() {
    echo json_encode( \pdqcsv\util\getAllDbExportsRecords() );
    wp_die();
}

/**
 * Gets one export record. Returns a single object, formatted the same as the objects output by `getCurrentExports`:
 * Takes one GET variable, `id`
 */
function getExportById() {
    echo json_encode( \pdqcsv\util\getSingleDbExportsRecord( intval($_GET['id']) ) );
    wp_die();
}

/**
 * Deletes one export record.
 * Requires POST param: `id`. Expects POST data to be in x-form-www-urlencoded format.
 */
function deleteExport() {
    echo json_encode( \pdqcsv\util\removeDbExportRecord( intval($_POST['id']) ) );
    wp_die();
}


/**
 * Starts the mysql queries for a database export.
 * Expects JSON-formatted POST data, formatted the same as the 'settings' property returned by \pdqcsv\ajax\getExportSettingDetails
 */
function export() {
    global $wpdb;
    // expect JSON-formatted POST data
    $request = json_decode( file_get_contents('php://input') );
    if ( $request === NULL ) {
        throw new \Exception( 'Request body not formatted properly' );
    }

    $objectType = sanitize_text_field( $request->objectType );
    // check the data type to export, and determine the database tables and row names we'll be using
    $config = _getDatabaseTablesAndColumns( $objectType );

    // We can't use $wpdb for this job, since $wpdb doesn't offer an option to do multiple queries at once.
    // So, create our own database connection.
    $mysqli = new \mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
    if ( mysqli_connect_errno() ) {
        throw new \Exception( mysqli_connect_error() );
    }

    _sanitizeRequest( $request, $mysqli );

    /**
     * Get the database to do as much of the work as possible, rather than juggling data in PHP. The database is much faster,
     * it can easily work within its memory limitations, and it likely has a longer execution timeout limit, giving us faster
     * results and lower chance of an error.
     * 
     * The filters and columns submitted by the user use two different kinds of fields: 'default' fields/properties and 'custom' fields/properties.
     * 'Default' fields directly map to columns in the object table. 'Custom' fields map to different `meta_key` entries in the meta table.
     * Our database queries will need to interact with both tables.
     * 
     * The database queries of interest do the following:
     * 1. Create a temporary table to store object IDs.
     * 2. Query the object and meta tables to find object IDs that pass the filter conditions, and store the result in the temporary table.
     * 3. Run one large query to get all the fields requested by the user, and use the temporary table to quickly select matching object IDs.
     *    Store the result in another table.
     * 4. Drop the temporary table.
     * 
     * Wait until we've created all the queries before we execute any of them. Then use multi_query to queue up all the queries, and exit the
     * php script, and let mysql churn away while we avoid PHP timeout errors.
     */

    // 0. Create an entry in the exports table to track the progress of this export
    $newRecord = \pdqcsv\util\createNewDbExportsRecord( $objectType );
    $tempTableName = $newRecord->temp_table_name;


    // 1. Create the temporary table. The TEMPORARY sql keyword makes the table too short-lived, so we just create a regular table and drop
    // it when we're done with it.
    // set the status before doing the next step
    $status1Query = \pdqcsv\util\buildQueryToSetExportsRecordStep( $newRecord->id, PDQCSV_EXPORT_STATUS_MAKING_TEMP_TABLE );
    $query1 = "CREATE TABLE `{$tempTableName}` (
        `id` INT NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET=utf8";


    // 2. Find the set of object IDs that pass the filter conditions, and insert the object IDs into the temporary table
    
    // set the status before doing this step
    $status2Query = \pdqcsv\util\buildQueryToSetExportsRecordStep( $newRecord->id, PDQCSV_EXPORT_STATUS_FILTERING_OBJECTS );

    $query2 = "INSERT INTO `{$tempTableName}` (`id`)
        SELECT DISTINCT `{$config->objectIdColumn}` FROM `{$config->objectTable}`\n\n";
    
    // filter on custom fields, using INNER JOIN for each one
    $customFieldFilters = array_filter( $request->filters, function($filter) { return $filter->fieldType === 'custom'; } );
    foreach( $customFieldFilters as $i => $filter ) {
        // figure out how this meta value is going to be compared
        $filterComparison = _createFilterComparison( "`m{$i}`.`meta_value`", $filter->rule, $filter->value, $filter->cast );
        // add this madness to the query to select IDs that pass this filter
        $query2 .= "INNER JOIN `{$config->metaTable}` `m{$i}` ON (
            `m{$i}`.`{$config->metaObjectIdColumn}` = `{$config->objectTable}`.`{$config->objectIdColumn}` AND
            `m{$i}`.`meta_key` = '{$filter->field}' AND
            {$filterComparison}
        )\n\n";
    }

    // filter on taxonomies that use the rule 'contains' or 'not empty', since these require INNER JOIN
    $taxoJoinFilters = array_filter( $request->filters, function($filter) { return $filter->fieldType === 'taxonomy' && in_array( $filter->rule, ['LIKE', 'not empty']); } );
    foreach( $taxoJoinFilters as $i => $filter ) {
        // this subquery gets all term_taxonomy_id's for the given taxonomy
        $termIds = "SELECT `term_taxonomy_id` FROM `{$wpdb->terms}`
            INNER JOIN `{$wpdb->term_taxonomy}` ON (
            `{$wpdb->term_taxonomy}`.`term_id` = `{$wpdb->terms}`.`term_id` AND
            `{$wpdb->term_taxonomy}`.`taxonomy` = '{$filter->field}'
        )";

        // decide how we will filter a list of term_taxonomy_id's
        if ( $filter->rule === 'LIKE' ) {
            // compare against the term with the given name
            $taxComparison = "= ( {$termIds} WHERE `{$wpdb->terms}`.`name` = '{$filter->value}' )";
        } else if ( $filter->rule === 'not empty' ) {
            // compare against a list of all term IDs
            $taxComparison = "IN ( {$termIds} )";
        }

        $query2 .= "INNER JOIN `{$wpdb->term_relationships}` `tj{$i}` ON (
            `tj{$i}`.`object_id` = `{$config->objectTable}`.`{$config->objectIdColumn}` AND
            `tj{$i}`.`term_taxonomy_id` {$taxComparison}
        )\n\n";
    }

    // filter on the default fields. This requires a WHERE clause. Compile an array of where-clause parts, then join them with AND and stick a WHERE on the beginning
    $defaultFieldFilters = array_filter( $request->filters, function($filter) { return $filter->fieldType === 'default'; } );
    $whereClauseParts = array_map( function($filter) use ($config) {
        return _createFilterComparison( "`{$config->objectTable}`.`{$filter->field}`", $filter->rule, $filter->value, @$filter->cast );
    }, $defaultFieldFilters );

    // filter on taxonomies that use the rule 'does not contain' or 'empty', since these require WHERE
    $taxoWhereFilters = array_filter( $request->filters, function($filter) { return $filter->fieldType === 'taxonomy' && in_array( $filter->rule, ['NOT LIKE', 'empty']); } );
    foreach( $taxoWhereFilters as $i => $filter ) {
        // this subquery gets all term names for the given taxonomy, associated with an object ID (i.e. post, page, etc)
        $listOfTermNames = "SELECT `trm{$i}`.`name` FROM `{$wpdb->term_relationships}`
            INNER JOIN `{$wpdb->term_taxonomy}` `tw{$i}` ON (
                `tw{$i}`.`term_taxonomy_id` = `{$wpdb->term_relationships}`.`term_taxonomy_id` AND
                `tw{$i}`.`taxonomy` = '{$filter->field}'
            )
            INNER JOIN `{$wpdb->terms}` `trm{$i}` ON (
                `trm{$i}`.`term_id` = `tw{$i}`.`term_taxonomy_id`
            )
            WHERE `{$wpdb->term_relationships}`.`object_id` = `{$config->objectTable}`.`{$config->objectIdColumn}`";

        if ( $filter->rule === 'NOT LIKE' ) {
            // filter object IDs where the list of the object's terms does not contain the given term
            array_push( $whereClauseParts, "'{$filter->value}' NOT IN ( {$listOfTermNames} )" );
        } else if ( $filter->rule === 'empty' ) {
            // filter object IDs where the list of the object's terms is empty
            array_push( $whereClauseParts, "( {$listOfTermNames} LIMIT 1 ) IS NULL" );
        }
    }

    // for any post type, add a part to the WHERE clause to only get posts of this type
    if ( $config->objectTable === $wpdb->posts ) {
        $type = $mysqli->real_escape_string( $request->objectType );
        array_push( $whereClauseParts, "`{$wpdb->posts}`.`post_type` = '{$type}'" );
    }

    if ( sizeof($whereClauseParts) > 0 ) {
        $query2 .= 'WHERE ' . join( ' AND ', $whereClauseParts ). "\n\n";
    }
    

    // 3. Create a table to store all filtered objects, with default fields, custom fields, and taxonomy terms.
    
    // set the status before doing this step
    $status3Query = \pdqcsv\util\buildQueryToSetExportsRecordStep( $newRecord->id, PDQCSV_EXPORT_STATUS_COMPILING_RESULTS );
    
    $allColumns = array_map( function($field, $i) use ($config) {
        if ( $field->fieldType === 'default' ) {
            // select columns from the object table
            return "`{$config->objectTable}`.`{$field->field}` AS '{$field->csvLabel}'";
        } else if ( $field->fieldType === 'custom' ) {
            // select columns from meta tables we'll LEFT JOIN with later
            return "`m{$i}`.`meta_values` AS '{$field->csvLabel}'";
        } else if ( $field->fieldType === 'taxonomy' ) {
            // select taxonomies from tables we'll LEFT JOIN with later
            return "`t{$i}`.`name` AS '{$field->csvLabel}'";
        }
    }, $request->fields, array_keys($request->fields) );

    // SELECT all requested fields and store in the results table
    $query3 = "CREATE TABLE " . $newRecord->results_table_name . " AS SELECT " . join( ', ', $allColumns ) . " FROM `{$config->objectTable}`\n\n";

    foreach( $request->fields as $i => $field ) {
        if ( $field->fieldType === 'custom' ) {
            // use a left join for each custom property in the meta table we want to include.
            // The subquery creates a two-column table (ID, meta values for the given meta_key). Each row is a unique object ID.
            // A single object instance could have multiple instances of the same meta_key, so use GROUP_CONCAT and GROUP BY to put all
            // values for each object ID in one table cell.
            $query3 .= "LEFT JOIN (
                SELECT `{$config->metaObjectIdColumn}`, GROUP_CONCAT( `meta_value` SEPARATOR '; ' ) `meta_values` FROM `{$config->metaTable}`
                WHERE `meta_key` = '{$field->field}'
                AND `{$config->metaObjectIdColumn}` IN ( SELECT `id` FROM `{$tempTableName}` )
                GROUP BY `{$config->metaObjectIdColumn}`
            ) `m{$i}` on `{$config->objectTable}`.`{$config->objectIdColumn}` = `m{$i}`.`{$config->metaObjectIdColumn}`\n\n";

        } else if ( $field->fieldType === 'taxonomy' ) {
            // use a left join for each taxonomy we want to include.
            // the subquery gets all terms for a given taxonomy, and groups them by object id.
            $query3 .= "LEFT JOIN (
                SELECT GROUP_CONCAT(`trm{$i}`.`name` SEPARATOR ', ') AS 'name', `object_id` FROM `{$wpdb->term_relationships}`
                INNER JOIN `{$wpdb->term_taxonomy}` `tx{$i}` ON (
                    `tx{$i}`.`term_taxonomy_id` = `{$wpdb->term_relationships}`.`term_taxonomy_id` AND
                    `tx{$i}`.`taxonomy` = '{$field->field}'
                )
                INNER JOIN `{$wpdb->terms}` `trm{$i}` ON (
                    `trm{$i}`.`term_id` = `tx{$i}`.`term_taxonomy_id`
                )
                GROUP BY `object_id`
            ) `t{$i}` ON `t{$i}`.`object_id` = `{$config->objectTable}`.`{$config->objectIdColumn}`\n\n";

        }
    }

    $query3 .= "WHERE `{$config->objectTable}`.`{$config->objectIdColumn}` IN ( SELECT `id` FROM `{$tempTableName}` )";
    
    
    // 4. Drop the temporary table
    // set the status before doing this step
    $status4Query = \pdqcsv\util\buildQueryToSetExportsRecordStep( $newRecord->id, PDQCSV_EXPORT_STATUS_DROPPING_TEMP_TABLE );
    $query4 = "DROP TABLE IF EXISTS `{$newRecord->temp_table_name}`";

    $finalStatusQuery = \pdqcsv\util\buildQueryToSetExportsRecordStep( $newRecord->id, PDQCSV_EXPORT_STATUS_DONE );


    // for debug purposes, store the queries we'll execute in the exports table
    $allQueries = [ $status1Query, $query1, $status2Query, $query2, $status3Query, $query3, $status4Query, $query4, $finalStatusQuery ];
    \pdqcsv\util\setDbExportQueries( $newRecord->id, $allQueries );

    // We've built all the queries we need. Queue them all up. Set a low time limit so that this script will exit shortly after
    // queuing the queries. Otherwise the script will try to wait until the queries are finished before exiting, but we don't need that.
    echo esc_html( $newRecord->id ) . ' ';
    set_time_limit( 1 );
    $mysqli->multi_query( join('; ', $allQueries) );
    $mysqli->close();
}



/** Returns an object containing info on the relevant database table and column names related to a given object/post type */
function _getDatabaseTablesAndColumns( $objectType ) {
    global $wpdb;
    switch ( $objectType ) {
        case 'user':
        return (object)array(
            'objectTable' => $wpdb->users,
            'metaTable' => $wpdb->usermeta,
            'objectIdColumn' => 'ID', // the database column which identifies a single instance of this object type
            'metaObjectIdColumn' => 'user_id' // the database column in the meta table which links the meta entry to an instance of this object type
        );
        break;

        case 'taxonomy':
        return (object)array(
            'objectTable' => $wpdb->terms,
            'metaTable' => $wpdb->termmeta,
            'objectIdColumn' => 'term_id',
            'metaObjectIdColumn' => 'term_id'
        );
        break;

        case 'comment':
        return (object)array(
            'objectTable' => $wpdb->comments,
            'metaTable' => $wpdb->commentmeta,
            'objectIdColumn' => 'comment_ID',
            'metaObjectIdColumn' => 'comment_id'
        );
        break;

        // posts, pages, and any custom post type
        default:
        return (object)array(
            'objectTable' => $wpdb->posts,
            'metaTable' => $wpdb->postmeta,
            'objectIdColumn' => 'ID',
            'metaObjectIdColumn' => 'post_id'
        );
    }
}


function _sanitizeRequest( &$request, $mysqli ) {
    /**
     * sanitize values passed via POST, and change the format a bit.
     * 
     * each field will be in the format:
     * {
     *  field: string (not including 'custom.', 'default.', or 'taxonomy.'),
     *  fieldType: 'default' | 'custom' | 'taxonomy',
     *  csvLabel: string
     * }
     * 
     * each filter will be in the format:
     * {
     *  field: string (not including 'custom.', 'default.', or 'taxonomy.'),
     *  fieldType: 'default' | 'custom' | 'taxonomy,
     *  rule: string,
     *  value: string,
     *  cast: string
     * }
     */
    foreach( $request->fields as $i => $field ) {
        _setFieldType( $field, $mysqli );
        // sanitize the column name to include in the CSV
        $field->csvLabel = $mysqli->real_escape_string( $field->csvLabel );
    }

    foreach( $request->filters as $i => $filter ) {
        _setFieldType( $filter, $mysqli );
        // check that the rule is one of a limited number of valid values
        $validRules = [ '=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'empty', 'not empty' ];
        if ( !in_array($filter->rule, $validRules) ) {
            throw new \Exception( 'Invalid rule value: ' . $filter->rule );
        }
        // sanitize the value if it's set
        if ( !empty($filter->value) ) {
            $filter->value = $mysqli->real_escape_string( $filter->value );
        }
        // check that the cast method is one of a limited number of valid values, if 'cast' is set
        if ( !empty($filter->cast) ) {
            $validCasts = [ 'alpha', 'date', 'number' ];
            if ( !in_array($filter->cast, $validCasts) ) {
                throw new \Exception( 'Invalid cast value: ' . $filter->cast );
            }
        }
    }
    return $request;
}

// takes an object with a 'field' property, changes that property to remove either 'default.', 'custom.', or 'taxonomy' from it,
// and adds a 'fieldType' property to the object to indicate 'default', 'custom', or 'taxonomy'.
// Modifies the original object.
function _setFieldType( &$obj, $mysqli ) {
    if ( substr($obj->field, 0, 8) === 'default.' ) {
        $obj->fieldType = 'default';
        $obj->field = substr( $obj->field, 8 );
        // the field is supposed to be a column name. Escape any backticks.
        $obj->field = str_replace( "`", "``", $obj->field );
    } else if ( substr($obj->field, 0, 7) === 'custom.' ) {
        $obj->fieldType = 'custom';
        $obj->field = substr( $obj->field, 7 );
        // the field is a value for meta_key. Sanitize it.
        $obj->field = $mysqli->real_escape_string( $obj->field );
    } else if ( substr($obj->field, 0, 9) === 'taxonomy.' ) {
        $obj->fieldType = 'taxonomy';
        $obj->field = substr( $obj->field, 9 );
        // sanitize the field - it will be used as a string
        $obj->field = $mysqli->real_escape_string( $obj->field );
    } else {
        throw new \Exception( 'Unexpected field name: ' . $obj->field );
    }
    return $obj;
}


// Creates part of a WHERE clause that is used to filter query results.
// $tableColumn should be in `table`.`column_name` format.
function _createFilterComparison( $tableColumn, $rule, $value, $cast ) {
    if ( preg_match('/[><=]/', $rule) ) {
        if ( !empty($cast) ) {
            if ( $cast === 'date' ) {
                return "CAST( $tableColumn AS DATETIME ) {$rule} CAST( '{$value}' AS DATETIME )";
            } else if ( $cast === 'number' ) {
                return "CAST( $tableColumn AS DOUBLE ) {$rule} " . floatval( $value );
            } else if ( $cast === 'alpha' ) {
                return "$tableColumn {$rule} '{$value}'";
            } else {
                throw new \Exception( 'Unexpected cast value: ' . $cast );
            }
        } else {
            // compare by string
            return "$tableColumn {$rule} '{$value}'";
        }
    } else if ( preg_match('/LIKE/', $rule) ) {
        // 'LIKE' or 'NOT LIKE'
        return "$tableColumn {$rule} '%{$value}%'";
    } else if ( $rule == 'empty' ) {
        return "( $tableColumn = '' OR $tableColumn IS NULL )";
    } else if ( $rule == 'not empty' ) {
        return "( $tableColumn <> '' AND $tableColumn IS NOT NULL )";
    } else {
        throw new \Exception( 'Unexpected rule: ' . $rule );
    }
}

