<?php

namespace pdqcsv\util;

require_once( __DIR__ . '/db.php' );

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

function downloadCsv() {
    // get the record identified in the POST data
    $exportRecord = getSingleDbExportsRecord( intval($_POST['id']) );
    if ( !isset($exportRecord) ) {
        throw new \Exception( 'That export no longer exists' );
    }

    // We can't use $wpdb for this job, since $wpdb doesn't offer an option to do an unbuffered query.
    // Unbuffered query lets us get results from the db with using less memory.
    // So, create our own database connection.
    $mysqli = new \mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
    if ( mysqli_connect_errno() ) {
        throw new \Exception( mysqli_connect_error() );
    }

    // output headers
    $date = date( 'Y-m-d' );
    header( 'Content-Type: application/octet-stream' ); // force download
    header( 'Content-Transfer-Encoding: Binary'); // force download
    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' ); // never cache
    header( 'Expires: 0' ); // never cache
    header( "Content-disposition: attachment; filename=pdqcsv-{$exportRecord->object_type}-export.{$date}.csv" );
    // send the headers to the client now so the download seems to start immediately
    flush();
    ob_flush();

    // The db query could potentially output a rather large table, so use an unbuffered query to avoid
    // memory limit errors
    // MYSQLI_USE_RESULT makes for an unbuffered query
    $result = $mysqli->query( "SELECT * FROM `{$exportRecord->results_table_name}`", MYSQLI_USE_RESULT );
    if ( !$result ) {
        throw new \Exception( 'Error selecting fields: ' . $mysqli->error );
    }

    // use the output stream as a file so we can use fputcsv to output csv content to the client
    $stdOut = fopen( 'php://output', 'w' );

    // first get a list of headers
    $headers = array_map( function($header) {
        return $header->name;
    }, $result->fetch_fields() );
    // excel is stupid when the upper-left cell is "ID". Fix this case.
    if ( $headers[0] === 'ID' ) {
        $headers[0] = '`ID';
    }
    fputcsv( $stdOut, $headers );

    // now output all the rows in the results table
    while ( $row = $result->fetch_row() ) {
        fputcsv( $stdOut, $row );
    }

    @$result->free_result();
    $mysqli->close();

    // delete the export record and tables
    removeDbExportRecord( $exportRecord->id );
}
