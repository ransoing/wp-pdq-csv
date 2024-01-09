/**
 * A custom jqueryUI widget -- controls to add multiple filter-inputs.
 * Fires 'multifiltersupdate' event when the filters have been updated.
 * The inputs are:
 * 
 * Example HTML:
 *  <div id="foo"></div>
 * 
 * In JS:
 *  $( '#foo' ).filterinputs({
 *      propertyLabel: 'Field',
 *      propertyOptions: [
 *          { value: 'baz1', label: 'Baz1' },
 *          { value: '-', label: '------', disabled: true },
 *          { value: 'baz2', label: 'Baz2' }
 *      ]
 *  });
 */
jQuery( function($) {

    $.widget( 'custom.multifilters', {
        // default options
        options: {
            propertyLabel: 'Choose one',
            propertyOptions: [], // : { label: string, value: string, disabled: boolean }[]
            initialFilters: [], // : { field: string, rule: string, value: string, cast: string }[]
            objectType: ''
        },

        _create: function() {
            // the HTML for this widget is stored in readable format in an HTML file, and made available as a
            // global var in `multi-filters.enqueue.php`
            this._$template = $( pdqcsvMultiFiltersHtml ).find( '[data-template-part-main]' );
            this.element.append( this._$template );

            // add variables for commonly used elements and slightly modify some of them
            this._$filtersSection = this._$template.find( '.pdqcsv-filter-inputs-list' );
            this._$addFilterButton = this._$template.find( '.pdqcsv-add-filter-button' ).click( () => this._openDialog() );
            this._$clearFiltersButton = this._$template.find( '.pdqcsv-clear-filters-button' ).click( () => this._removeAllFilters() );

            this._toggleFiltersVisibility();

            this.options.initialFilters.forEach( filter => {
                // find labels for each value
                try {
                    const fieldLabel = filter.field ? this.options.propertyOptions.find( option => option.value === filter.field ).label : undefined;
                    const ruleLabel = filter.rule ? $( pdqcsvFilterInputsHtml ).find('select[name=rule] option[value="' + filter.rule + '"]').text() : undefined;
                    const castLabel = filter.cast ? $( pdqcsvFilterInputsHtml ).find('select[name=cast] option[value="' + filter.cast + '"]').text() : undefined;
                    this._addFilter( filter.field, fieldLabel, filter.rule, ruleLabel, filter.value, filter.cast, castLabel );
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
            const $dialogElement = $( pdqcsvMultiFiltersHtml ).find( '[data-template-part-dialog]' );

            let initialValues = undefined;
            if ( $pillToEdit ) {
                // set the form fields to match the filter we're editing
                const pillData = $pillToEdit.pill( 'getOptions' ).data;
                initialValues = [
                    pillData.field,
                    pillData.rule,
                    pillData.value,
                    pillData.cast
                ];
            }

            // initialize the set of inputs
            $dialogElement.find( '[data-filter-inputs]' ).filterinputs({
                propertyLabel: this.options.propertyLabel,
                propertyOptions: this.options.propertyOptions,
                initialValues: initialValues,
                objectType: this.options.objectType
            });

            // open the dialog
            $dialogElement.dialog({
                title: $pillToEdit ? 'Edit filter' : 'Add a filter',
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
                        text: $pillToEdit ? 'Apply' : 'Add filter',
                        class: 'pdqcsv-modal-submit-button',
                        click: () => $dialogElement.find( 'form' ).submit()
                    }
                ]
            });

            // set the form inputs to trigger onInputChange when they change
            const form = $dialogElement.find( 'form' )[0];
            const formInputs = [ form.field, form.rule, form.value, form.cast ];
            formInputs.forEach( input => $(input).change(onInputChange) );
            $( form.value ).keyup( onInputChange );
    
            // running this disables the submit button
            onInputChange();
    
            // handle form submit, via enter keyboard button or UI submit button
            $dialogElement.find( 'form' ).submit( event => {
                event.preventDefault();
                if ( !formIsValid() ) {
                    return;
                }
                $dialogElement.dialog( 'close' );

                // add a new filter
                $fieldOption = $(form.field).find('option:selected');
                $ruleOption = $(form.rule).find('option:selected');
                $castOption = $(form.cast).find('option:selected');
                $newFilter = this._addFilter(
                    $fieldOption.val(), $fieldOption.text(),
                    $ruleOption.val(), $ruleOption.text(),
                    form.value.value,
                    $castOption.val(), $castOption.text()
                );

                // if we were trying to edit a filter, insert the new one after the old one and remove the old one
                if ( $pillToEdit ) {
                    $newFilter.insertAfter( $pillToEdit );
                    $pillToEdit.remove();
                }

                $dialogElement.remove();

                this._triggerUpdateEvent();
            });
    
            function formIsValid() {
                // the form is valid if all inputs that are enabled have a value
                return formInputs.every( input => input.value || input.disabled );
            }
    
            function onInputChange() {
                setTimeout( () => {
                    $dialogElement.closest( '.ui-dialog' ).find( '.pdqcsv-modal-submit-button' ).button( 'option', 'disabled', !formIsValid() );
                }, 10 );
            }
        },

        _triggerUpdateEvent() {
            this._trigger( 'update', new Event('update', this._$template[0]) );
        },

        /** Adds a new filter "pill" to the list and returns the new pill */
        _addFilter: function( field, fieldLabel, rule, ruleLabel, value, cast, castLabel ) {
            // add a pill in the added-filters section to keep track of the filters the user has created
            const labelParts = [ fieldLabel, ruleLabel, value, castLabel ? 'Compare ' + castLabel : '' ];
            const fullLabel = labelParts.filter( part => part && part.length > 0 ).map(
                part => $( '<span class="pdqcsv-filter-pill-part"></span>' ).text( part ).prop( 'outerHTML' )
            );
            $newPill = $('<div>').pill({
                data: { field: field, rule: rule, value: value, cast: cast },
                label: fullLabel,
                labelAsHtml: true,
                showEditButton: true
            })
            .on( 'pilledit', event => this._openDialog($(event.target)) )
            .on( 'pillremove', event => this._removeFilter($(event.target)) )

            this._$filtersSection.append( $newPill );

            this._toggleFiltersVisibility();
            return $newPill;
        },

        _removeFilter: function( $filterPill ) {
            $filterPill.remove();
            this._toggleFiltersVisibility();
            this._triggerUpdateEvent();
        },

        _removeAllFilters: function() {
            this._$filtersSection.find( '.pdqcsv-pill' ).each( (i, element) => this._removeFilter($(element)) );
        },

        _toggleFiltersVisibility: function() {
            // show/hide the filters section depending on whether there are any filters added
            const hasFilters = this._$template.find('.pdqcsv-pill').length > 0;
            this._$filtersSection.toggle( hasFilters );
            this._$clearFiltersButton.attr( 'disabled', !hasFilters );
        },

        // gets all data regarding the selected filters, formatted for running an export
        getData: function() {
            return this._$filtersSection.find( '.pdqcsv-pill' ).map( function() {
                return $(this).pill( 'getOptions' ).data;
            }).get();
        }

    });
});
