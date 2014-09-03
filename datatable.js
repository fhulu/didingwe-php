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
      name: 'We didnt define heading',
      flags: [],
      slideSpeed: 300
    },
    
    _create: function()
    {
      /*
      $.extend(this.options.actions, 
       { slide: {name: '<', desc: 'Show more options...'},
         slideoff: {name: '>', desc: 'Hide options'}
      })*/
      var self = this;
      var id = this.element.attr('id');
      if (this.hasFlag('show_header')) this.showHeader();
      if (this.options.fields !== undefined && this.hasFlag('show_titles') !== undefined) this.showTitles();
      if (this.options.rows !== undefined)
        this.showData(this.options.row);
      else
        $.json('/?a=page/table', {data: {field: id}}, function(data) {
          if (data === undefined || data === null) {
            console.log('No table data for table:', id);
            return;
          }
          if (self.hasFlag('show_titles')) self.showTitles(data.fields);
          self.showData(data.rows);
       })
    },
    
    hasFlag: function(flag)
    {
      return this.options.flags.indexOf(flag) >= 0;
    },
    
    hasHeader: function()
    {
      var opts = this.options;
      return opts.name !== undefined && opts.page_size !== undefined;
    },
    
    showHeader: function()
    {      
      var head = this.element.find('thead');
      if (!head.exists()) 
        head = $('<thead></thead>').prependTo(this.element);
      else
        head.html('');
      var tr = $('<tr class=header></tr>').appendTo(head);
      var th = $('<th></th>').appendTo(tr);
      if (this.options.name !== undefined)
        $('<div class=heading></div>').html(this.options.name).appendTo(th);
      
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
    
    
    showTitles: function(fields)
    {      
      var head = this.element.find('thead');
      if (fields === undefined) fields = this.options.fields;
      var tr = $('<tr class=titles></tr>').appendTo(head);
      var count = 0;
      var self = this;
      $.each(fields, function(code, props) {
        if (++count == 1 && !self.hasFlag('show_key')) return;
        if (code=='html' || code == 'template' || code == 'actions' ) return;
        var name = props===null? code: props.name;
        $('<th></th>').html(name).appendTo(tr);
      });
    },
    
    showData: function(data)
    {
      var self = this;
      var body = this.element.find('tbody');
      var show_key = this.hasFlag('show_key');
      $.each(data, function(i, row) {
        var tr = $('<tr></tr>').appendTo(body);
        tr.attr('_key', row[0]);
        if (i % 2 === 0) tr.addClass('alt');
        var last = row.length-1;
        $.each(row, function(j, cell) {
          if (j===0 && !show_key) return;
          var td = $('<td></td>').appendTo(tr);
          if (j != last) {
            td.html(cell);
          }
          else {
            self.set_actions(tr, td, cell)
          }
        });
      });
      var column_count = body.find('tr:first-child td').length;
      this.element.find('tr.header th').attr('colspan', column_count);
      this.adjust_actions_height();
    },
    
    set_actions: function(tr, td, actions)
    {
      if (!$.isArray(actions)) actions = actions.split(',');
      if (actions[0] === 'slide') actions.insert(1, 'slideoff');
      var self = this;
      td.addClass('actions');
      var parent = tr;
      $.each(actions, function(k, action) {        
        var props = self.options[action];
        var div = $('<span>');
        div.html(props.name);
        div.attr('title', props.desc);
        div.attr('action', action);
        div.click(function() {
          tr.trigger('action',[div,action,props.action]);
          tr.trigger(action, [div,props.action]);
        });
        div.appendTo(parent);
        if (action === 'slide') {
          parent = $('<span class=slide>').toggle(false).appendTo(tr);
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
      tr.on('slideoff', function() {
        tr.find('.slide').animate({width:'toggle'}, self.options.slideSpeed);
        tr.find('[action=slide]').toggle();
      });

      var key = tr.attr('_key');
      
      tr.on('action', function(event, button, name, value) {
        if (value === undefined) return;
        if (value.indexOf('dialog:') === 0) {
          var page = value.substr(7);
          var data = {page: page, key: key, load:""};
          var tmp = $('<div></div>').page({data:data});
          tmp.on('read_'+page, function(event, object, options) {
            object.dialog($.extend({modal:true}, options));
          });
        }
        else if (value.indexOf('url:') === 0) {
          document.location = value.substr(4).replace('$key', key);
        }
        else {
          var data = {_page: self.element.attr('id'), _key: key,  _field: name};
          $.json('/?a=page/action', {data: data}, function(result) {
            tr.trigger('processed_'+name, [result]);
          });
        }
      })
      
      tr.on('processed_delete', function(result) {
        tr.remove();
//        alert('deleted successfullyy ' + JSON.toString(result));
      });
    },
    
    adjust_actions_height: function()
    {
      this.element.find("tbody tr").each(function() {
        var row = $(this);
        var height = row.height()*0.98;
        row.find('.slide,[action]').height(height).css('line-height', height.toString()+'px');
      });
    }
  })
}) (jQuery);
