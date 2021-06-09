/**
 * A custom jqueryUI widget -- a set of inputs to filter a database query
 * The inputs are:
 * [ object property ] [ rule ] [ compare-to-value ] [ cast-as ]
 * 
 * The object property is any property of a user/post/etc. i.e. a user's email, a post's title, or any meta key.
 * The rule is something like `less than`, `equal to`, etc.
 * The compare-to-value is simply the value we're comparing the object property to.
 * Cast-as tells the database how to cast the object property and the compare-to-value. This is essential because
 *    wordpress stores every meta_value as a string, which causes bugs particularly when trying to filter by numeric value.
 *    Casting both values as string when filtering objects allows the filter to work correctly.
 * 
 * Example HTML:
 *  <div id="foo"></div>
 * 
 * In JS:
 *  $( '#foo' ).filterinputs({
 *      propertyLabel: 'Bar',
 *      propertyOptions: [
 *          { value: 'baz1', label: 'Baz1' },
 *          { value: '-', label: '------', disabled: true },
 *          { value: 'baz2', label: 'Baz2' }
 *      ]
 *  });
 */
jQuery( function($) {

    $.widget( 'custom.filterinputs', {
        // default options
        options: {
            propertyLabel: 'Choose one',
            propertyOptions: [], // : { label: string, value: string, disabled: boolean }[]
            initialValues: undefined, // an array of 4 values, corresponding to the 4 inputs
            objectType: ''
        },

        _create: function() {
            // the HTML for this widget is stored in readable format in an HTML file, and made available as a
            // global var in `filter-inputs.enqueue.php`
            this._$template = $( pdqcsvFilterInputsHtml );
            this.element.append( this._$template );

            this._$propertySelect = this._$template.find( '.pdqcsv-property-field select');
            this._$ruleSelect = this._$template.find( '.pdqcsv-rule-field select');
            this._$valueInput = this._$template.find( '.pdqcsv-value-field input');
            this._$castSelect = this._$template.find( '.pdqcsv-cast-field select');

            // add a label to the first combobox
            this._$template.find( '.pdqcsv-property-field label' ).text( this.options.propertyLabel );
            // add property options to the first combobox, grouped by field type
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
                        $optgroup.append(
                            $( '<option>' ).attr( 'value', option.value ).text( option.label ).attr( 'disabled', option.disabled )
                        );
                    });
                    this._$propertySelect.append( $optgroup );
                }
            });

            // set initial values if they were given
            if ( this.options.initialValues ) {
                this._selectOption( this._$propertySelect, this.options.initialValues[0] );
                this._selectOption( this._$ruleSelect, this.options.initialValues[1] );
                this._$valueInput.val( this.options.initialValues[2] );
                this._selectOption( this._$castSelect, this.options.initialValues[3] );
            }

            // initialize all combobox widgets
            this._$template.find( 'select' ).combobox();

            // disable the value input and cast input in certain cases
            this._$ruleSelect.change( () => this._setFieldsEnabledOrDisabled() );
            // run the onchange function in case initial values are such that some inputs should be disabled
            this._setFieldsEnabledOrDisabled();

            // when the property select changes, hide the example values, and maybe change restrictions on rules
            this._$propertySelect.change( () => {
                this._$template.find( '.pdqcsv-example-values, .toggle-refresh' ).toggle( false );
                this._$template.find( '.toggle-show' ).toggle( true );
                this._$template.find( '.pdqcsv-toggle' ).toggle( this._$propertySelect.val() != '' && this._$propertySelect.val() != null );

                // restrict which rules are selectable if the field is a taxonomy
                if ( this._$propertySelect.val() && this._$propertySelect.val().indexOf('taxonomy.') === 0 ) {
                    const allowedFields = ['LIKE', 'NOT LIKE', 'empty', 'not empty'];
                    this._$ruleSelect.find( 'option' ).each( function() {
                        $(this).attr('disabled', allowedFields.indexOf($(this).val()) === -1);
                    });
                    // if a now-disabled option is selected, clear the selection
                    if ( allowedFields.indexOf(this._$ruleSelect.val()) === -1 ) {
                        this._$ruleSelect.val( null );
                        this._$ruleSelect.change();
                        this._$ruleSelect.parent().find('input').val( '' );
                    }
                } else {
                    this._$ruleSelect.find( 'option' ).attr('disabled', false);
                }
            });
            this._$propertySelect.change();
            // load example values
            this._$template.find( '.pdqcsv-toggle' ).click( () => {
                this._$template.find( '.pdqcsv-example-values, .pdqcsv-example-values .lds-hourglass, .toggle-refresh' ).toggle( true );
                this._$template.find( '.toggle-show, .pdqcsv-example-results' ).toggle( false );
                $.getJSON( ajaxurl, { action: 'pdqcsv_getExampleValues', objectType: this.options.objectType, field: this._$propertySelect.val() } ).then( response => {
                    this._$template.find( '.pdqcsv-example-values .lds-hourglass' ).toggle( false );
                    this._$template.find( '.pdqcsv-example-results' ).toggle( true ).html( response.length > 0 ? response.join('<br>') : '(No values found)' );
                });
            });
        },

        _destroy: function() {
            this._$template.remove();
        },

        // adds a [selected] attribute to the <option> in a <select> which has a given value
        _selectOption( $select, value ) {
            if ( !value ) return;
            $option = $select.find( 'option' ).filter( function() {
                return $(this).val() === value;
            });
            if ( $option ) {
                $option.attr( 'selected', true );
            }
        },

        _setFieldsEnabledOrDisabled: function() {
            const ruleVal = this._$ruleSelect.val();
            // disable the `value` input if the rule doesn't need a value
            const disableValue = !!( ruleVal && ruleVal.match(/empty|not empty/) );
            this._$valueInput.prop( 'disabled', disableValue ).toggleClass( 'disabled', disableValue );
            if ( disableValue ) {
                this._$valueInput.val( '' );
            }
            // only enable the `cast` input if the rule involves an order
            const disableCast = !!( ruleVal && (!ruleVal.match(/[<>]/) || ruleVal == '<>') );
            const $castInput = this._$template.find( '.pdqcsv-cast-field input' );
            $castInput.prop( 'disabled', disableCast ).toggleClass( 'disabled', disableCast );
            this._$castSelect.prop( 'disabled', disableCast );
            if ( disableCast ) {
                $castInput.val( '' );
                this._$castSelect.val( '' );
            }
        }

    });
});
