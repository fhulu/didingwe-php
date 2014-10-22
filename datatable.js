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
      this.params = { 
        path: this.options.path, 
        key: this.options.key, 
        sort: this.options.sort,
        sort_order: this.options.sort_order,
        page_num: this.options.page_num || 1,
        page_size: this.options.page_size
      };
      if (this.hasFlag('show_titles') || this.hasFlag('show_header')) {
        $('<thead></thead>').prependTo(this.element);
        if (this.hasFlag('show_header')) this.showHeader();
        if (this.hasFlag('show_titles')) this.showTitles();
      }
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
      var data = $.extend({action: 'data'}, self.params);
      data.path = data.path +'/load';
      $.json('/', {data: data}, function(data) {
        self.populate(data);
      });
    },
    
    populate: function(data)
    {
      if (data === undefined || data === null) {
        console.log('No table data for table:', this.params.field);
        return;
      }
      this.showData(data);
      this.showActions();
      if (this.options.page_size !== undefined) this.showPaging(parseInt(data.total));
      this.adjustActionsHeight();
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
    
    showTitles: function()
    {      
      var head = this.head();
      var tr = head.find('.titles').empty();
      if (!tr.exists()) tr = $('<tr class=titles></tr>').appendTo(head);
      var self = this;
      var fields = this.options.fields;
      $.each(fields, function(i, code) {
        if ($.isPlainObject(code) && code.type != undefined) return;
        if (code === 'attr') return;
        var field = self.getProperties(code, fields);
        if ($.isPlainObject(code) && field === 'type') return;
        code = field.code || code;
        var th = $('<th></th>').appendTo(tr);
        if (code === 'actions') return;
        if ($.isArray(field.name)) field.name = field.name[field.name.length-1];
        th.html(field.name || toTitleCase(code));
        if (code === self.params.sort) 
          th.attr('sort', self.params.sort_order);
        else
          th.attr('sort','');
        th.click(function() {
          console.log(th.attr('sort'), self.params.sort);
          th.siblings().attr('sort','');
          var order = 'asc';
          if (self.params.sort === code) 
            order = th.attr('sort')==='asc'?'desc':'asc';
          th.attr('sort', order);
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
      var expandable = this.options.expand.action !== undefined;
      var fields = this.options.fields;
      $.each(data.rows, function(i, row) {
        var tr = $('<tr></tr>').appendTo(body);
        var key = row[0];
        tr.attr('_key', key);
        if (i % 2 === 0) tr.addClass('alt');
        var expanded = !expandable;
        var k = 0;
        $.each(row, function(j, cell) {
          if (j===0 && !show_key) return;
          var code = fields[k++];
          if (code === 'attr') {
            $.each(cell.split(','), function(k,attr) {
              attr = attr.split(':');
              if (attr[0] === 'class') 
                tr.addClass(attr[1]);
              else
                tr.attr(attr[0],attr[1]);
            });
            return;
          }
          var field = self.getProperties(code, fields);
          if ($.isPlainObject(code) && field == 'type') {
            code = fields[k++];
            field = self.getProperties(code, fields);
          }
          var td = $('<td></td>').appendTo(tr);
          if (code === 'actions') {
            self.setRowActions(tr, td, cell);
            return;
          }
          
          self.showCell(field, td, cell, key);
          if (!expanded) {
            expanded = true;
            self.createAction('expand', undefined, tr).prependTo(td);
            self.createAction('collapse', undefined, tr).prependTo(td).hide();
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
    
    showCell: function(field, td, value, key)
    {      
      field = this.getProperties(field, this.options.fields);
      
      if (!$.valid(field.html)) {
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
    
    getProperties: function(field, fields)
    {
      var props = {};
      var key = field;
      if ($.isPlainObject(field)) {
        key = field.code;
        if (key !== undefined) {
          props = field;
        }
        else {
          for (key in field) {
            if (!field.hasOwnProperty(key)) continue;
            if (key === 'type') return 'type';
            props.code = key;
            break;
          };
        }
      }

      var type_field = this.options.types[key] || {};
      var option_field = this.options[key] || {}
      var list_item_type = {};
      var in_list = false;
      
      for( var i in fields) {
        var item = fields[i];
        if (typeof item === 'string' && key === item) {
          in_list = true;
          break;
        }
        if ($.isPlainObject(item) && item.type !== undefined) 
          list_item_type = this.options.types[item.type];
      }

      if (!in_list) 
        list_item_type = {};
      else
        props.code = key;
      return $.extend({}, type_field, option_field, props, list_item_type);
    },
    
    createAction: function(action, actions, sink)
    {
      if (sink === undefined) sink = this.element;
      if (actions === undefined) actions = this.options;
      var props = this.getProperties(action, actions);
      if ($.isEmptyObject(props) || ['slide','slideoff','collapse'].indexOf(action) < 0  && props.action === undefined) { 
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
        var options = $.extend({},self.params,{code: action, action: props.action, key: key });
        var listener = self.element.closest('.page').eq(0);
        options.path += '/';
        if (actions === self.options.row_actions) 
          options.path += 'row_actions/';
        else if (actions === self.options.footer_actions) 
          options.path += 'footer_actions/';
        options.path += action;
        listener.trigger('child_action', [div,options]);
      });
      return div;
    },
    
    setRowActions: function(tr, td, row_actions)
    {
      if (!$.isArray(row_actions)) row_actions = row_actions.split(',');
      var self = this;
      td.addClass('actions');
      var parent = td;
      var all_actions = this.options.row_actions;
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
      tr.on('expand', function(event, button, action) {
        if (!action || !action.pages) return;
        button.hide();
        tr.find('[action=collapse]').show();
        var expanded = $('<tr class=expanded></tr>');
        var td = $('<td></td>')
                .attr('colspan', tr.children('td').length)
                .prependTo(expanded);
        expanded.insertAfter(tr);
        $.each(action.pages, function(i, path) {
          var tmp = $('<div></div>');
          tmp.page({path: path, key: key});
          path = path.replace(/\//, '_');
          tmp.on('read_'+path, function(event, object) {
            td.append(object);
          });
        });
      });
      tr.on('collapse', function(event, button) {
        button.hide();
        tr.find('[action=expand]').show();
        var next = tr.next();
        if (next.attr('class') === 'expanded') next.remove();
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
        if (i === widths.length)
          widths.push(width);
        else if (width > widths[i])
          widths[i] = width;
      })
    },
    
    getWidths: function()
    {
      var widths = [];
      this.updateWidths(this.head().find('.titles'),widths);
      var self = this;
      this.body().children('tr').each(function() {
        if (!$(this).hasClass('actions'))
          self.updateWidths($(this), widths);
      });
      return widths;
    },
    
    adjustWidths: function(editor)
    {
      var widths = this.getWidths();
      var input;
      var fields = this.options.fields;
      editor.children().each(function(i) {
        var field = fields[i];
        if (field === 'actions') {
          input.css('width', widths[i-1] + widths[i] + 'px');
          return;
        }
        input = $(this).children('*').eq(0);
        input.css('width', widths[i]+'px');
      });
    },
    
    createEditor: function(template, fields, type, cell)
    {
      var editables = this.options[type];
      if (editables !== undefined) {
        editables = editables.fields;
        if (typeof editables === 'string')
          editables = editables.split(',');
      }
      var editor = $('<tr></tr>').addClass(type);
      var td
      template.children().each(function(i) {
        var field = fields[i];
        if (field === 'attr') return;
        
        //if (editables !== undefined && editables.indexOf(field.code) < 0) return;
        if (field === 'actions') {
          td.attr('colspan',2);
          return;
        }
        td = $(cell);
        $('<input type=text></input>').css('width','10px').appendTo(td);
        td.appendTo(editor);
      });
      
      editor.insertAfter(template); 
      this.adjustWidths(editor);
      return editor;
    },
    
    createFilter: function()
    {
      var filter = this.head().find('.filter');
      if (filter.exists()) return filter;
      
      var self = this;
      var titles = self.head().find('.titles');
      filter = self.createEditor(titles, self.options.fields, 'filter', '<th></th>').hide();
      filter.find('input').bind('keyup cut paste', function(e) {
        self.params.filtered = '';
        self.params.page_num = 1;
        self.params.page_size = self.options.page_size;
        filter.find('input').each(function() {
          self.params.filtered += $(this).val() + '|';
        });
        self.load();
      });
      return filter;
    },
    
    showFilter: function()
    {
      var filter = this.createFilter();
      filter.toggle();
      if (filter.is(':visible'))
        this.adjustWidths(filter);
    },
    
    showActions: function()
    {
      var actions = this.options.footer_actions;
      if (actions === undefined) return;
      var tr = $('<tr></tr>').addClass('actions').appendTo(this.body());
      var td = $('<td>').appendTo(tr);
      var self = this;
      $.each(actions, function(i, action) {
        if ($.isPlainObject(action) && action.type !== undefined) return;
        self.createAction(action, actions).appendTo(td);
      });
      this.spanColumns(td);
    }
    
  })
}) (jQuery);
