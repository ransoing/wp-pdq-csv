
jQuery( function($) {
    $( '.pdqcsv-settings-error' ).toggle( false );

    let $currentBuilderForm;
    const $objectTypeSelect = $( '[name=object-type]' );


    // get the value of the 'export-settings' GET variable, if it's available
    let exportSettingsId;
    try {
        exportSettingsId = window.location.search.replace( /^\?/, '' ).split( '&' )
        .map( varPair => varPair.split('=') )
        .find( varPair => varPair[0] === 'export-settings' )[1];

        loadSavedSettings( exportSettingsId );
    } catch ( e ) {
        // fail silently. No export settings to load this time.
        initializeObjectTypeCombobox();
        $( '.pdqcsv-export-content' ).toggle( true );
    }

    $('.pdqcsv-load-settings').exportsettingsdialog({
        mode: 'load',
        onExportSettingsLoaded: settingsId => loadSavedSettings( settingsId )
    });

    function loadSavedSettings( savedSettingsId ) {
        // show the loading spinner and hide the content until we've populated the entire csv builder form
        $( '.pdqcsv-settings-loading' ).toggle( true );
        $( '.pdqcsv-export-content' ).toggle( false );

        // get details on the export identified by the ID
        $.getJSON( ajaxurl, { action: 'pdqcsv_getExportSettingDetails', id: savedSettingsId } ).then( response => {
            try {
                const objectType = response.settings.objectType
                $objectTypeSelect.find( 'option[value="' + objectType + '"]' ).attr( 'selected', true );
                if ( $objectTypeSelect.combobox('instance') !== undefined ) {
                    $objectTypeSelect.combobox( 'destroy' );
                };
                initializeObjectTypeCombobox();
                return onObjectTypeSelectChange( $objectTypeSelect[0], response.settings.filters, response.settings.fields );
            } catch ( e ) {
                console.error( e );
                onloadExportFail();
            }
        })
        .fail( () => onloadExportFail() )
        .always( () => {
            $('.pdqcsv-settings-loading').toggle(false);
            // always show the content so that the user can select a new dropdown item
            $('.pdqcsv-export-content').toggle(true);
        });
    }

    function onloadExportFail() {
        $('.pdqcsv-settings-error').toggle(true);
        // reset the combobox value
        if ( $objectTypeSelect.combobox('instance') !== undefined ) {
            $objectTypeSelect.combobox( 'destroy' );
        };
        initializeObjectTypeCombobox();
        $objectTypeSelect.val( '' );
        $objectTypeSelect.change();
    }


    function initializeObjectTypeCombobox() {
        $objectTypeSelect.combobox().change( function() {
            // if the selected type is some type of post (i.e. NOT user, taxonomy, or comment), initialize the filters with post_status=publish
            const initialFilters = ['user', 'taxonomy', 'comment'].indexOf($(this).val()) === -1 ?
                [{ field: 'default.post_status', rule: '=', value: 'publish' }] :
                undefined;

            onObjectTypeSelectChange( this, initialFilters );
        });
    }


    /** Clears the old csv builder form and creates a new one. Returns a Promise - the AJAX call to get available fields for the selected object type */
    function onObjectTypeSelectChange( selectElement, initialFilters, initialFields ) {
        if ( $currentBuilderForm ) {
            $currentBuilderForm.remove();
        }

        if ( !selectElement.value ) {
            return;
        }

        const ajaxCall = $.getJSON( ajaxurl, buildAjaxFieldRequestData(selectElement.value) )
        .then( function(response) {
            // create fields for the filters
            let allFields = [];
            if ( response.default ) {
                allFields = allFields.concat( response.default.map( field => {
                    return { label: field.label, value: 'default.' + field.dbColumn };
                }));
            }
            if ( response.taxonomies ) {
                allFields = allFields.concat( response.taxonomies.map( field => {
                    return { label: field.label, value: 'taxonomy.' + field.value };
                }));
            }
            if ( response.custom ) {
                allFields = allFields.concat( response.custom.map( field => {
                    return { label: field.meta_key, value: 'custom.' + field.meta_key };
                }));
            }
            return allFields;
        });

        const $option = $(selectElement).find( 'option[value=' + selectElement.value + ']' );
        $currentBuilderForm = $('<div>').csvbuilderform({
            objectType: selectElement.value,
            objectLabel: $option.data('singular-name'),
            objectLabelPlural: $option.text(),
            propertyLabel: 'Field',
            propertyLabelPlural: 'Fields',
            errorMessage: 'Error: failed to load list of ' + selectElement.value + ' fields',
            initialFilters: initialFilters,
            initialFields: initialFields,
            getProperties: () => ajaxCall
        }).appendTo( $('#builder-form-wrapper') );

        return ajaxCall;
    }


    function buildAjaxFieldRequestData( objectType ) {
        // objectType should be a value from the object type <select>
        let data = {};
        switch( objectType ) {
            case 'user': data.action = 'pdqcsv_getUserFields'; break;
            case 'taxonomy': data.action = 'pdqcsv_getTaxonomyFields'; break;
            case 'comment': data.action = 'pdqcsv_getCommentFields'; break;
            default: data.action = 'pdqcsv_getPostTypeFields';
        }
        if ( data.action === 'pdqcsv_getPostTypeFields' ) {
            data.postType = objectType;
        }
        return data;
    }


});
