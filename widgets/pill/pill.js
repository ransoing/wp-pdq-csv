/**
 * A custom jqueryUI widget -- A single pill with buttons to edit or remove the pill.
 * Fires 'pilledit' or 'pillremove' events when the edit or remove buttons are clicked.
 * 
 * Example HTML:
 * <div id="foo"></div>
 * 
 * In JS:
 * $( '#foo' ).pill({
 *  data: { 'any-object': 'any-value' },
 *  label: 'Words'
 * });
 */
jQuery( function($) {

  $.widget( 'custom.pill', {
    // default options
    options: {
        data: '',
        label: '',
        labelAsHtml: false,
        showEditButton: false,
        showRemoveButton: true
    },

    getOptions: function() {
        return this.options;
    },

    _create: function() {
        // the HTML for this widget is stored in readable format in an HTML file, and made available as a
        // global var in `pill.enqueue.php`
        this._$template = $( pdqcsvPillHtml );
        // effectively replace the target element with the HTML found in the template
        this.element.addClass( this._$template.attr('class') );
        this.element.html( this._$template.html() );
        this._$template = this.element;

        const $label = this._$template.find( '.pdqcsv-pill-label' );
        this.options.labelAsHtml ? $label.html( this.options.label ) : $label.text( this.options.label );

        // hide buttons if needed and emit events when clicked
        this._$template.find( '.pdqcsv-pill-edit' ).toggle( this.options.showEditButton ).click(
            () => this._trigger( 'edit', new Event('edit', this._$template[0]) )
        );
        this._$template.find( '.pdqcsv-pill-remove' ).toggle( this.options.showRemoveButton ).click(
            () => this._trigger( 'remove', new Event('remove', this._$template[0]) )
        );
    },

    _destroy: function() {
        // this.element.remove();
    }

  });
});
