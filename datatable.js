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
    options: {
      name: 'Untitled',
      flags: [],
    },

    _create: function()
    {
      var me = this;
      var opts = me.options;
      if (opts.sort) opts.flags.push('sortable');
      var r = opts.render;
      r.expandFields(opts, "fields", opts.fields);
      var row = r.initField(opts.row, opts);
      me.row_classes = opts.row.class.join(' ');
      me.cell = r.initField(opts.cell, opts);
      me.cell.class = me.cell.class.join(' ')
      $.extend(opts.row_styles,opts.row.styles);
      opts.row_actions = opts.row_actions.concat(opts.row.actions);
      opts.render.expandFields(opts, "row_actions", opts.row_actions);
      me._init_params();
      if (me.hasFlag('show_titles') || me.hasFlag('show_header')) {
        me.head().addClass(opts.head.class.join(' ')).prependTo(me.element);
        if (me.hasFlag('show_header')) me.showHeader();
        if (me.hasFlag('show_titles')) me.showTitles();
      }
      me.showFooterActions();
      me.showData();
      me.element.on('refresh', function(e, args) {
        me.showData(args);
        e.stopImmediatePropagation();
      })
      .on('addRow', function(e, data) {
        me.addRow(data);
      })
      me.body().scroll($.proxy(me._scroll,me));
      me.bindRowActions()
    },

    _init_params: function()
    {
      this.params = { page_num: 1, offset: 0};
      var exclude = [ 'create', 'action', 'css', 'id', 'content', 'disabled',
          'html','name', 'page_id', 'position','script','slideSpeed', 'text', 'tag', 'type'];
      for (var key in this.options) {
        if (exclude.indexOf(key) >= 0) continue;
        var val = this.options[key];
        if (typeof val === 'string' || typeof val === "number")
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
      this.element.trigger('refresh', [args]);
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
      var me = this;
      var opts = me.options;
      me.head().find('.paging [action]').attr('disabled','');
      var data = $.extend(opts.request, args, {action: 'values'}, me.params);
      var selector = opts.selector;
      if (selector !== undefined) {
        $.extend(data, $(selector).values());
      }

      var el = me.element;
      me.loading = true;
      $.json('/', {data: mkn.plainValues(data)}, function(data) {
        if (!data) return;
        if (data._responses)
          el.triggerHandler('server_response', [data]);
        el.trigger('refreshing', [data]);
        var end = new Date().getTime();
        console.log("Load: ", end - start);
        me.populate(data);
        me.loading = false;
        el.triggerHandler('refreshed', [data]);
        delete data.data;
        $.extend(me.params, data);
        console.log("Populate: ", new Date().getTime() - end);
      });
    },

    populate: function(data)
    {
      if (data === undefined || data === null) {
        console.log('No table data for table:', this.params.field);
        return;
      }
      if (this.options.page_size !== undefined) this.showPaging(parseInt(data.total));

      for(var i in data.data) {
        this.addRow(data.data[i]);
      }
      this.adjustWidths(this.body().find('tr:first-child'))
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
      if (this.options.page_size !== undefined && !this.hasFlag('hide_paging')) this.createPaging(th);
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
      this.params.size = invoker.siblings('#page_size').val();
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
          self.params.size = $(this).val();
          self.refresh();
        }
      });

      var page = head.find('#page_num');
      this.element.on('goto_first', function(e, btn) {
        self.pageTo(btn, 1);
      })
      .on('goto_prev', function(e, btn) {
        self.page(btn, -1);
      })
      .on('goto_next', function(e, btn) {
        self.page(btn, 1);
      })
      .on('goto_last', function(e, btn) {
        var size = parseInt(head.find('#page_size').val());
        var total = parseInt(head.find('#page_total').html());
        self.pageTo(btn, Math.floor(total/size)+1);
      })
    },

    showPaging: function(total)
    {
      var head = this.head();
      head.find('#page_total').text(total);
      var page = this.params.page_num;
      var size = this.params.size;
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

    bindSort: function(th, field, index)
    {
      var self = this;
      th.click(function() {
        th.siblings().attr('sort','');
        var order = 'asc';
        if (self.params.sort == index)
          order = th.attr('sort')==='asc'?'desc':'asc';
        th.attr('sort', order);
        self.params.sort = index;
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
      var classes = this.options.title.class.join(' ');
      var j = 0;
      for (var i in fields) {
        var field = fields[i];
        var id = field.id;
        var visible = mkn.visible(field);
        if (id == 'key' && !visible  || id === 'attr' || id == 'style') continue;
        var th = $('<th></th>').addClass(classes).appendTo(tr);
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
          self.bindSort(th, id, i);
        th.toggle(visible);
      };
      this.spanColumns(head.find('.header th'));
    },

    spanColumns: function(td)
    {
      var tr = this.head().find('.titles');
      if (!tr.exists()) tr = this.body().children('tr').eq(0);
      td.attr('colspan', tr.children().length);
    },

    spanData: function(field, row, col)
    {
      if (field.span <= 1) return row[col];
      var span = row.slice(col, col+field.span);
      return span.join(' ');
    },

    showData: function(args)
    {
      var me = this;
      var body = me.body();
      var opts = me.options;
      var max_height  = parseInt(body.css('max-height'));
      if (opts.page_size == 0 || max_height > 0) {
        var row_height = $('<tr><td>Loading...</td></tr>').appendTo(body).outerHeight(true);
        if (row_height < 1) row_height = 20;
        me.params.size = Math.ceil(max_height/row_height)+1;
      }
      body.addClass(opts.body.class.join(' '));
      body.empty();
      me.load(args);
      me.spanColumns(me.head().find('.header>th'));
    },

    setRowStyles: function(row, styles) {
      var row_styles = this.options.row_styles;
      if (!row_styles) return;
      styles.split(',').forEach(function(style) {
        var classes = row_styles[style];
        if (!classes) return;
        row.addClass(classes.join(' '));
      });
    },

    addRow: function(row) {
      var me = this;
      var opts = me.options;
      var fields = opts.fields;
      var tr = $('<tr>');
      tr.addClass(me.row_classes);
      var key;
      var expandable = false;
      var col = 0;
      for (var i in fields) {
        var field = fields[i];
        var cell = me.spanData(field, row, col);
        if (cell === null) cell = '';
        col += field.span;
        if (key === undefined && (field.id === 'key' || field.key)) {
          key = cell;
          tr.attr('key', key);
        }

        var hide = field.hide || field.show === false;
        if (field.id === 'key' && hide) continue;
        cell = mkn.escapeHtml(cell);
        if (field.id === 'style') {
          me.setRowStyles(tr, cell);
          continue;
        }
        var td = $('<td>').appendTo(tr);
        if (hide) td.addClass('hidden');
        if (field.class) td.addClass(field.class.join(' '))

        if (field.id === 'actions') {
          var actions = cell.split(',');
          var expandable = actions.indexOf('expand') >= 0;
          me.createRowActions(tr, td, actions);
          if (!expandable) continue;
          td = tr.children().eq(0).addClass('expandable');
          if (!td.children().exists()) {
            var text = td.text();
            td.text('');
            $('<div>').text(text).appendTo(td).css('display','inline-block');
          }
          me.createAction('expand', undefined, tr).prependTo(td);
          me.createAction('collapse', undefined, tr).prependTo(td).hide();
          continue;
        }
        me.showCell(field, td, cell, key);
      }
      tr.appendTo(me.body());
    },

    showCell: function(field, td, value, key)
    {
      td.addClass(this.cell.class);
      if (field.html === undefined) {
        if (value !== undefined && value !== null) td.html(value);
        return;
      }
      field = mkn.copy(field);
      delete field.width;
      field.key = key;
      field.value = value;
      if (key !== undefined) {
        if (!$.isNumeric(key)) key = key.toLowerCase().replace(/ +/,'_');
        field.id = field.id + '_' + key;
      }
      var created = this.options.render.create(field);
      if (value !== undefined) created.value(value);
      td.append(created);
    },

    bindAction: function(obj, props, sink, path)
    {
      if (sink === undefined) sink = this.element;
      var self = this.element;
      obj.click(function() {
        var action = props.id;
        sink.trigger('action',[obj,action,props]);
        sink.trigger(action, [obj,props]);
        if (props.action === undefined) return;
        var key = sink.attr('key');
        if (key === undefined) key = self.options.key;
        var options = $.extend({},self.params, props, {id: action, action: props.action, key: key });
        var listener = self.closest('.page').eq(0);
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

    createRowActions: function(tr, td, row_actions)
    {
      if (!$.isArray(row_actions)) row_actions = row_actions.split(',');
      if (row_actions.indexOf('slide') >= 0)
        row_actions.push('slideoff');

      td.addClass('actions');

      var opts = this.options;
      var all_actions = opts.row_actions;
      var key = tr.attr('key');
      var normal_actions = [];
      var slide_actions = [];
      var actions = normal_actions;
      for (var i in all_actions) {
        var action = mkn.copy(all_actions[i]);
        var id = action.id;
        if (row_actions.indexOf(id) < 0) continue;
        action.key = key;
        action = mkn.merge(action, opts[id]);
        actions.push(action);
        if (id == 'slide')
          actions = slide_actions;
      }
      if (normal_actions.length)
        opts.render.createItems(td, {}, undefined, normal_actions);
      if (!slide_actions.length) return;
      var slider = $('<div class="slide">').toggle(false).appendTo(td);
      slider.data('actions', slide_actions);
    },

    slide: function(tr)
    {
      var height = this.getActionsHeight(tr);
      var slider = tr.find('.slide');
      if (slider.children().length == 0) {
        this.options.render.createItems(slider, {}, undefined, slider.data('actions'));
        slider.find('[action]').click(function() {
          slider.parent().trigger('action',[$(this),'', $(this).attr('action')]);
        });
        slider.data('width', slider.width());
        slider.width(0);
      }
      slider.find('[action]').height(height);
      slider.show().animate({width: slider.data('width')}, this.options.slideSpeed);
    },


    loadSubPages: function(tr, pages)
    {
      var expanded = $('<tr class=expanded></tr>');
      var td = $('<td></td>')
              .attr('colspan', tr.children('td').length)
              .prependTo(expanded);
      expanded.insertAfter(tr);

      var key = tr.attr('key');
      var tmp = $('<div></div>');
      var index = 0;
      var load = function() {
        var path = pages[index];
        mkn.showPage({path: path, key: key }, td).done(function() {
          if (++index < pages.length)
            load();
        })
      };
      load();
    },

    bindRowActions: function()
    {
      var self = this;
      this.element.on('slide', 'tr', function(e) {
        $(e.target).toggle();
        self.slide($(this));
      })
      .on('expand', 'tr', function(e) {
        var tr = $(this);
        tr.find('[action=expand]').hide();
        tr.find('[action=collapse]').show();
        if (tr.next().hasClass('expanded')) return;
        var expand = self.options['expand'];
        if (!expand.pages) return;
        self.loadSubPages(tr, expand.pages)
      })
      .on('collapse', 'tr', function(e) {
        var tr = $(this);
        tr.find('[action=collapse]').hide();
        tr.find('[action=expand]').show();
        var next = tr.next();
        if (next.hasClass('expanded')) next.remove();
      })
      .on('action', 'tr', function(evt, btn) {
        if (!btn.parent('.slide').exists()) return;
        self.slide($(this));
        $(this).find('[action=slide]').toggle();
        var slider = $(this).find('.slide');
        slider.animate({width: 0}, self.options.slideSpeed*2, function() { slider.hide()});
      })
      .on('delete', 'tr', function() {
        $(this).remove();
        return $(this);
      })
    },

    getActionsHeight: function(tr)
    {
      return (tr.innerHeight()*0.99).toString()+'px';
    },


    updateWidths: function(row, widths)
    {
      row.children().each(function(i) {
        var width = $(this).outerWidth(true);
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

    adjustWidths: function(row)
    {
      var widths = this.getWidths();
      row.children().each(function(i) {
        $(this).css('width', widths[i]+'px');
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
      var td;
      template.children().each(function(i) {
        var field = fields[i];
        if (!mkn.visible(field)) return;
        if (field.id === 'style' || field.id === 'actions') {
          var colspan = td.attr('colspan');
          if (!colspan) colspan = 1;
          td.attr('colspan', parseInt(colspan)+1);
          return;
        }
        td = $(cell);
        $('<input type=text></input>').css('width','10px').attr('field_id', field.id).appendTo(td);
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
        // self.params.page_num = 1;
        // self.params.page_size = self.options.page_size;
        fields.forEach(function(field, index) {
          delete self.params['f'+index];
          var obj = filter.findByAttribute('field_id', field.id);
          if (!obj.exists()) return;
          var val = obj.value();
          if (val == '') return;
          self.params['f'+index] = val;
        });
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
      this.options.render.expandFields(this.options, "footer_actions", this.options.footer_actions);
      var actions = this.options.footer_actions;
      if (!actions.length) return;
      var footer = $('<tfoot>').appendTo(this.element);
      var tr = $('<tr>').addClass('actions').appendTo(footer);
      var td = $('<td>').appendTo(tr);
      var key = this.options.key;
      actions.map(function(action) {
        action.key = key;
        return action;
      })
      this.options.render.createItems(td, this.options, undefined, actions);
      this.spanColumns(td);
    },

    _scroll: function(e) {
      var me = this;
      var opts = me.opts;
      var body = me.body();
      if(body.scrollHeight() - body.scrollTop() != body.height() || me.loading) return;
      if (me.params.total && me.params.offset + me.params.size > me.params.total) return;
      me.params.offset += me.params.size;
      me.load();
    },

  })
}) (jQuery);
