/**
 * A custom jqueryUI widget -- a select dropdown with an autocomplete search box. Taken and modified from https://jqueryui.com/autocomplete/#combobox
 * Example HTML:
 * <div class="ui-widget">
 *   <label>
 *     <select id="foo">
 *       <option value></option>
 *       <option value="bar">Bar</option>
 *     </select>
 *   </label>
 * </div>
 * 
 * In JS:
 * $( '#foo' ).combobox();
 */
jQuery( function($) {
    $.widget( "custom.combobox", {
      _create: function() {
        // the HTML for this widget is stored in readable format in an HTML file, and made available as a
        // global var in `combobox.enqueue.php`
        this._$template = $( pdqcsvComboboxHtml );
        this._$template.insertAfter( this.element );
 
        // add a blank <option> to the select element
        const blankOption = $( '<option value="" disabled>' );
        this.element.prepend( blankOption );
        if ( this.element.find('[selected]').length === 0 ) blankOption.attr('selected', true);
        this.element.hide();
        this.element.change( function(event) {
          this._trigger( 'change', event );
        }.bind(this) );
        this._createAutocomplete();
      },
 
      _createAutocomplete: function() {
        var selected = this.element.find( ":selected" ),
          value = selected.val() ? selected.text() : "",
          wasOpen = false,
          element = this.element;
 
        this._$input = this._$template.find( 'input' ).val( value ).autocomplete({
            delay: 0,
            minLength: 0,
            source: $.proxy( this, "_source" )
          }).tooltip({
            classes: {
              "ui-tooltip": "ui-state-highlight"
            }
          }).on( "mousedown", function() {
            wasOpen = $(this).autocomplete( "widget" ).is( ":visible" );
          }).on( "click", function() {
            $(this).trigger( "focus" );
 
            // Close if already visible
            if ( wasOpen ) {
              return;
            }
 
            // Pass empty string as value to search for, displaying all results
            $(this).autocomplete( "search", "" );
          }).on( "blur", function() {
            if ( $(this).val() !== element.val() && !$(this).val() ) {
              element.val( $(this).val() );
              element.change();
            }
          });

          // create a custom item renderer on the autocomplete so we can disable items
          this._$input.data( 'ui-autocomplete' )._renderItem = function(ul, item) {
            if ( item.isGroupHeader ) {
              return $( '<li>' ).text( item.label ).appendTo( ul ).addClass( 'pdqcsv-autocomplete-item-header' );
            } else {
              return $( '<li>' ).append( item.label ).appendTo( ul ).toggleClass( 'disabled', item.option.disabled );
            }
          };
 
        this._on( this._$input, {
          autocompleteselect: function( event, ui ) {
            ui.item.option.selected = true;
            this._trigger( "select", event, {
              item: ui.item.option
            });
            this.element.change();
          },
 
          autocompletechange: "_removeIfInvalid"
        });
      },
 
      _source: function( request, response ) {
        var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
        let elements = [];
        this.element.children().each( function() { // loop through immediate children; optgroups and options which are not part of a group
          const tagName = this.tagName.toLowerCase();
          if ( tagName === 'option' ) {
            elements.push( getMappedOption(this) );
          } else if ( tagName === 'optgroup' ) {
            // get all options in this group first, to see if there's any that match, before outputting the group title
            const matchingOptions = $(this).children().map( (i, el) => getMappedOption(el) ).filter( (i, el) => !!el );
            if ( matchingOptions.length > 0 ) {
              elements.push( { label: $(this).attr('label'), isGroupHeader: true } );
              elements = elements.concat( matchingOptions.get() );
            }
          }
        });
        response( elements.filter(element => !!element) );

        function getMappedOption( option ) {
          var text = $( option ).text();
          if ( option.value && ( !request.term || matcher.test(text) ) ) {
            return {
              label: text,
              value: text,
              option: option
            };
          }
        }
      },
 
      _removeIfInvalid: function( event, ui ) {
        // Selected an item, nothing to do
        if ( ui.item && !ui.item.option.disabled ) {
          return;
        }
 
        // Search for a match (case-insensitive)
        var value = this._$input.val(),
          valueLowerCase = value.toLowerCase(),
          valid = false;
        this.element.find( "option" ).each(function() {
          if ( $( this ).text().toLowerCase() === valueLowerCase && !this.disabled ) {
            this.selected = valid = true;
            return false;
          }
        });
 
        // Found a match, nothing to do
        if ( valid ) {
          this.element.change();
          return;
        }
 
        // Remove invalid value
        this._$input
          .val( "" )
          .attr( "title", value + " didn't match any item" )
          .tooltip( "open" );
        this.element.val( "" );
        this.element.change();
        this._delay(function() {
          this._$input.tooltip( "close" ).attr( "title", "" );
        }, 2500 );
        this._$input.autocomplete( "instance" ).term = "";
      },
 
      _destroy: function() {
        this._$template.remove();
        this.element.show();
      },

      getValue: function() {
        return this.element.val();
      }
    });
});
