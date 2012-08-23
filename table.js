//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
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
  $.widget( "ui.table", {
    options: {
      pageSize: 0,
      sortField: null,
      sortOrder: 'asc',
      url: null,
      method: 'get'
    },
    
    _create: function() 
    {
      this.row = null;
      this.row_count = 0;
      this.data = { _offset: 0, _size: 0 } ;
      this.result = null;
      this.filter = null;
      this.editor = null;
      this.refresh();
    },
  
    _readPageSize: function()
    {
      size = parseInt(this.element.find(".paging [type='text']").val());
      return Math.min(size, this.row_count);
    },
    
    _savePageSize: function()
    {
      this.options.pageSize = this._readPageSize();
      return this;
    },

    refresh_header: function(table)
    {
      var body_pos = html.indexOf("<tbody");
      var thead = html.substring(html.indexOf('<thead'));
    },
    
    update: function(selector, parent)
    {
      var table = this.element;
      if (parent == undefined) parent = table;
      var old = parent.find(selector);
      var newer = this.result.find(selector);
      if (old.exists())
        old.replaceWith(newer);
      else 
        parent.append(newer);
    },
    
    show_header: function()
    {
      var table = this.element;
      var thead = table.find('thead');
      if (!thead.exists()) 
        thead = $('<thead></thead>').appendTo(table);;

      this.update('.header', thead);
      
      if (this.filter != null && this.filter.is(':visible')) 
         table.find('.filtering').attr('on','');

      var self = this;
      table.find(".filtering").click(function() {
        if (table.find('.filter').is(':visible')) 
          self.hide_filter(table);
        else
          self.show_filter(table);
      });

      this.update('.titles', thead);
    },
        
    _bind_paging: function()
    {
      var table = this.element;
      var paging = table.find('.paging');
      if (!paging.exists()) return;

      this.row_count = parseInt(paging.attr('rows'));
      table.find("[nav='next']").prop('disabled', this.data._offset + this.options.pageSize >= this.row_count);
      table.find("[nav='prev']").prop('disabled', this.data._offset <= 0);    
    
      var self = this;
      table.find("[nav='next']").click(function() {
        var new_size = self._readPageSize();
        if (new_size == self.options.pageSize) {
          self.options.pageSize = new_size;
          self.data._offset += size;
          if (self.data._offset > self.row_count) self.offset = Math.min(0, self.row_count-self.options.pageSize);
        }
        else   
          self.options.pageSize = new_size;
        self.refresh();
      });
    
      table.find("[nav='prev']").click(function() {
        self.data._offset -= self._savePageSize().options.pageSize;
        if (self._offset < 0) self.data._offset = 0;
        self.refresh();
      });
    
      table.find(".paging [type='text']").bind('keyup input cut paste', function(e) {
        var me = $(this);
        if (e.keyCode == 13)
          self._savePageSize().refresh();
        else table.find(".paging [type='text']").each(function() {
          if (!$(this).is(me)) $(this).val(me.val());
        });
      });      
    },
    
    _bind_titles: function()
    {
      var table = this.element;
      if (!table.find('.titles')) return;
      
      var self = this;
      table.find("th[sort]").click(function() {
        var field = table.find('[order]');
        self.options.sortOrder = 'asc';
        if ($(this).is(field) && field.attr('order') == 'asc') self.options.sortOrder = 'desc';
        field.removeAttr('order');
        $(this).attr('order', self.options.sortOrder);
        self.options.sortField = $(this).attr('name');
        self.refresh();
      });
  
      
      table.find("td div[expand=collapsed]").click(function() { self.expand(this); });        
      table.find("td div[expand=expanded]").click(function() { self.collapse(this); });
    },
    
    
    showEditor: function(row)
    {      
      if (this.editor == null)
        this.editor = this._create_editor('edit', '<td></td>');

      this.editor.children().each(function(i) {
        if (!$(this).hasAttr('edit')) return true;
        var td = row.children().eq(i);
        var val = td.html();
        td.replaceWith($(this).clone());
        td = row.children().eq(i);
        td.children().val(val);
        td.find("select option").each(function() {
          if ($(this).text() == val) $(this).attr("selected","selected");
        });
      });
    },
    
    _edit_row: function(button)
    {
      var row = button.parent().parent();
      if (!this.element.trigger('edit', [row])) return this;

      this.showEditor(row);
      
      var self = this;
      button.attr('title', 'save').unbind('click');
      button.click(function() { self._save_row(button); });
    },
    
    _save_row: function(button)
    {
      var row = button.parent().parent();
      if (!this.element.trigger('save', [row])) return this;
      
      var self = this;
      button.attr('title', 'edit').unbind('click');
      button.click(function() { self._edit_row(button); }); 
      
      var body = self.element.find("tbody");
      var data = { };
      var key = body.attr('key');
      if (key != undefined) data[key] = row.attr(key);
      
      row.children('[edit]').each(function() {
        var name = $(this).attr('edit');
        var type = $(this).attr('type');
        var text, val;
        if (type.indexOf('list:') == 0) {  // select dropdown
          var option  = $(this).find(":selected");
          text = option.text();
          val = option.val();
        }
        else {
          var input = $(this).children().eq(0);
          text = val = input.val();
        }
        data[name] = val;
        $(this).html(text);
      });

      var is_new = row.hasAttr('new');
      // remove delete button if not specified for the form
      if (is_new && !body.hasAttr('save')) 
          row.find(".actions div[delete]").remove();
 
      var url = is_new? body.attr('adder'): body.attr('save');
      if (url == undefined || url == '') return;
      
      $.send(unescape(url), {data: data, method: self.options.method}, function(result) {
        if (!row.hasAttr('new') || !body.hasAttr('key')) return true;
        row.attr(key, result);
        row.find("[key]").html(result);
      });
    },
    
    deleteRow: function(row)
    {
      var body = this.element.find("tbody");
      var key = body.attr('key');
      var url = body.attr('deleter');
      if (key != undefined && url != undefined && url != '') {        
        var data = { };
        data[key] = row.attr(key);
         $.send(unescape(url), {data: data, method: self.options.method});
      }
      row.remove();
    },
    
    addRow: function(button)
    {
      if (this.editor == null)
        this.editor = this._create_editor('edit', '<td></td>');
      
      var body = this.element.find("tbody");
      var key = body.attr('key');
      var row = $("<tr new></tr>");
      this.editor.children().each(function() {
        var name = $(this).attr('edit');
        if (key != undefined && name == key)
          row.append("<td key></td");
        else
          row.append("<td></td");
      });
    
      var actions = row.children().last();
      actions.addClass('actions');
      actions.html("<div edit=on></div><div delete></div>");
      
      if (button !== undefined) 
        row.insertBefore(button.parent().parent());
      else
        body.append(row);
      this._bind_actions(row);
      this.showEditor(row);
    },
    
    _bind_actions: function(adder)
    {
      var self = this;
      var parent = this.element;
      if  (adder == undefined) 
        self.element.find(".adder").click(function() { self.addRow($(this)); });
      else 
        parent = adder;
 
      var body = this.element.find("tbody");
      var key = body.attr('key');
      
      parent.find(".actions div").filter('not([title=edit],[title=save],[title=delete])').click(function() {
        var row = $(this).parent().parent();
        var action = $(this).attr('title');
        action = action.replace(' ','_');
        var data = {};
        data[key] = row.attr(key);
        self.element.trigger('action', [action, row, data]);
        self.element.trigger(action, [row, data]);
      }); 
      parent.find(".actions div[title=edit]").click(function() { self._edit_row($(this)); });
      parent.find(".actions div[title=save]").click(function() { self._save_row($(this)); });
      parent.find(".actions div[title=delete]").click(function() { self.deleteRow($(this).parent().parent()); });
    },

    
    _adjust_actions_width: function()
    {
      var width = 0;
      this.element.find("tbody tr:first-child td.action").children().each(function() {
        width += parseInt($(this).css('width')) 
          + parseInt($(this).css('padding-left'))
          + parseInt($(this).css('padding-right'));
      })   
      if (width > 0)        
        this.element.find(".titles th:last-child").css('width', width);
    },

    post: function(url, data, callback)
    { 
      return $.get(url, data, function(result) {
        var regex = /<script[^>]+>(.+)<\/script>/;
        var script = regex.exec(result);
        if (script != null && script.length > 2) {
          eval(script[1]);
          return;
        }
        callback(result);
      });
    },
    
    refresh: function()
    {
      if (this.options.pageSize > 0) 
        this.data._size = this.options.pageSize;
  
      if (this.options.sortField != null) {
        this.data._sort = this.options.sortField;
        this.data._order = this.options.sortOrder;
      };

      var self = this;
      $.send(this.options.url, {data: this.data, method: self.options.method}, function(data) {
        self.result = $(data);
        self.show_header();
        self.update('tbody'); 
        self._bind_paging(); 
        self._bind_titles(); 
        self._bind_actions(); 
        self._adjust_actions_width(); 
        self.element.trigger('refresh'); 
      });
      return this;
    },
    
    _create_sub_table: function(col, attr)
    {
    
    },
    
    _create_list: function(col, attr)
    {
      var url = '/?a='+attr.substr(attr.indexOf(':')+1);
      var select = $("<select></select>");
      select.html(jq_submit(url));
      col.append(select);
    },
   
    _create_editor: function(type, col_text)
    {
      var table = this.element;
      var titles = table.find(".titles");
      var editor = $("<tr class="+type+"></tr>");
      var self = this;
      titles.find("th").each(function() {
        var name = $(this).attr('name');
        var col = $(col_text);
        col.appendTo(editor);        
        var attr = $(this).attr(type);
        if (attr == undefined) return true;
        col.attr('type', attr);
        col.attr(type, name);
        var width = parseInt($(this).css('width')) * 0.8;
        if (attr.indexOf('list:') == 0) 
          self._create_list(col, attr);
        else if (attr == '')
          col.append("<input type='text' style='width:"+width+";'></input>");
      });
      
      return editor;
    },
    
    _reset_editor: function(editor)
    {
      editor.find('*').val('');
      editor.show();
      return this;
    },
    
    show_filter: function()
    {
      if (this.filter != null) {
        this._reset_editor(this.filter);
        this.filter.show();
      }
      else {
        this.filter = this._create_editor('filter', '<th></th>');
        this.filter.insertAfter(this.element.find('.titles'));
      }
      var self = this;
      this.filter.find("input").bind('keyup input cut paste', function() {
        var val = $(this).val();
        var name = $(this).parent().attr('filter')  
        if (val.trim() == '')
          delete self.data[name];
        else
          self.data[name] = val;
        self.refresh();
      }); 
      
      this.element.find(".filtering").attr('on','');
    },
    
    hide_filter: function(table)
    {
      this.filter.hide();
      this.element.find(".filtering").removeAttr('on');
      this.data = { _offset: 0 };
      this.refresh();
    },    
    
    expand: function(button)
    {
      var row = $(button).parent().parent();
      $(button).unbind('click').attr("expand","expanded");
      var self = this; 
      if (self.element.trigger('expand', [row]) == false) {
        $(button).attr("expand","collapsed").click(function() { self.expand(button); });
        return this;
      }
      $(button).click( function() { self.collapse(button); });
      return this;
    },
    
    collapse: function(button)
    {
      var row = $(button).parent().parent();
      $(button).unbind('click').attr("expand","collapsed");
      var self = this; 
      if (self.element.trigger('collapse', [row]) == false) {
        $(button).attr("expand","expanded").click(function() { self.collapse(button); });
        return this;
      }
      $(button).click(function() { self.expand(button); });
      return this;
    }
  });
}) (jQuery);
