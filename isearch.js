$.widget( "custom.isearch", {
  options: {
    categoryPrefix: "-",
    allowNew: false,
    fields:  ['value','name','detail'],
    itemRender: "$name<div>$detail</div>"
  },

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
    this.input = $( "<input>" )
      .appendTo( this.wrapper )
      .attr( "title", "" )
      .addClass( "isearch-input ui-widget ui-widget-content ui-state-default ui-corner-left" )
      .autocomplete({
        delay: 0,
        minLength: 0,
        source: $.proxy( this, "_source" )
      })
      .tooltip({
        tooltipClass: "ui-state-highlight"
      })
      .on( "autocompleteselect", function( event, ui ) {
        el.val(ui.item.value);
        console.log(ui.item, "val", el.val())
    });
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
    response( [
        {label: "hello", text: "africa", value: "a"},
        {label: "ndaa", text: "mudini", value: "b"}
      ]
    );
  },
  _renderItem: function( ul, item ) {
    console.log(item)
    var code = item[0];
    if (code[0] == this.options.categoryPrefix)
      return $("<li>").append($("<a>").text(code.substr(1))).addClass('.isearch-category').appendTo(ul);
    var text = this.options.itemRender;
    $.each(this.options.fields, function(i, field) {
      var val = i < item.length? item[i]: "";
      text = text.replace(new RegExp('\\$'+field+"([\b\W]|$)?", 'g'), val+'$1');
    });
  }
});
