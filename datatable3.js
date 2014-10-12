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
      name: 'Untitled',
      flags: []
    },
    
    _create: function()
    {
      if (this.hasFlag('show_titles') || this.hasFlag('show_header')) {
        $('<thead></thead>').prependTo(this.element);
        if (this.hasFlag('show_header')) this.showHeader();
      }
      this.params = { path: this.options.path, key: this.options.key};
      if (this.options.sort !== undefined) this.params.sort = this.options.sort;
      if (this.options.page_size !== undefined) this.params.page_size = this.options.page_size;
      this.params.page_num = 1;
      this.load();
    },
   
    head: function()
    {
      return this.element.children('thead').eq(0);
    },
    
    body: function()
    {
      return this.element.children('tbody').eq(0);
    },
    
    load: function()
    {
      var self = this;
      self.head().find('.paging [action]').attr('disabled','');
      $.json('/?a=datatable3/read', {data: self.params}, function(data) {
        if (data === undefined || data === null) {
          console.log('No table data for table:', self.params.field);
          return;
        }
        if (self.hasFlag('show_titles')) self.showTitles(data);
        self.showData(data);
        self.showActions();
        if (self.options.page_size !== undefined) self.showPaging(parseInt(data.total));
        if (self.hasFlag('filter')) self.createFilter(data.fields);
        self.adjustActionsHeight();
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
      var head = this.head();
      head.html('');
      var tr = $('<tr class=header></tr>').appendTo(head);
      var th = $('<th></th>').appendTo(tr);
      if (this.options.name !== undefined)
        $('<div class=heading></div>').html(this.options.name).appendTo(th);
      
      var self = this;
      if (this.hasFlag('filter')) {
        this.createAction('filter').appendTo(th);
        this.element.on('filter', function() { self.showFilter(); });
      }
      if (this.options.page_size !== undefined) this.createPaging(th);
    },
    
    createPaging: function(th)
    {
      var paging = $('<div class=paging></div>').appendTo(th);
      $('<div>Showing from </div>').appendTo(paging);
      $('<div id=page_from></div>').appendTo(paging);
      $('<div>to</div>').appendTo(paging);
      $('<div id=page_to></div>').appendTo(paging);
      $('<div>of</div>').appendTo(paging);
      $('<div id=page_total></div>').appendTo(paging);
      $('<div>at</div>').appendTo(paging);
      $('<input id=page_size type=text></input>').val(this.options.page_size).appendTo(paging)
      $('<div>entries per page</div>').appendTo(paging);

      this.createAction('goto_first').attr('disabled','').appendTo(paging);
      this.createAction('goto_prev').attr('disabled','').appendTo(paging);
      $('<input id=page_num type=text></input>').val(1).appendTo(paging);
      this.createAction('goto_next').attr('disabled','').appendTo(paging);
      this.createAction('goto_last').attr('disabled','').appendTo(paging);   
      this.bindPaging();
    },

    pageTo: function(invoker, number)
    {
      if (invoker.hasAttr('disabled')) return;
      this.params.page_num = number;
      this.params.page_size = invoker.siblings('#page_size').val();
      invoker.siblings('#page_num').val(number);
      this.load();
    },
    
    page: function(invoker, offset)
    {      
      this.pageTo(invoker, parseInt(invoker.siblings('#page_num').val())+offset);
    },
    
    bindPaging: function()
    {
      var self = this;
      var head = self.head();
      head.find(".paging [type='text']").bind('keyup input cut paste', function(e) {
        if (e.keyCode === 13) {
          self.params.page_size = $(this).val();
          self.load();
        }
      }); 
      
      var page = head.find('#page_num');
      this.element.on('goto_first', function(e, invoker) {
        self.pageTo(invoker, 1);
      })
      .on('goto_prev', function(e, invoker) {
        self.page(invoker, -1);
      })
      .on('goto_next', function(e, invoker) {
        self.page(invoker, 1);
      })
      .on('goto_last', function(e, invoker) {
        var size = parseInt(head.find('#page_size').val());
        var total = parseInt(head.find('#total').val());
        self.pageTo(invoker, total/size);
      })
    },
    
    showPaging: function(total)
    {
      var head = this.head();
      head.find('#page_total').text(total);
      var page = this.params.page_num;
      var size = this.params.page_size;
      var prev = head.find('[action=goto_first],[action=goto_prev]');
      var next = head.find('[action=goto_last],[action=goto_next]');
      if (page <= 1) {
        page = 1;
        prev.attr('disabled','');
        head.find('#page_num').val(1);
      }
      
      if (size >= total) head.find('#page_num').val(1);

      head.find('#page_from').text((page-1)*size+1);
      head.find('#page_to').text(Math.min(page*size,total));
      if (page >= total/size) 
        next.attr('disabled','');
      else
        next.removeAttr('disabled');
        
      if (page > 1) prev.removeAttr('disabled');
    },
    
    showTitles: function(data)
    {      
      var head = this.head();
      var tr = head.find('.titles').empty();
      if (!tr.exists()) tr = $('<tr class=titles></tr>').appendTo(head);
      var self = this;
      var show_key = self.hasFlag('show_key');
      $.each(data.fields, function(i, field) {
        if (i === 0 && !show_key) return;
        var code = field.code;
        if (field.code === 'attr') return;
        var th = $('<th></th>').appendTo(tr);
        if (field.code === 'actions') return;
        th.html(field.name || toTitleCase(code));
        if (code === self.params.sort) 
          th.attr('sort', self.params.sort_order);
        else
          th.attr('sort','');
        th.click(function() {
          th.siblings().attr('sort','');
          var order = 'asc';
          if (self.params.sort === code)
            order = th.attr('sort')==='asc'?'desc':'asc';
          self.params.sort = code;
          self.params.sort_order = order;
          self.load();
        });        
      });
      this.spanColumns(head.find('.header th'));
    },
    
    spanColumns: function(td)
    {
      var tr = this.head().find('.titles');
      if (!tr.exists()) tr = this.body().children('tr').eq(0);
      td.attr('colspan', tr.children().length);
    },
    
    showData: function(data)
    {
      var self = this;
      var body = self.body().empty();
      var show_key = this.hasFlag('show_key');
      var expandable = this.options.row.expand !== undefined;
      var show_edits = this.hasFlag('show_edits');
      var all_actions = this.options.row.actions;
      $.each(data.rows, function(i, row) {
        var tr = $('<tr></tr>').appendTo(body);
        var key = row[0];
        tr.attr('_key', key);
        if (i % 2 === 0) tr.addClass('alt');
        var expanded = !expandable;
        $.each(row, function(j, cell) {
          if (j===0 && !show_key) return;
          var field = data.fields[j];
          if (field.code === 'attr') {
            $.each(cell.split(','), function(k,attr) {
              attr = attr.split(':');
              if (attr[0] === 'class') 
                tr.addClass(attr[1]);
              else
                tr.attr(attr[0],attr[1]);
            });
            return;
          }
          var td = $('<td></td>').appendTo(tr);
          if (field.code === 'actions') {
            self.setRowActions(tr, td, all_actions, cell);
            return;
          }
          
          self.showCell(show_edits, field, td, cell, key);
          if (!expanded) {
            expanded = true;
            f.createAction('expand', all_actions, tr).prependTo(td);
            self.createAction('collapse', all_actions, tr).prependTo(td).hide();
          }
        });
      });
      var widths = this.options.widths;
      if (widths !== undefined) {
        body.children('tr').eq(0).children().each(function(i) {
          var width = widths[i];
          if (width === undefined || parseInt(width) === 0) return;
          $(this).css('width', ''+width+'px');
        });
      }
      this.spanColumns(this.head().find('.header>th'));      
    },
    
    showCell: function(editable, field, td, value, key)
    {
      if (!editable || !$.valid(field.html)) {
        td.html(value);
        return;
      }
      var html = field.html.replace('$code', key+'_'+field.code);
      var entity = $(html)
              .css('width','100%')
              .css('height','100%')
              .value(value).appendTo(td);
      if (field.create !== undefined && field.create !== null) {
        var create_opts = $.extend({key: key}, field);
        entity.customCreate(create_opts);
      }
    },
    
    createAction: function(action, actions, sink)
    {
      if (sink === undefined) sink = this.element;
      if (actions === undefined) actions = this.options;
      var props;
      if ($.isPlainObject(action)) {
        var key;
        for (key in action) {};
        props = $.extend({}, actions[key], action);
        action = key;
      }
      else if (typeof action === 'string') {
        props = $.extend({}, this.options[action], actions[action]);
      }
      if (props === undefined) { 
        console.log("undefined props for", action, "defined", actions);
        return $('');
      }
      var div = $('<span>');
      if (props.name === undefined) props.name = toTitleCase(action);
      div.html(props.name);
      div.attr('title', props.desc);
      div.attr('action', action);
      var self = this;
      div.click(function() {
        sink.trigger('action',[div,action,props.action]);
        sink.trigger(action, [div,props.action]);
        if (props.action === undefined) return;
        var key = sink.attr('_key');
        if (key === undefined) key = self.options.key;
        var options = $.extend({},self.params,{code: action, action: props.action, key: key, selector: props.selector});
        var listener = self.element.closest('.page').eq(0);
        listener.trigger('child_action', [div,options]);
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
      this.bindRowActions(tr);
    },
    
    slide: function(parent)
    {
       parent.find('.slide').animate({width:'toggle'}, this.options.slideSpeed);
    },
    
    bindRowActions: function(tr) 
    {
      var self = this;
      var key = tr.attr('_key');
      
      tr.on('slide', function(e,btn) {
        btn.toggle();
        self.slide(tr);
      });
      tr.on('expand', function(event, button, page) {
          button.hide();
          tr.find('[action=collapse]').show();
          var tmp = $('<div></div>').page({page: page, key: key, load:""});
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

      tr.on('action', function(evt, btn) {
        if (!btn.parent('.slide').exists()) return;
        self.slide(tr);
        tr.find('[action=slide]').toggle();
      });

      tr.on('processed_delete', function(result) {
        tr.remove();
//        alert('deleted successfullyy ' + JSON.toString(result));
      });
    },
    
    adjustActionsHeight: function()
    {
      this.element.find("tbody>tr").each(function() {
        var row = $(this);
        var height = (row.height()*0.99).toString()+'px';
        row.find('.slide,[action]').height(height).css('line-height', height);
      });
    },
    
    updateWidths: function(row, widths)
    {
      row.children().each(function(i) {
        var width = $(this).width();
        if (widths[i] === undefined || width > widths[i])
          widths[i] = width;
      })
    },
    
    getWidths: function()
    {
      var widths = [];
      this.updateWidths($('.titles'),widths);
      var self = this;
      this.body().children('tr').each(function() {
        if (!$(this).hasClass('actions'))
          self.updateWidths($(this), widths);
      });
      return widths;
    },
    
    createEditor: function(template, fields, type, cell)
    {
      var editables = this.options[type];
      if (editables !== undefined) {
        editables = editables.fields;
        if (typeof editables === 'string')
          editables = editables.split(',');
      }
      var widths = this.getWidths();
      var editor = $('<tr></tr>').addClass(type);
      var td;
      var field_index = this.hasFlag('show_key')?0:1;
      template.children().each(function(i) {
        var field = fields[field_index++];
        if (field.code === 'attr') return;
        
        if (editables !== undefined && editables.indexOf(field.code) < 0) return;
        if (field.code === 'actions') {
          td.attr('colspan',2);
          td.children('input').eq(0).css('width', widths[i-1]+widths[i]);
          return;
        }
        td = $(cell);
        $('<input type=text></input>').width(widths[i]*0.98).appendTo(td);
        td.appendTo(editor);
      });
      
      editor.insertAfter(template); 

      return editor;
    },
    
    createFilter: function(fields)
    {
      if (this.head().find('.filter').exists()) return;
      
      var self = this;
      var titles = self.head().find('.titles');
      var filter = self.createEditor(titles, fields, 'filter', '<th></th>').hide();
      filter.find('input').bind('keyup cut paste', function(e) {
        self.params.filtered = '';
        self.params.page_num = 1;
        self.params.page_size = self.options.page_size;
        filter.find('input').each(function() {
          self.params.filtered += $(this).val() + '|';
        });
        self.load();
      });
    },
    
    showFilter: function()
    {
      this.head().find('.filter').toggle();
    },
    
    showActions: function()
    {
      var actions = this.options.actions;
      if (actions === undefined) return;
      var tr = $('<tr></tr>').addClass('actions').appendTo(this.body());
      var td = $('<td>').appendTo(tr);
      var self = this;
      $.each(actions, function(i, action) {
        self.createAction(action).appendTo(td);
      });
      this.spanColumns(td);
    }
    
  })
}) (jQuery);