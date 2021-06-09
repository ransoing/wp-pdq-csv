
jQuery( function($) {
    // regularly update the status of all exports
    var updateInterval = setInterval( getStatuses, 5000 );
    getStatuses();

    function getStatuses() {
        $.getJSON( ajaxurl, { action: 'pdqcsv_getCurrentExports' } ).then( response => {
            $('.export-row').each( function() {
                $status = $(this).find( 'td.status' );
                let id = $status.attr('data-status-for');
                let matchingExportObject = response.find( exportObject => exportObject.id === id );
                if ( matchingExportObject ) {
                    // get the description for this status code
                    let description = pdqcsvExportStatuses.find( status => status.statusCode === matchingExportObject.status_step ).description;
                    $status.text( description );
                    const isFinished = matchingExportObject.status_step === pdqcsvExportStatuses[pdqcsvExportStatuses.length - 1].statusCode;
                    $status.toggleClass( 'finished', isFinished );
                    if ( isFinished ) {
                        $(this).find( '.export-link' ).toggle( true );
                    }
                }
            });

            const allAreDone = response.every( exportObject => {
                return exportObject.status_step === pdqcsvExportStatuses[pdqcsvExportStatuses.length - 1].statusCode;
            });
            if ( allAreDone ) {
                clearInterval( updateInterval );
            }
        });
    }

    $('.pdqcsv-download-csv').click( function() {
        let id = $(this).attr('data-id');
        // create a form and submit it to open a new tab via a POST request
        $form = $('<form method="post" action="./admin.php?page=wp-pdq-csv-download-csv" target="_blank"><input name="id" value="' + id + '"></form>');
        $('body').append( $form );
        $form.submit().remove();

        // the download script deletes this export record, so change the row in the table to reflect this.
        // Don't remove the row -- that could be somewhat confusing to the user since they didn't click 'delete'
        $(this).closest( 'tr' ).addClass( 'pdqcsv-removed' ).find( '.status' ).text( 'Downloaded' );
        $('.debug-info-row[data-id="' + id + '"]').toggle( false ); // hide the debug info
    });

    $('.submitdelete').click( function() {
        let id = $(this).attr('data-id');
        $.post( ajaxurl + '?action=pdqcsv_deleteExport', { id: id } );
        // remove the row this button was in and the debug info row
        $('.debug-info-row[data-id="' + id + '"]').remove();
        $(this).closest( 'tr' ).remove();
    });

    $('.pdqcsv-show-queries').click( function() {
        let id = $(this).attr('data-id');
        let $tr = $('.debug-info-row[data-id="' + id + '"]');
        $tr.toggle();
        $(this).text( $tr.css('display') === 'none' ? 'Show debug info' : 'Hide debug info' );
    });
});
