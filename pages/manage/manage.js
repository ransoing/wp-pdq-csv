
jQuery( function($) {
    $( 'table.pdqcsv .submitdelete' ).click( function() {
        // remove the row this button was in
        $(this).closest( 'tr' ).remove();
        // make the request to delete the entry from the database
        $.post( ajaxurl + '?action=pdqcsv_deleteExportSetting', { id: $(this).data('id') } );
    });
});
