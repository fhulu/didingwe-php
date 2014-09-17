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
      name: 'Undefined',
      flags: [],
      slideSpeed: 300
    },
    
    _create: function()
    {
      if (this.hasFlag('show_titles') || this.hasFlag('show_header')) {
        $('<thead></thead>').prependTo(this.element);
        if (this.hasFlag('show_header')) this.showHeader();
      }
      if (this.options.rows !== undefined)
        this.showData(this.options.rows);
      else
        this.load();
    },
   
    load: function()
    {
      var self = this;
      var id = this.element.attr('id');
      var head = this.element.children('thead').eq(0);
      head.find('.titles').empty();
      var page_size = head.find('#page_size').eq(0);
      var data = {field: id, key: this.options.key};
      if (page_size.exists) data.page_size = page_size.val();
      $.json('/?a=datatable/read', {data: data}, function(data) {
        if (data === undefined || data === null) {
          console.log('No table data for table:', id);
          return;
        }
        if (self.hasFlag('show_titles')) self.showTitles(data.fields);
        self.showData(data);
      });
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
      head.html('');
      var tr = $('<tr class=header></tr>').appendTo(head);
      var th = $('<th></th>').appendTo(tr);
      if (this.options.name !== undefined)
        $('<div class=heading></div>').html(this.options.name).appendTo(th);
      
      if (this.options.filter != null)
        $('<div class=filtering title="Filter/Search"></div>').appendTo(th);
      
      if (this.options.page_size != null) this.showPaging(th);
    },
    
    showPaging: function(th)
    {
      var paging = $('<div class=paging></div>').appendTo(th);
      $('<div>Show</div>').appendTo(paging);
      var self = this;
      $('<input id=page_size type=text></input>').val(this.options.page_size).appendTo(paging)
      $('<div>entries per page</div>').appendTo(paging);
      this.createAction('goto_first_page').attr('disabled','').appendTo(paging);
      this.createAction('goto_prev_page').attr('disabled','').appendTo(paging);
      $('<input id=page_num type=text></input>').val(1).appendTo(paging);
      this.createAction('goto_next_page').attr('disabled','').appendTo(paging);
      this.createAction('goto_last_page').attr('disabled','').appendTo(paging);
      
      paging.find("[type='text']").bind('keyup input cut paste', function(e) {
        if (e.keyCode == 13) self.load();
      });      
    },
    
    showTitles: function(fields)
    {      
      var head = this.element.children('thead').eq(0);
      if (fields === undefined) fields = this.options.fields;
      var tr = $('<tr class=titles></tr>').appendTo(head);
      var count = 0;
      var self = this;
      var show_key = self.hasFlag('show_key');
      $.each(fields, function(i, field) {
        if (i === 0 && !show_key) return;
        var code = field.code;
        var th = $('<th></th>').appendTo(tr);
        if (field.code !== 'actions')
          th.html(field.name===null? code: field.name);
      });
      if (this.hasHeader())
        tr.prev().attr('colspan', tr.find('td').length);
    },
    
    
    showData: function(data)
    {
      var self = this;
      var body = this.element.children('tbody').eq(0);
      body.empty();
      var show_key = this.hasFlag('show_key');
      var expandable = data.actions !== undefined && data.actions.expand !== undefined;
      var show_edits = this.hasFlag('show_edits');
      var all_actions = data.actions;
      $.each(data.rows, function(i, row) {
        var row = data.rows[i];
        var tr = $('<tr></tr>').appendTo(body);
        var key = row[0];
        tr.attr('_key', key);
        if (i % 2 === 0) tr.addClass('alt');
        $.each(row, function(j, cell) {
          if (j===0 && !show_key) return;
          var td = $('<td></td>').appendTo(tr);
          var field = data.fields[j];
          if (field.code !== 'actions') {
            self.showCell(show_edits, field, td, cell, key);
            if (j === 0 && expandable) {
              self.createAction('expand', all_actions, tr).prependTo(td);
              self.createAction('collapse', all_actions, tr).prependTo(td).hide();
            }
          }
          else {
            self.setRowActions(tr, td, all_actions, cell);
          }
        });
      });
      var column_count = body.find('tr:first-child>td').length;
      this.element.find('tr.header th').attr('colspan', column_count);
      this.adjust_actions_height();
    },
    
    showCell: function(editable, field, td, value, key)
    {
      if (!editable || !$.valid(field.html)) {
        td.html(value);
        return;
      }
      var html = field.html.replace('$code', key+'_'+field.code);
      $(html).value(value).appendTo(td);
    },
    
    createAction: function(action, actions, sink)
    {
      if (actions === undefined) actions = this.options;
      if (sink === undefined) sink = this.element;
      var props = actions[action];
      if (props === undefined) console.log(action, actions);
      var div = $('<span>');
      div.html(props.name);
      div.attr('title', props.desc);
      div.attr('action', action);
      div.click(function() {
        sink.trigger('action',[div,action,props.action]);
        sink.trigger(action, [div,props.action]);
      });
      return div;
    },
    
    setRowActions: function(tr, td, all_actions, row_actions)
    {
      if (!$.isArray(row_actions)) row_actions = row_actions.split(',');
      var self = this;
      td.addClass('actions');
      var parent = td;
      $.each(row_actions, function(i, action) {  
        if (action === 'expand') return;
        self.createAction(action, all_actions, tr).appendTo(parent);
        if (action === 'slide') {
          parent = $('<span class="slide">').toggle(false).appendTo(parent);
          self.createAction('slideoff', all_actions, tr).appendTo(parent);
        }
      });
      this.bind_actions(tr);
    },
    
    bind_actions: function(tr) 
    {
      var self = this;
      var key = tr.attr('_key');
      
      tr.on('slide', function(event, button) {
        button.toggle();
        tr.find('.slide').animate({width:'toggle'}, self.options.slideSpeed);
      });
      tr.on('slideoff', function() {
        tr.find('.slide').animate({width:'toggle'}, self.options.slideSpeed);
        tr.find('[action=slide]').toggle();
      });
      tr.on('expand', function(event, button, page) {
          button.hide();
          tr.find('[action=collapse]').show();
          var data = {page: page, key: key, load:""};
          var tmp = $('<div></div>').page({data:data});
          tmp.on('read_'+page, function(event, object) {
            var expanded = $('<tr class=expanded></tr>');
            $('<td></td>')
                    .attr('colspan', tr.children('td').length)
                    .append(object)
                    .prependTo(expanded);
            expanded.insertAfter(tr);
          });
      });
      tr.on('collapse', function(event, button) {
        button.hide();
        tr.find('[action=expand]').show();
        var next = tr.next();
        if (next.attr('class') === 'expanded') next.hide();
      });

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
