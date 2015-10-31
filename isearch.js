$.widget( "custom.isearch", {
  options: {
    categoryPrefix: "-",
    allowNew: false,
    fields:  ['value','name','detail'],
    render: "$name<div>$detail</div>",
    chosen: "$name"
  },

  self: this,

  _create: function ()
  {
    var el = this.element
    this.wrapper = $("<span>")
      .addClass("isearch input")
      .insertAfter(el)
      .css('width', el.css('width'))
      .append(el)

    this.element.hide();
    this._createAutocomplete();
    this._createShowAllButton();
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
        var text = mkn.replaceFields(options.render, options.fields, item.data);
        return $("<li>")
          .append($("<a>"+text+"</a>"))
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
      .appendTo( this.wrapper )
      .button({
        icons: {
          primary: "ui-icon-triangle-1-s"
        },
        text: false
      })
      .removeClass( "ui-corner-all" )
      .addClass( "isearch-toggle ui-corner-right" )
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
