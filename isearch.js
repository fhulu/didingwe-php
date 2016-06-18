$.widget( "custom.isearch", {
  options: {
    categoryPrefix: "-",
    fields:  ['value','name'],
    display: "$name",
    chosen: "$name",
    flags: []
  },

  self: this,

  hasFlag: function(flag) {
    return this.options.flags.indexOf(flag) >= 0;
  },

  _create: function ()
  {
    var el = this.element
    this.wrapper = $("<span>")
      .addClass("isearch input")
      .insertAfter(el)
      .css('width', el.css('width'))
      .append(el)


    el.hide();
    this._createAutocomplete();
    if (this.hasFlag('show_all') || this.options.adder !== undefined) {
      this.buttons = $('<span>').addClass('isearch-buttons').appendTo(this.wrapper);
      if (this.hasFlag('show_all')) this._createShowAllButton();
      if (this.options.adder !== undefined) this._createAddNewButton();
    }
  },

  _createAutocomplete: function() {
    var el = this.element;
    var options = this.options;
    this.input = $( "<input>" );
    this.input.appendTo( this.wrapper )
      .attr( "title", "" )
      .addClass( "isearch-input ui-widget ui-widget-content ui-state-default ui-corner-left" )
      .autocomplete({
        delay: 0,
        minLength: 0,
        source: $.proxy( this, "_source" ),
      })
      .tooltip({
        tooltipClass: "ui-state-highlight"
      })
      .autocomplete("instance")._renderItem =  function( ul, item ) {
        if (!item) return;
        var text = mkn.replaceFields(options.display, options.fields, item.data);
        return $("<li>")
          .text(text)
          .appendTo(ul)
      }

    this._on( this.input, {
      autocompleteselect: function( event, ui ) {
        el.val(ui.item.code);
      }
    });
    el.change(function() {

    })
  },

  _createShowAllButton: function() {
    var input = this.input,
      wasOpen = false;

    $( "<a>" )
      .attr( "tabIndex", -1 )
      .attr( "title", "Show All Items" )
      .tooltip()
      .appendTo( this.buttons )
      .button({
        icons: {
          primary: "ui-icon-triangle-1-s"
        },
        text: false
      })
      .removeClass( "ui-corner-all" )
      .addClass( "isearch-toggle" )
      .mousedown(function() {
        wasOpen = input.autocomplete( "widget" ).is( ":visible" );
      })
      .click(function() {
        input.focus();

        // Close if already visible
        if ( wasOpen ) {
          return;
        }

        // Pass empty string as value to search for, displaying all results
        input.autocomplete( "search", "" );
      });
  },
  _createAddNewButton: function() {
    var self = this;
    $( "<a>" )
      .attr( "tabIndex", -1 )
      .attr( "title", "Add New Item" )
      .tooltip()
      .appendTo( this.buttons )
      .button({
        icons: {
          primary: "ui-icon-plus"
        },
        text: false
      })
      .removeClass( "ui-corner-all" )
      .addClass( "isearch-add" )
      .click(function() {
        mkn.showDialog(self.options.adder, self.options);
      });
  },

  _source: function( request, response ) {
    var opts = this.options;
    var data = {action: 'data', path: opts.path, key: opts.key, term: this.input.val() };
    var selector = opts.selector;
    if (selector !== undefined) {
      $.extend(data, $(selector).values());
    }
    var el = this.element;
    $.json('/', {data: data}, function(data) {
      if (!data) return;
      if (data._responses)
        el.trigger('server_response', data);
      el.trigger('refreshing', [data]);
      response( data.rows.map(function(val) {
        return { code: val[0], data: val, label: mkn.replaceFields(opts.chosen, opts.fields, val) };
      }));
    });
  },

});
