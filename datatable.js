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
      flags: [],
    },

    _create: function()
    {
      if (this.options.sort) this.options.flags.push('sortable');
      this.expandFields(this.options.fields);
      this.expandFields(this.options.row_actions);
      this._init_params();
      if (this.hasFlag('show_titles') || this.hasFlag('show_header')) {
        $('<thead></thead>').prependTo(this.element);
        if (this.hasFlag('show_header')) this.showHeader();
        if (this.hasFlag('show_titles')) this.showTitles();
      }
      this.showFooterActions();
      this.load();
      var self = this;
      this.element.on('refresh', function(e, invoker, args) {
        self.load(args);
      })
    },

    _init_params: function()
    {
      this.params = { page_num: 1};
      var exclude = [ 'create', 'action', 'css', 'id', 'content', 'disabled',
          'html','name', 'page_id', 'position','script','slideSpeed'];
      for (var key in this.options) {
        if (exclude.indexOf(key) >= 0) continue;
        var val = this.options[key];
        if ($.isPlainObject(val) || $.isArray(val)) continue;
        this.params[key] = val;
      }
    },

    _promote_fields: function(fields)
    {
      $.each(fields, function(i, val) {
        if (!$.isPlainObject(val))
          fields[i] = $.toObject(val);
      });
    },

    refresh: function(args)
    {
      this.element.trigger('refresh', [this.element, args]);
    },

    head: function()
    {
      return this.element.children('thead').eq(0);
    },

    body: function()
    {
      return this.element.children('tbody').eq(0);
    },

    load: function(args)
    {
      var start = new Date().getTime();
      var self = this;
      self.head().find('.paging [action]').attr('disabled','');
      var data = $.extend(this.options.request, args, {action: 'data'}, self.params);
      var selector = this.options.selector;
      if (selector !== undefined) {
        $.extend(data, $(selector).values());
      }
      data.path = data.path +'/load';
      $.json('/', {data: data}, function(data) {
        if (data._responses)
          self.element.trigger('server_response', data);
        self.element.trigger('refreshed', [data]);
        var end = new Date().getTime();
        console.log("Load: ", end - start);
        self.populate(data);
        delete data.rows;
        $.extend(self.params, data);
        console.log("Populate: ", new Date().getTime() - end);
      });
    },

    populate: function(data)
    {
      if (data === undefined || data === null) {
        console.log('No table data for table:', this.params.field);
        return;
      }
      this.showData(data);
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
      this.refresh();
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
          self.refresh();
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
        var total = parseInt(head.find('#page_total').html());
        self.pageTo(invoker, Math.floor(total/size)+1);
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

    bindSort: function(th, field)
    {
      var self = this;
      th.click(function() {
        th.siblings().attr('sort','');
        var order = 'asc';
        if (self.params.sort === field)
          order = th.attr('sort')==='asc'?'desc':'asc';
        th.attr('sort', order);
        self.params.sort = field;
        self.params.sort_order = order;
        self.refresh();
      });
    },

    showTitles: function()
    {

      var head = this.head();
      var tr = head.find('.titles').empty();
      if (!tr.exists()) tr = $('<tr class=titles></tr>').appendTo(head);
      var self = this;
      var fields = this.options.fields;
      var j = 0;
      for (var i in fields) {
        var field = fields[i];
        if (field.hide || field.id === 'attr') continue;
        var id = field.id;
        var th = $('<th></th>').appendTo(tr);
        if (id === 'actions') continue;
        if ($.isArray(field.name)) field.name = field.name[field.name.length-1];
        th.html(field.name || toTitleCase(id));
        if (self.hasFlag('sortable')) {
          if (id === self.params.sort)
            th.attr('sort', self.params.sort_order);
          else
            th.attr('sort','');
        }
        if (field.width !== undefined) {
          th.css('width', field.width);
        }
        ++j;
        if (self.hasFlag('sortable'))
          self.bindSort(th, id);
      };
      this.spanColumns(head.find('.header th'));
    },

    spanColumns: function(td)
    {
      var tr = this.head().find('.titles');
      if (!tr.exists()) tr = this.body().children('tr').eq(0);
      td.attr('colspan', tr.children().length);
    },

    appendRow: function(data)
    {
      var body = this.body();
      var fields =  [].concat(this.options.fields);
      for (var i in fields) {
        var val = $.firstValue(fields[i]);
        val.value = data[i];
      }
      var options = {
        fields: { type: 'table_row', columns: fields},
        types: this.options.types,
        path: ""
      };
      console.log("reset", fields);
      body.page(options);
//      var tmp = $('<tr>$</tr>');//.appendTo(body);
    },

    showData: function(data)
    {
      var self = this;
      var body = self.body().empty();
      var expandable = this.options.expand.action !== undefined;
      var fields = this.options.fields;
      var tr;
      for(var i in data.rows) {
        var row = data.rows[i];
        if (tr) {
          self.bindRowActions(tr);
          tr.appendTo(body);
        }
        tr = $('<tr></tr>');//.appendTo(body);
        var key;
        if (i % 2 === 0) tr.addClass('alt');
        var expanded = !expandable;
        var k = 0;
        for (var j in row) {
          var cell = row[j];
          var field = fields[k++];
          if (field.id === 'attr') {
            cell = cell.split(',');
            for (var l in cell) {
              var attr = cell[l].split(':');
              if (attr[0] === 'class')
                tr.addClass(attr[1]);
              else
                tr.attr(attr[0],attr[1]);
            };
            continue;
          }
          if (field.type && !field.id) field = fields[k++];
          if (field.id === 'key' || field.key) {
            key = cell;
            tr.attr('_key', key);
          }
          if (field.hide) continue;

          var td = $('<td></td>').appendTo(tr);
          if (field.id === 'actions') {
            self.setRowActions(tr, td, cell);
            continue;
          }

          self.showCell(field, td, cell, key);
          if (expanded) continue;
          expanded = true;
          self.createAction('expand', undefined, tr).prependTo(td);
          self.createAction('collapse', undefined, tr).prependTo(td).hide();
        }
      }
        if (tr) {
          self.bindRowActions(tr);
          tr.appendTo(body);
        }
      this.spanColumns(this.head().find('.header>th'));
    },

    showCell: function(field, td, value, key)
    {
      var entity;
      if (!$.valid(field.html)) {
        td.html(value);
        entity = td;
      }
      else {
        var html = field.html.replace('$id', key+'_'+field.id);
        entity = $(html)
              .css('width','100%')
              .css('height','100%')
              .value(value)
              .appendTo(td);
      }

      var style = field.style;
      if (style) {
        for (var attr in style) entity.css(attr, style[attr]);
      }
      if (field.action) {
        this.bindAction(entity, field, td.parents('tr').eq(0));
      }
      if (field.create !== undefined && field.create !== null) {
        var create_opts = $.extend({key: key}, field);
        entity.customCreate(create_opts);
      }
    },

    expandFields: function(fields)
    {
      if (!fields) return;
      var default_type;
      var action;
      var types = this.options.types;
      for (var i in fields) {
        var item = fields[i];
        var id;
        if ($.isPlainObject(item)) {
          if (item.type !== undefined) {
            default_type = types[item.type];
            item.hide = true;
            continue;
          }
          if (item.action !== undefined) {
            action = item.action;
            continue;
          }
          if (item.id === undefined) {
            for (var key in item) {
              if (!item.hasOwnProperty(key)) continue;
              id = key;
              break;
            };
            item = item[id];
            if (!item.type && default_type)
              item = merge(default_type, item);
            item = merge(types[id], item);
          }
        }
        else if (typeof item === 'string') {
          id = item;
          if (types[item] !== undefined)
            item = merge(types[item], default_type);
          else if (default_type !== undefined)
            item = $.copy(default_type);
          else
            item = {};
        }

        item.id = id;
        if (action && !item.action) item.action = action;
        fields[i] = item;
      }
    },

    bindAction: function(obj, props, sink, path)
    {
      if (sink === undefined) sink = this.element;
      var self = this;
      obj.click(function() {
        var action = props.id;
        sink.trigger('action',[obj,action,props.action]);
        sink.trigger(action, [obj,props.action]);
        if (props.action === undefined) return;
        var key = sink.attr('_key');
        if (key === undefined) key = self.options.key;
        var options = $.extend({},self.params, props, {id: action, action: props.action, key: key });
        var listener = self.element.closest('.page').eq(0);
        options.path += '/';
        if (path !== undefined) options.path += path + '/';
        options.path += action;
        listener.trigger('child_action', [obj,options]);
      });
    },

    findField: function(name, fields)
    {
      for (var i in fields) {
        if (name === fields[i].id) return fields[i];
      }
    },

    createAction: function(action, actions, sink, path)
    {
      var props;
      if (actions) {
        props = this.findField(action, actions);
        if (!props) props = this.options[action];
      }
      else if ($.isPlainObject(action)) {
        props = action;
        action = props.id;
      }
      else
        props = this.options[action];
      if (!props || $.isEmptyObject(props))
        return $('');
      var div = $('<span>');
      if (props.name === undefined) props.name = toTitleCase(action);
      div.html(props.name);
      div.attr('title', props.desc);
      div.attr('action', action);
      props.id = action;
      this.bindAction(div, props, sink, path);
      return div;
    },

    setRowActions: function(tr, td, row_actions)
    {
      if (!$.isArray(row_actions)) row_actions = row_actions.split(',');
      var self = this;
      td.addClass('actions');
      var parent = td;
      var all_actions = this.options.row_actions;
      for (var i in row_actions) {
        var action = row_actions[i];
        if (action === 'expand') continue;
        self.createAction(action, all_actions, tr, 'row_actions').appendTo(parent);
        if (action === 'slide') {
          parent = $('<span class="slide">').toggle(false).appendTo(parent);
          self.createAction('slideoff', all_actions, tr).appendTo(parent);
        }
      };
      //this.bindRowActions(tr);
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
        console.log(action)
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
            console.log("read", object)
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
      editor.find('input:last-child').css('width','99%');
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
      var td;
      template.children().each(function(i) {
        var field = fields[i];
        if (field.id === 'attr' || field.id === 'actions') {
          var colspan = td.attr('colspan');
          if (!colspan) colspan = 1;
          td.attr('colspan', parseInt(colspan)+1);
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
      var fields = self.options.fields;
      filter = self.createEditor(titles, self.options.fields, 'filter', '<th></th>').hide();
      var cols = filter.children();
      filter.find('input').bind('keyup cut paste', function(e) {
        self.params.filtered = '';
        self.params.page_num = 1;
        self.params.page_size = self.options.page_size;
        var j = 0;
        for (var i in fields) {
          var field = fields[i];
          if (field.id === 'actions' || field.id === 'attr') continue;
          var val = field.hide? '': cols.eq(j++).find('input').val();
          self.params.filtered += val + '|';
        }
        self.refresh();
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

    showFooterActions: function()
    {
      var actions = this.options.footer_actions;
      if (actions === undefined) return;
      var footer = $('<tfoot></tfoot>').appendTo(this.element);
      var tr = $('<tr></tr>').addClass('actions').appendTo(footer);
      var td = $('<td>').appendTo(tr);
      this.expandFields(actions);
      for (var i in actions) {
        var action = actions[i];
        if (!action.hide)
          this.createAction(action, undefined, tr, 'footer_actions').appendTo(td);
      }
      this.spanColumns(td);
    }

  })
}) (jQuery);
