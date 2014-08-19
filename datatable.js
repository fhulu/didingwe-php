///~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// Author     : Fhulu Lidzhade
// Date       : 17/06/2012   20:39
// Description:
//   table.js defines a jquery ui extension enhancing html tables. It adds featues 
//   such as:
//    1) sorting by field on column header click
//    2) paging with adjustable page size
//    3) customable row expansion/collapse when required
//    4) editable rows including select boxes
//    5) dynamic addition of rows
//    6) dynamic deletion of rows
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

(function( $ ) {
  $.widget( "ui.datatable", {
    options: {/*
      titles: null,
      fields: null, //todo: 'underscores'
      filter: null, //todo: all
      sortable: null, //todo: all
      sort_field: null,
      heading: null,
      page_size: null,
      expandable: null,
      actions: null,
      row_actions: null,
      rows: null*/
      heading: 'We didnt define heading'
    },
    
    _create: function()
    {
      this.showHeader();
      this.showTitles();
      this.showData();
    },
    
    showHeader: function()
    {
      var opts = this.options;
      if (opts.titles === undefined && opts.heading === undefined && opts.page_size === undefined ) 
        return;
      
      var head = this.element.find('thead');
      if (!head.exists()) 
        head = $('<thead></thead>').prependTo(this.element);
      else
        head.html('');
      var tr = $('<tr class=header></tr>').appendTo(head);
      var th = $('<th></th>').attr('colspan', this.options.titles.length).appendTo(tr);
      if (this.options.heading !== undefined)
        $('<div class=heading></div>').html(this.options.heading).appendTo(th);
      
      if (this.options.filter !== undefined)
        $('<div class=filtering title="Filter/Search"></div>').appendTo(th);
      
      if (this.options.page_size !== undefined) {
        var paging = $('<div class=paging></div>').appendTo(th);
        paging.html('Showing from<div class=from></div> to <div class=to></div> of <div class=total></div>');
        $('<button nav=prev disabled></button>').appendTo(paging);
        $('<input type=text></input>').val(this.options.page_size).appendTo(paging);
        $('<button nav=next disabled></button>').appendTo(paging);
      }
    },
    
    
    showTitles: function(titles)
    {
      if (this.options.titles === undefined) return;
      
      var head = this.element.find('thead');
      if (titles === undefined) titles = this.options.titles;
      var tr = $('<tr class=titles></tr>').appendTo(head);
      $.each(titles, function(i, title) {
        $('<th></th>').html(title).appendTo(tr);
      });
    },
    
    showData: function(data)
    {
      var self = this;
      var body = this.element.find('tbody');
      if (data === undefined) data = this.options.rows;
      var row_actions = this.options.row_actions;
      $.each(data, function(i, row) {
        var tr = $('<tr></tr>').appendTo(body);
        var last = row.length-1;
        $.each(row, function(j, cell) {
          var td = $('<td></td>');
          if (j != last) {
            td.html(cell);
          }
          else {
            td.addClass('actions');
            $.each(cell, function(k, action) {
              var actions = self.options.row_actions[action];
              $('<div></div>')
                      .attr('action',action)
                      .attr('title', actions[1])
                      .html(actions[0])
                      .appendTo(td);
            });
          }
          td.appendTo(tr);
        });
      });
    }
  })
}) (jQuery);
