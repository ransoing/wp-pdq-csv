/**
 * A custom jqueryUI widget -- Controls to add fields to a CSV.
 * Fires an event 'multifieldschange' when the fields have changed.
 * 
 * Example HTML:
 * <div id="foo"></div>
 * 
 * In JS:
 * $( '#foo' ).multifields();
 */
jQuery( function($) {

  $.widget( 'custom.multifields', {
    // default options
    options: {
        thingToAdd: 'field',
        propertyOptions: [], // : { label: string, value: string, disabled: boolean }[]
        initialFields: [] // : { field: string, csvLabel: string }[]
    },

    _create: function() {
        // create a copy of `propertyOptions` because we'll be editing it
        this.options.propertyOptions = $.extend( true, [], this.options.propertyOptions );

        // the HTML for this widget is stored in readable format in an HTML file, and made available as a
        // global var in `multi-fields.enqueue.php`
        this._$template = $( pdqcsvMultiFieldsHtml ).find( '[data-template-part-main]' );
        this.element.append( this._$template );

        // add text and behavior for buttons
        this._$template.find( '.pdqcsv-multi-fields-add-field' ).click( () => this._openDialog() );
        this._$template.find( '.pdqcsv-multi-fields-add-all-fields' ).click( () => this._addAllFields() );
        this._$template.find( '.pdqcsv-multi-fields-clear-fields' ).click( () => this._removeAllPills() );

        this._$template.find( '[data-thing-to-add]' ).text( this.options.thingToAdd );

        this._$template.find( '.pdqcsv-pills' ).sortable({
            placeholder: 'pdqcsv-sort-placeholder pdqcsv-pill',
            handle: '.pdqcsv-pill-label',
            cursor: 'grabbing'
        }).disableSelection();

        this._setClearAllButtonEnabled();

        // add initial fields
        this.options.initialFields.forEach( field => {
            try {
                // find the label for the value
                const fieldLabel = this.options.propertyOptions.find( option => option.value === field.field ).label;
                this._addPill( field.field, fieldLabel, field.csvLabel );
            } catch ( e ) {
                // fail silently and skip adding this pill.
                console.error( 'Couldn\'t add a pill.', e );
            }
        });
    },

    _destroy: function() {
        this._$template.remove();
    },

    /** Opens a dialog to either add or edit a pill. $pillToEdit is optional. */
    _openDialog: function( $pillToEdit ) {
        const $dialogElement = $( pdqcsvMultiFieldsHtml ).find( '[data-template-part-dialog]' );
        const $fieldSelect = $dialogElement.find( 'select' );
        const $nameInput = $dialogElement.find( '.pdqcsv-dialog-column-name' );

        $fieldSelect.change( onInputChange );
        $nameInput.keyup( onInputChange );

        // add a label and options to the combobox
        $dialogElement.find( '.dialog-combobox-label' ).text( this.options.thingToAdd );
        const selectedValue = $pillToEdit && $pillToEdit.pill( 'getOptions' ).data.field;

        // add property options to the property combobox, grouped by field type
        const groups = [
            { prefix: 'default.',   label: 'Default fields' },
            { prefix: 'taxonomy.',  label: 'Taxonomies', },
            { prefix: 'custom.',    label: 'Custom fields' }
        ];
        groups.forEach( group => {
            const groupOptions = this.options.propertyOptions.filter( option => option.value.indexOf(group.prefix) === 0 );
            if ( groupOptions.length > 0 ) {
                const $optgroup = $('<optgroup>').attr('label', group.label);
                groupOptions.forEach( option => {
                    // if we're editing a pill, don't disable the option that the pill represents. Instead, put the [selected] attribute on the option
                    const $option = $( '<option>' ).attr( 'value', option.value )
                    .text( option.label )
                    .attr( 'disabled', option.disabled && !(selectedValue && selectedValue === option.value) );
                    if ( option.value === selectedValue ) {
                        $option.attr( 'selected', true );
                    }
                    $optgroup.append( $option );
                });
                $fieldSelect.append( $optgroup );
            }
        });

        // prepopulate the column name input if we're editing the pill
        if ( $pillToEdit ) {
            $nameInput.val( $pillToEdit.pill( 'getOptions' ).data.csvLabel );
        }

        $fieldSelect.combobox().change( event => {
            // populate the 'column name' input with the display value
            const matchingOption = this.options.propertyOptions.find( option => option.value === event.target.value );
            $nameInput.val( matchingOption == null ? '' : matchingOption.label );
        });

        // open the dialog
        $dialogElement.dialog({
            title: $pillToEdit ? 'Edit ' + this.options.thingToAdd : 'Add a ' + this.options.thingToAdd,
            autoOpen: true,
            modal: true,
            resizable: false,
            draggable: false,
            buttons: [
                {
                    text: 'Cancel',
                    click: () => {
                        $dialogElement.dialog( 'close' );
                        $dialogElement.remove()
                    }
                }, {
                    text: $pillToEdit ? 'Apply' : 'Add ' + this.options.thingToAdd,
                    class: 'pdqcsv-modal-submit-button',
                    click: () => $dialogElement.find( 'form' ).submit()
                }
            ]
        });

        // running this disables the submit button
        onInputChange();

        // handle form submit, via enter keyboard button or UI submit button
        $dialogElement.find( 'form' ).submit( event => {
            event.preventDefault();
            if ( !formIsValid() ) {
                return;
            }
            $dialogElement.dialog( 'close' );

            let $placeholder;
            if ( $pillToEdit ) {
                // if we're editing a pill, create a placeholder to replace the old pill, then remove the old pill, then add the new one and remove the placeholder.
                // We don't want to add the new one then remove the old one, because that will create undesired effects with which field options are disabled when
                // the user goes to add the next pill
                $placeholder = $('<div>').insertAfter( $pillToEdit );
                this._removePill( $pillToEdit );

            }
            const $newPill = this._addPill( event.target.field.value, $dialogElement.find( '.custom-combobox input' ).val(), event.target.label.value );
            if ( $pillToEdit ) {
                // now reorder the new pill
                $newPill.insertAfter( $placeholder );
                $placeholder.remove();
            }

            $dialogElement.remove();

            this._trigger( 'change', new Event('change') );
        });

        function formIsValid() {
            const form = $dialogElement.find( 'form' )[0];
            return form.field.value && form.label.value;
        }

        function onInputChange() {
            setTimeout( () => {
                $dialogElement.closest( '.ui-dialog' ).find( '.pdqcsv-modal-submit-button' ).button( 'option', 'disabled', !formIsValid() );
            }, 10 );
        }
    },

    _addPill: function( field, fieldDisplayLabel, csvLabel ) {
        if ( !field ) {
            return;
        }
        // mark this value in `propertyOptions` as disabled, so the user can't select it twice
        const matchingOption = this.options.propertyOptions.find( option => option.value === field );
        matchingOption.disabled = true;

        // add a pill
        $newPill = $('<div>').pill({
            data: { field: field, csvLabel: csvLabel },
            label: csvLabel === fieldDisplayLabel ? csvLabel : csvLabel + ' <span class="pdqcsv-field-label-original-name">(' + fieldDisplayLabel + ')</span>',
            showEditButton: true,
            labelAsHtml: true
        })
        .on( 'pilledit', event => this._openDialog($(event.target)) )
        .on( 'pillremove', event => this._removePill($(event.target)) )

        this._$template.find( '.pdqcsv-pills' ).append( $newPill );

        this._setClearAllButtonEnabled();
        return $newPill;
    },

    _addAllFields: function() {
        // add pills for the remaining fields which haven't yet been added
        this.options.propertyOptions.filter( option => !option.disabled )
        .forEach( option => this._addPill(option.value, option.label, option.label) );
    },

    _removePill: function( $pill, setClearAll ) {
        setClearAll = setClearAll === undefined ? true : setClearAll;
        const matchingOption = this.options.propertyOptions.find( option => option.value === $pill.pill('getOptions').data.field );
        $pill.remove();
        // mark this value in `propertyOptions` as enabled
        matchingOption.disabled = false;

        if ( setClearAll ) {
            this._setClearAllButtonEnabled();
        }
    },

    _removeAllPills: function() {
        this._$template.find( '.pdqcsv-pill' ).each( (i, element) => this._removePill($(element), false) );
        this._setClearAllButtonEnabled();
    },

    _setClearAllButtonEnabled: function() {
        // enable or disable the 'clear all' button based on whether there are any pills
        const hasPills = this._$template.find( '.pdqcsv-pill' ).length > 0;
        this._$template.find( '.pdqcsv-multi-fields-clear-fields' ).attr( 'disabled', !hasPills );
        this._$template.find( '.pdqcsv-pills' ).toggle( hasPills );
        this._trigger( 'change', new Event('change') );
    },

    // gets all data regarding the selected fields, formatted for running an export
    getData: function() {
        return this._$template.find( '.pdqcsv-pill' ).map( function() {
            return $(this).pill( 'getOptions' ).data;
        }).get();
    }

  });
});
