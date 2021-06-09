/**
 * A custom jqueryUI widget -- Adds behavior to a button which opens a dialog box to either load or save export settings
 * 
 * Example HTML:
 * <button type="button" id="foo">Click it</button>
 * 
 * In JS:
 * $( '#foo' ).exportsettingsdialog({
 *   mode: 'load',
 *   onExportSettingsLoaded: function( id ) {}
 * });
 */
jQuery( function($) {

  $.widget( 'custom.exportsettingsdialog', {
    // default options
    options: {
        mode: '', // either 'load' or 'save',
        getExportSettingsData: function() { return {} }, // required if mode = 'save'. Must return an object with export settings.
        onExportSettingsLoaded: function( settingsId ) {} // required if mode = 'load'
    },

    _create: function() {
        this.element.click( () => this._openDialog() );
    },

    _destroy: function() {
        this._$template.remove();
    },

    _openDialog: function() {
        // the HTML for this widget is stored in readable format in an HTML file, and made available as a
        // global var in `export-settings-dialog.enqueue.php`
        const $dialogElement = $( pdqcsvExportSettingsDialogHtml ).find( this.options.mode === 'load' ? '[data-template-part-load]' : '[data-template-part-save]' );
        this._$dialogElement = $dialogElement;
        
        // show the loading spinner and load a list of available export settings
        $contentSection = $dialogElement.find( '.export-settings-content' ).toggle( false );
        $loadingSection = $dialogElement.find( '.export-settings-loading' ).toggle( true ); 
        $errorSection = $dialogElement.find( '.export-settings-error' ).toggle( false );

        $.getJSON( ajaxurl, { action: 'pdqcsv_getExportSettings' } ).then( records => {
            $contentSection.toggle( true );

            // add options to the select elements and initialize comboboxes
            $dialogElement.find( '[data-combobox]' ).each( function() {
                records.forEach( record => {
                    $(this).append(
                        $('<option>').text( record.name ).attr( 'value', record.id )
                    );
                });
                $(this).combobox();
            });

            // initialize tabs
            $dialogElement.find( '[data-tabs]' ).tabs({
                activate: () => this._onInputChange()
            });

            // hide comboboxes if there are no saved export settings
            if ( records.length === 0 ) {
                $dialogElement.find( '.saved-exports-combobox-widget' ).html( '<p>There are no saved export settings!</p>' );
            }
        })
        .fail( error => $errorSection.toggle(true) )
        .always( () => $loadingSection.toggle(false) );

        // open the dialog
        $dialogElement.dialog({
            title: this.options.mode === 'load' ? 'Load export settings' : 'Save export settings',
            autoOpen: true,
            modal: true,
            resizable: false,
            draggable: false,
            width: this.options.mode === 'load' ? undefined : 'auto',
            maxWidth: this.options.mode === 'load' ? undefined : 500,
            buttons: [
                {
                    text: 'Cancel',
                    click: () => {
                        $dialogElement.dialog( 'close' );
                        $dialogElement.remove();
                    }
                }, {
                    text: this.options.mode === 'load' ? 'Load export settings' : 'Save export settings',
                    class: 'pdqcsv-modal-submit-button',
                    click: () => this._getCurrentForm().submit()
                }
            ]
        });

        // set change handlers for form validation. Run the handler now, which disables the submit button
        $dialogElement.find( 'select, input' ).change( () => this._onInputChange() ).keyup( () => this._onInputChange() );
        this._onInputChange();

        // handle form submit, via enter keyboard button or UI submit button
        $dialogElement.find( 'form' ).submit( event => this._onDialogFormSubmit(event) );
    },

    _onInputChange: function() {
        setTimeout( () => {
            this._$dialogElement.closest( '.ui-dialog' ).find( '.pdqcsv-modal-submit-button' ).button( 'option', 'disabled', !this._formIsValid() );
        }, 10 );
    },

    // checks whether the current form is able to be submitted
    _formIsValid: function() {
        const $currentForm = this._getCurrentForm();
        if ( !$currentForm ) return;
        const form = $currentForm[0];
        return form &&
               (
                   ( form.export_settings_id && form.export_settings_id.value ) ||
                   ( form.new_export_settings_name && form.new_export_settings_name.value ) ||
                   ( form.old_export_settings_id && form.old_export_settings_id.value )
               );
    },

    // the dialog has three possible forms. Get the form that the user is currently interacting with
    _getCurrentForm: function() {
        if ( this.options.mode === 'load' ) {
            // there's only one form in the 'load' dialog
            return this._$dialogElement.find( 'form' );
        } else {
            // there's one form per tab in the 'save' dialog
            let activeTabIndex;
            try {
                activeTabIndex = this._$dialogElement.find( '[data-tabs]' ).tabs( 'option', 'active' );
                return this._$dialogElement.find( 'form' ).eq( activeTabIndex );
            } catch ( e ) {
                return;
            }
        }
    },

    _onDialogFormSubmit( event ) {
        event.preventDefault();
        if ( !this._formIsValid() ) {
            return;
        }
        this._$dialogElement.dialog( 'close' );

        if ( this.options.mode === 'load' ) {
            this.options.onExportSettingsLoaded( event.target.export_settings_id.value );
            this._$dialogElement.remove();
        } else {
            // get ready to make a POST request to add or change export settings.
            const postData = {
                settings: this.options.getExportSettingsData()
            };
            // add either `name` or `id` to the data.
            event.target.name === 'save-new' ? postData.name = event.target.new_export_settings_name.value : postData.id = event.target.old_export_settings_id.value;

            // remove the current dialog, and open a new one to show the results of the call.
            this._$dialogElement.remove();

            $statusDialog = $( pdqcsvExportSettingsDialogHtml ).find( '[data-template-part-saving]' );
            $statusDialog.find('.export-settings-error, .export-settings-content').toggle( false );
            this._openStatusDialog( $statusDialog );
            $.ajax( ajaxurl + '?action=pdqcsv_saveExportSettings', {
                method: 'POST',
                contentType: 'application/json; charset=UTF-8',
                data: JSON.stringify( postData )
            })
            .then( () => {
                $statusDialog.find('.export-settings-content').toggle( true );
                // automatically close the modal
                setTimeout( () => {
                    $statusDialog.dialog( 'close' );
                    $statusDialog.remove();
                }, 2000 );
            })
            .fail( () => $statusDialog.find('.export-settings-error').toggle(true) )
            .always( () => $statusDialog.find('.export-settings-loading').toggle(false) );
        }
    },

    _openStatusDialog( $dialogContentElement ) {
        $dialogContentElement.dialog({
            title: 'Saving...',
            closeOnEscape: false,
            autoOpen: true,
            modal: true,
            resizable: false,
            draggable: false,
            buttons: [
                {
                    text: 'Close',
                    click: () => {
                        $dialogContentElement.dialog( 'close' );
                        $dialogContentElement.remove();
                    }
                }
            ]
        });
    }

  });
});
