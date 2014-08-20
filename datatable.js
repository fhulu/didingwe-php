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
      url: '/?a=page/read&page=mytable
      rows: null*/
      heading: 'We didnt define heading',
      row_actions: {},
      slideSpeed: 300
    },
    
    _create: function()
    {
      $.extend(this.options.row_actions, 
       {'slide': ['<', 'Show more options...'],
        'slideoff': ['>', 'Hide options']
      })
      if (this.hasHeader) this.showHeader();
      if (this.options.titles !== undefined) this.showTitles();
      this.showData();
    },
    
    hasHeader: function()
    {
      var opts = this.options;
      return opts.titles !== undefined || opts.heading !== undefined && opts.page_size !== undefined;
    },
    
    showHeader: function()
    {      
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
      $.each(data, function(i, row) {
        var tr = $('<tr></tr>').appendTo(body);
        tr.attr('_key', row[0]);
        if (i % 2 === 0) tr.addClass('alt');
        var last = row.length-1;
        $.each(row, function(j, cell) {
          if (j===0 && self.options.show_key === undefined) return;
          var td = $('<td></td>').appendTo(tr);
          if (j != last) {
            td.html(cell);
          }
          else {
            self.set_actions(tr, td, cell)
          }
        });
      });
    },
    
    set_actions: function(tr, td, actions)
    {
      if (actions[0] === 'slide') actions.insert(1, 'slideoff');
      var self = this;
      td.addClass('actions');
      var parent = td;
      var height = tr.height();
      $.each(actions, function(k, action) {
        var row_actions = self.options.row_actions[action];
        var div = $('<div></div>');
        div.html(row_actions[0]);
        div.attr('title', row_actions[1]);
        div.attr('action', action);
        div.click(function() {
          tr.trigger('action',[div,action]);
          tr.trigger(action, [div]);
        });
        div.appendTo(parent);
        if (action === 'slide') {
          parent = $('<div class=slide></div>').toggle(false).appendTo(tr);
          parent.find('div')
                  .height(height)
                  .css('line-height', height.toString() + 'px');
        }
      });
      this.bind_actions(tr);
    },
    
    bind_actions: function(tr) 
    {
      var self = this;
      tr.on('slide', function(event, button) {
        button.toggle();
        tr.find('.slide').animate({width:'toggle'}, self.options.slideSpeed);
      });
      tr.on('slideoff', function(event, button) {
        tr.find('.slide').animate({width:'toggle'}, self.options.slideSpeed);
        tr.find('[action=slide]').toggle();
      })

      var data = {_page: self.element.attr('id'), _key: tr.attr('_key')};
      
      tr.on('delete', function() {
        $.json('/?a=page/action', {data: $.extend({}, data, {_field: 'delete'}) }, function(result) {
          tr.trigger('deleted', [result]);
        });
      })
      
      tr.on('deleted', function(result) {
        tr.remove();
//        alert('deleted successfullyy ' + JSON.toString(result));
      });
    }
  })
}) (jQuery);
