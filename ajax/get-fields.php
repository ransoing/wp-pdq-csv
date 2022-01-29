<?php

namespace pdqcsv\ajax;

require_once( __DIR__ . '/export.php' );

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

/**
 * Gets all default and custom fields for users.
 * Returns an object in this format:
 * {
 *   'default': {
 *     'dbColumn': string,
 *     'label': string
 *   }[],
 *   'custom': {
 *     'metaKey': string
 *   }[]
 * }
 */
function getUserFields() {
    global $wpdb;
    // build a 2D array describing columns in the wp_users table
    $defaultFieldsCondensed = [
        [ 'ID', 'User ID' ],
        [ 'user_login', 'Username' ],
        [ 'user_pass', 'Hashed password' ],
        [ 'user_nicename', 'URL-friendly username' ],
        [ 'user_email', 'Email' ],
        [ 'user_url', 'URL' ],
        [ 'user_registered', 'Registration date/time' ],
        [ 'user_activation_key', 'Activation key' ],
        [ 'user_status', 'Status' ],
        [ 'display_name', 'Display name' ]
    ];

    echo json_encode( (object)[
        'default' => _buildDefaultFields( $defaultFieldsCondensed ),
        'custom' => _getDistinctCustomFields( $wpdb->usermeta, 'umeta_id' )
    ]);
    wp_die();
}

/**
 * Gets all default and custom fields for any post type.
 * 
 * Requires GET param: `postType`
 * 
 * Returns an object in the same format as `getUserFields`, but with the addition of 'taxonomies' fields.
 */
function getPostTypeFields() {
    global $wpdb;
    // build a 2D array describing columns in the wp_posts table
    $defaultFieldsCondensed = [
        [ 'ID', 'Post ID' ],
        [ 'post_author', 'Author user ID' ],
        [ 'post_date', 'Date' ],
        [ 'post_date_gmt', 'Date (GMT)' ],
        [ 'post_content', 'Content' ],
        [ 'post_title', 'Title' ],
        [ 'post_excerpt', 'Excerpt' ],
        [ 'post_status', 'Status' ],
        [ 'comment_status', 'Comment status' ],
        [ 'ping_status', 'Ping status' ],
        [ 'post_password', 'Password' ],
        [ 'post_name', 'URL-friendly title' ],
        [ 'to_ping', 'To ping' ],
        [ 'pinged', 'Pinged' ],
        [ 'post_modified', 'Modified date' ],
        [ 'post_modified_gmt', 'Modified date (GMT)' ],
        [ 'post_content_filtered', 'Content (filtered)' ],
        [ 'post_parent', 'Parent post ID' ],
        [ 'guid', 'URL' ],
        [ 'menu_order', 'Menu order' ],
        // [ 'post_type', 'Post type' ],
        [ 'post_mime_type', 'MIME type' ],
        [ 'comment_count', 'Comment count' ]
    ];

    // validate that the given post type is a valid post type
    $allPostTypes = array_keys( get_post_types() );
    $postType = sanitize_text_field( $_GET['postType'] );
    if ( !in_array($postType, $allPostTypes) ) {
        exit;
    };

    // get distinct custom fields specifically for this post type
    $preparedQuery = $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM `{$wpdb->postmeta}`
        INNER JOIN `{$wpdb->posts}` ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
        WHERE {$wpdb->posts}.post_type = '%s' ORDER BY meta_key;",
        $postType
    );
    $distinctFields = _getDistinctCustomFields( $wpdb->postmeta, 'meta_id', $postType, $preparedQuery );

    $taxonomyObjects = get_taxonomies( ['object_type' => [$postType] ], 'objects' );
    $taxonomies = array_map( function($key) use ($taxonomyObjects) {
        $tax = $taxonomyObjects[$key];
        return (object)[
            'label' => empty( $tax->label ) ? $tax->name : $tax->label,
            'value' => $tax->name
        ];
    }, array_keys($taxonomyObjects) );

    echo json_encode( (object)[
        'default' => _buildDefaultFields( $defaultFieldsCondensed ),
        'taxonomies' => $taxonomies,
        'custom' => $distinctFields
    ]);
    wp_die();
}

/**
 * Gets all default and custom fields for comments.
 * 
 * Returns an object in the same format as `getUserFields`
 */
function getCommentFields() {
    global $wpdb;
    // build a 2D array describing columns in the wp_comments table
    $defaultFieldsCondensed = [
        [ 'comment_ID', 'ID' ],
        [ 'comment_post_ID', 'Post ID' ],
        [ 'comment_author', 'Author' ],
        [ 'comment_author_email', 'Author email' ],
        [ 'comment_author_url', 'Author URL' ],
        [ 'comment_author_IP', 'Author IP address' ],
        [ 'comment_date', 'Date' ],
        [ 'comment_date_gmt', 'Date (GMT)' ],
        [ 'comment_content', 'Content' ],
        [ 'comment_karma', 'Karma' ],
        [ 'comment_approved', 'Approved' ],
        [ 'comment_agent', 'Agent' ],
        [ 'comment_type', 'Type' ],
        [ 'comment_parent', 'Parent ID' ],
        [ 'user_id', 'User ID' ]
    ];

    echo json_encode( (object)[
        'default' => _buildDefaultFields( $defaultFieldsCondensed ),
        'custom' => _getDistinctCustomFields( $wpdb->commentmeta, 'meta_id' )
    ]);
    wp_die();
}

