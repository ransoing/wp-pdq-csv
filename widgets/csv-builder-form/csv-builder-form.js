/**
 * A custom jqueryUI widget -- a form that allows the user to select columns to add to a CSV, and add filters to restrict
 * what items are included in the CSV.
 * This widget also includes a loading bar and makes a request to get valid property options.
 *
 * Example HTML:
 *   <div id="foo"></div>
 * 
 * In JS:
 *  $( '#foo' ).csvbuilderform({
 *      objectLabel: 'User',
 *      propertyLabel: 'Field',
 *      errorMessage: 'Oops',
 *      getProperties: function() {
 *          return new Promise( function(resolve,reject) {
 *              ...
 *          });
 *      }
 *  });
 */
jQuery( function($) {

    $.widget( 'custom.csvbuilderform', {
        // default options
        options: {
            objectType: 'user', // the value used in the dropdown of what kind of thing to export
            objectLabel: 'Object',
            objectLabelPlural: 'Objects',
            propertyLabel: 'Field',
            propertyLabelPlural: 'Fields',
            errorMessage: 'Error: failed to load required data.',
            initialFilters: [],
            initialFields: [],
            getProperties: undefined // : () => Promise<propertyOptions[]>  (this Promise uses then/fail/always, as returned by jQuery Ajax methods)
        },

        _create: function() {
            // the HTML for this widget is stored in readable format in an HTML file, and made available as a
            // global var in `csv-builder-form.enqueue.php`
            this._$template = $( pdqcsvCsvBuilderFormHtml ).find( '[data-template-part-form]' );
            this.element.append( this._$template );

            this._$template.find( '[data-option-object-label]' ).text( this.options.objectLabel.toLowerCase() );
            this._$template.find( '[data-option-object-label-plural]' ).text( this.options.objectLabelPlural.toLowerCase() );
            this._$template.find( '[data-option-property-label]' ).text( this.options.propertyLabel.toLowerCase() );
            this._$template.find( '[data-option-property-label-plural]' ).text( this.options.propertyLabelPlural.toLowerCase() );

            // add variables for commonly used elements and slightly modify some of them
            this._$loadingSection = this._$template.find( '.pdqcsv-csv-builder-form-loading' );
            this._$errorSection = this._$template.find( '.pdqcsv-csv-builder-form-error' ).text( this.options.errorMessage );
            this._$contentSection = this._$template.find( '.pdqcsv-csv-builder-form-content' );

            // initialize the save button
            this._$template.find( '.pdqcsv-save-settings' ).exportsettingsdialog({
                mode: 'save',
                getExportSettingsData: () => this._getExportSettingsData()
            });

            // load the URL which will give us the property options to pass to widgets
            this._$loadingSection.toggle( true );
            this._$errorSection.toggle( false );
            this._$contentSection.toggle( false );
            this.options.getProperties().then( propertyOptions => {
                this._propertyOptions = propertyOptions;
                this._$contentSection.toggle( true );
                // initialize fields
                this._$template.find( '[data-multi-fields]' ).on( 'multifieldschange', e => {
                    if ( $(e.target).multifields('getData').length > 0 ) {
                        $('.pdqcsv-export-data').attr('disabled', false).attr('title', '');
                    } else {
                        $('.pdqcsv-export-data').attr('disabled', true).attr('title', 'You must add at least one CSV column');
                    }
                }).multifields({
                    thingToAdd: this.options.propertyLabel.toLowerCase(),
                    propertyOptions: this._propertyOptions,
                    initialFields: this.options.initialFields
                });
                // initialize filters
                this._$template.find( '[data-multi-filters]' ).multifilters({
                    propertyLabel: this.options.propertyLabel,
                    propertyOptions: this._propertyOptions,
                    initialFilters: this.options.initialFilters,
                    objectType: this.options.objectType
                });

                $('.pdqcsv-export-data').click( () => {
                    const exportSettings = this._getExportSettingsData();
                    // if any filter compares by date, convert the value into ISO format
                    exportSettings.filters.forEach( (filter, i) => {
                        if ( filter.cast === 'date' ) {
                            // make a copy just to be safe
                            exportSettings.filters[i] = Object.assign( {}, filter );
                            exportSettings.filters[i].value = moment( exportSettings.filters[i].value ).toDate().toISOString();
                        }
                    });
                    const exportPromise = $.ajax( ajaxurl + '?action=pdqcsv_export', {
                        method: 'POST',
                        contentType: 'application/json; charset=UTF-8',
                        data: JSON.stringify( exportSettings )
                    });
                    this._openProgressDialog( exportPromise );
                });
            })
            .fail( error => this._$errorSection.toggle(true) )
            .always( () => this._$loadingSection.toggle(false) );
        },

        _destroy: function() {
            this._$template.remove();
        },

        // gets field and filter data and compiles an object
        _getExportSettingsData: function() {
            return {
                objectType: this.options.objectType,
                fields: this._$template.find( '[data-multi-fields]' ).multifields( 'getData' ),
                filters: this._$template.find( '[data-multi-filters]' ).multifilters( 'getData' )
            };
        },

        _openProgressDialog( exportPromise ) {
            $dialogContentElement = $( pdqcsvCsvBuilderFormHtml ).find( '[data-template-part-export-dialog]' );
            $dialogContentElement.dialog({
                title: 'Exporting...',
                closeOnEscape: false,
                autoOpen: true,
                modal: true,
                resizable: false,
                draggable: false,
                buttons: [
                    {
                        text: 'Close',
                        click: () => {
                            clearInterval( exportUpdateInterval );
                            $dialogContentElement.dialog( 'close' );
                            $dialogContentElement.remove();
                        }
                    }, {
                        text: 'Download CSV',
                        class: 'pdqcsv-modal-submit-button',
                        click: () => {
                            // create a form and submit it to open a new tab via a POST request
                            $form = $('<form method="post" action="./admin.php?page=wp-pdq-csv-download-csv" target="_blank"><input name="id" value="' + exportRecordId + '"></form>');
                            $('body').append( $form );
                            $form.submit().remove();
                            $dialogContentElement.dialog( 'close' );
                            $dialogContentElement.remove();
                        }
                    }
                ]
            });

            let exportUpdateInterval;
            let exportRecordId;

            // disable the download button
            $dialogContentElement.closest( '.ui-dialog' ).find( '.pdqcsv-modal-submit-button' ).button( 'option', 'disabled', true );

            exportPromise.then( response => {
                exportRecordId = parseInt( response );

                // make regular requests to get the status of the export
                exportUpdateInterval = setInterval( () => {
                    this._getExportStatus( exportRecordId ).then( statusStep => {
                        const friendlyText = pdqcsvExportStatuses.find( status => status.statusCode === statusStep ).description;
                        const isFinished = statusStep === pdqcsvExportStatuses[pdqcsvExportStatuses.length - 1].statusCode;
                        // update the text and set the class if needed
                        $dialogContentElement.find( '.pdqcsv-status' )
                        .text( friendlyText )
                        .toggleClass( 'finished', isFinished );

                        if ( isFinished ) {
                            clearInterval( exportUpdateInterval );
                            $dialogContentElement.find( '.pdqcsv-dialog-loading' ).toggle( false );
                            // enable the download button
                            $dialogContentElement.closest( '.ui-dialog' ).find( '.pdqcsv-modal-submit-button' ).button( 'option', 'disabled', false );
                        }
                    });
                }, 3000 );
            });

        },
        
        _getExportStatus: function( exportRecordId ) {
            return $.getJSON( ajaxurl, { action: 'pdqcsv_getExportById', id: exportRecordId } ).then( response => {
                return response.status_step;
            });
        }
    });
});