/**
 * Gets all default and custom fields for taxonomies.
 * 
 * Returns an object in the same format as `getUserFields`
 */
function getTaxonomyFields() {
    global $wpdb;
    // build a 2D array describing columns in the wp_terms table
    $defaultFieldsCondensed = [
        [ 'term_id', 'ID' ],
        [ 'name', 'Name' ],
        [ 'slug', 'Slug' ],
        [ 'term_group', 'Group ID' ]
    ];

    echo json_encode( (object)[
        'default' => _buildDefaultFields( $defaultFieldsCondensed ),
        'custom' => _getDistinctCustomFields( $wpdb->termmeta, 'meta_id' )
    ]);
    wp_die();
}


/**
 * Gets 4 random example values. Requires GET params: `objectType`, `field`
 * Returns an array of strings.
 */
function getExampleValues() {
    global $wpdb;
    $objectType = sanitize_text_field( $_GET['objectType'] );
    $field = sanitize_text_field( $_GET['field'] );
    $config = _getDatabaseTablesAndColumns( $objectType );
    $result;
    if ( substr($field, 0, 8) === 'default.' ) {
        $field = substr( $field, 8 );
        // the field is supposed to be a column name. Escape any backticks.
        $field = str_replace( "`", "``", $field );
        $result = $wpdb->get_results( "SELECT DISTINCT `{$field}` FROM `{$config->objectTable}` WHERE `{$field}` <> '' AND `{$field}` IS NOT NULL ORDER BY RAND() LIMIT 4" );
        $values = array_map( function($row) use ($field) {
            return $row->$field;
        }, $result );
    } else if ( substr($field, 0, 7) === 'custom.' ) {
        $field = substr( $field, 7 );
        $result = $wpdb->get_results( $wpdb->prepare("SELECT DISTINCT `meta_value` FROM `{$config->metaTable}` WHERE `meta_key` = '%s' AND `meta_value` <> '' AND `meta_value` IS NOT NULL ORDER BY RAND() LIMIT 4", $field) );
        $values = array_map( function($row) {
            return $row->meta_value;
        }, $result );
    } else if ( substr($field, 0, 9) === 'taxonomy.' ) {
        $field = substr( $field, 9 );
        $terms = get_terms( array(
            'taxonomy' => $field,
            'hide_empty' => false,
            'number' => 4
        ));
        $values = array_map( function($term) {
            return $term->name;
        }, $terms );
    }

    echo json_encode( $values );
    wp_die();
}


/** Takes a 2D array. Each subarray represents [ dbColumn, label ]. Outputs an array of objects, each one { dbColumn: column_value, label: label_value } */
function _buildDefaultFields( $condensedFields ) {
    $defaultFields = array_map( function($arr) {
        return (object)[
            'dbColumn' => $arr[0],
            'label' => $arr[1]
        ];
    }, $condensedFields );
    // sort by label
    usort( $defaultFields, function($a, $b) {
        return strcmp( $a->label, $b->label );
    });
    return $defaultFields;
}

/** Gets a list of distinct meta_key values from a meta table in the database. Gets and sets cached values where appropriate. */
function _getDistinctCustomFields( string $metaTableName, string $metaIdColumName, string $postType = null, $customQuery = null ) {
    // try to retrieve cached value first
    $cachedData = _getMetaKeyCache( $metaTableName, $metaIdColumName, $postType );
    if ( $cachedData ) {
        return $cachedData;
    }

    // fetch the list of distinct meta_key values
    global $wpdb;
    $query = $customQuery ? $customQuery : "SELECT DISTINCT meta_key FROM `{$metaTableName}` ORDER BY meta_key";
    $newData = $wpdb->get_results( $query );
    // save the list to a cache
    _setMetaKeyCache( $newData, $metaTableName, $metaIdColumName, $postType );
    return $newData;
}


/**
 * Wordpress never changes the meta_key in a meta table row. meta_value may change for any given row, but not meta_key.
 * This means that as long as the highest meta_id value stays the same (i.e. as long as no new meta table entries have been added),
 * there won't be any new meta_keys, so we can use a cached list of unique meta_keys.
 * 
 * This function either returns a falsy value (if there is no cache, or if the cache is invalid), or returns the cached results of
 * the query to get unique meta_key values.
 */
function _getMetaKeyCache( string $metaTableName, string $metaIdColumName, string $postType = null ) {
    $cache = get_option( _buildCacheOptionName($metaTableName, $postType) );
    if ( $cache === false || intval($cache->highestId) < _getHighestMetaId($metaTableName, $metaIdColumName) ) {
        return false;
    } else {
        return $cache->data;
    }
}


function _setMetaKeyCache( $dataToCache, string $metaTableName, string $metaIdColumName, string $postType = null ) {
    $data = (object)[
        'highestId' => _getHighestMetaId( $metaTableName, $metaIdColumName ),
        'data' => $dataToCache
    ];
    update_option( _buildCacheOptionName($metaTableName, $postType), $data, false );
}


/** Builds the option name used for getting/retrieving cached values via get_option or update_option */
function _buildCacheOptionName( string $metaTableName, string $postType = null ) {
    return 'pdqcsv_cache_' . ( $postType ? "{$metaTableName}_{$postType}" : $metaTableName );
}


function _getHighestMetaId( string $metaTableName, string $metaIdColumName ) {
    global $wpdb;
    return intval( $wpdb->get_var("SELECT {$metaIdColumName} FROM `{$metaTableName}` ORDER BY {$metaIdColumName} DESC LIMIT 1") );
}
