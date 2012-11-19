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
      method: 'post',
      data: {}
    },
    
    _create: function() 
    {
      this.row = null;
      this.row_count = 0;
      this.data = {_offset: 0, _size: 0} ;
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

    ajax: function(url, data, callback)
    {
      $.send(url, {data: $.extend(this.options.data,data), method: this.options.method}, callback);
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
  
    },
    
    
    showEditor: function(row)
    {      
      if (this.editor == null)
        this.editor = this._create_editor('edit', '<td></td>');

      this.editor.find('td').each(function(i) {
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
    
    editRow: function(row)
    {
      var body = this.element.find("tbody");
      var url = body.attr('edit');
      if (url != '') return this;

      this.showEditor(row);
      
      var button = row.find(".actions div[action=edit]");
      button.attr('action', 'save').attr('title','save');
      return this;
    },
    
    saveRow: function(row, data)
    {
      var self = this;
      var button = row.find(".actions div[action=save]");
      button.attr('action', 'edit').attr('title','edit');; 
      
      var body = self.element.find("tbody");
      var key = body.attr('key');
      
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
          row.find(".actions div[action=delete]").remove();
 
      var url = is_new? body.attr('add'): body.attr('save');
      if (url == undefined || url == '') return;
      if (is_new) data[key] = '';
      this.ajax(url, data, function(result) {
        if (!row.hasAttr('new') || key===undefined) return true;
        row.removeAttr('new');
        row.attr(key, result);
        row.find("[key]").html(result);
      });
    },
    
   deleteRow: function(row, data)
    {
      if (!confirm('Are you sure?')) return this;
      var body = this.element.find("tbody");
      var url = body.attr('delete');
      if (url != undefined && url != '') this.ajax(url, data); 
      row.remove();
      return this;
    },
    
    addRow: function()
    {
      if (this.editor == null)
        this.editor = this._create_editor('edit', '<td></td>');
      
      var body = this.element.find("tbody");
      var key = body.attr('key');
      var row = $("<tr new></tr>");
      this.editor.find('td').each(function() {
        var name = $(this).attr('edit');
        if (key != undefined && name == key)
          row.append("<td key></td");
        else
          row.append("<td></td");
      });
    
      var actions = row.children().last();
      actions.addClass('actions');
      actions.html("<div action=save></div><div action=delete></div>");
      
      var button = body.find(".actions div[action=add]");
      if (button !== undefined) 
        row.insertBefore(button.parent().parent().parent());
      else
        body.append(row);
      this.showEditor(row);
      this._bind_actions(row);
    },
    
    exportData: function()
    {
      var button = this.element.find('.actions [action=export]');
      window.location.href = unescape(button.attr('url'));
    },
   
    checkAll: function()
    {
      var check = this.element.find('[action=checkall]').is(':checked');
      this.element.find('[action=checkrow]').prop('checked', check);
      var url = this.get_body('checkall');
      if (url != '') {
        var key = this.get_body('key');
        var data = { check: check, keys:''};
        this.element.find('[action=checkrow]').each(function(){
          var row = $(this).parents('tr').eq(0);
          data.keys += ',' + row.attr(key);
        });
        data.keys = data.keys.substr(1);
        this.ajax(url, data);
      }
    },
 
    get_body: function(attr)
    {
      var body = this.element.find("tbody");
      return body.attr(attr);
    },
    
    get_key: function(row)
    {
      var data = {};
      var key = this.get_body('key');
      if (key !== undefined) 
        data[key] = row.attr(key);
      return data;
    },

    checkRow: function(row, data)
    {
      var url = this.get_body('checkrow');
      if (url === undefined || url == '') return; 
      data['status'] = row.find('[action=checkrow]').is(':checked')?1:0;
      this.ajax(url, data);
    },
    
    _bind_actions: function(row)
    {
      if (row == undefined) 
        row = this.element.find('tr');
      var body = this.element.find("tbody");
      var key = body.attr('key');

      var self = this;
      row.find("[action]")
      .click(function() {
        var row = $(this).parents('tr').eq(0);
        var data = {};
        if (key !== undefined) 
          data[key] = row.attr(key);
        var action = $(this).attr('action');
        action = action.replace(' ','_');
        row.trigger('action', [action, data]);
        row.trigger(action, [data]);      
      }) 
      .each(function() {
        var action = $(this).attr('action');
        action = action.replace(' ','_');
        var row = $(this).parents('tr').eq(0);
        row.on(action, function() {
          if (action == 'add' || action == 'save' || action=='delete' || action == 'checkrow' || action == 'checkall' || action == 'expand' || action == 'collapse') return true;
          var url = body.attr(action);
          if (url == '' || url===undefined) return true;
          if (key != undefined) {
            var sep = url.indexOf('?')>=0?'&':'?';
            url += sep + key+'='+row.attr(key);
          }
          window.location.href = url;
          return true;
        });
      });
      
      row
        .on('edit', function(e, data) {self.editRow($(this),data);})
        .on('save', function(e, data) {self.saveRow($(this),data);})
        .on('delete', function(e, data) {self.deleteRow($(this),data);})
        .on("expand", function(e, data) {self.expand($(this),data);})        
        .on("collapse", function(e, data) {self.collapse($(this),data);}) 
        .on("checkrow", function(e, data) {self.checkRow($(this),data);})
        .on('add', function() {self.addRow();})
        .on('export', function() {self.exportData();})
        .on('checkall', function() {self.checkAll();});
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
      this.ajax(this.options.url, this.data, function(data) {
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

   _create_list: function(col, attr)
    {
      var list = attr.substr(attr.indexOf(':')+1);
      var select = $("<select></select>");
      var values = list.split(',');
      if (values.length == 1) {
        var url = '/?a='+attr.substr(attr.indexOf(':')+1);
        select.html(jq_submit(url));
      }
      else {
        for (var i=0; i<values.length; ++i) {
          select.append("<option>"+values[i]+"</option>")
        }
      } 
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
        var prev_method = self.options.method;
        self.options.method = 'get';
        self.refresh();
        self.options.method = 'post';
      }); 
      
      this.element.find(".filtering").attr('on','');
    },
    
    hide_filter: function(table)
    {
      this.filter.hide();
      this.element.find(".filtering").removeAttr('on');
      this.data = {_offset: 0};
      this.refresh();
    },    
    
    expand: function(row)
    {
      var button = row.find("[action=expand]");
      if (button !== undefined)
        $(button).attr("action","collapse");
      var key = this.get_body('key');
      var key_value = row.attr(key);
      row.after("<tr class=expanded><td colspan="+row.children().length+"></td></tr>");
      row = row.next();
      var url = this.get_body('expand');
      if (url !== undefined) {
        if (url.indexOf('#') == -1) { 
          var data = {};
          data[key] = key_value;
          row.find('td').loadHtml(url, {method: this.options.method, data: data} );
          return row;
        }
        var detail = $(url).clone();
        row.find('td').html(detail.html());
        row.find('input[id]').each(function() {
          $(this).attr('id', $(this).attr('id')+'_'+key_value);
        });
        row.find('input[name]').each(function() {
          $(this).attr('name', $(this).attr('name')+'_'+key_value);
        });
        return row;
      }
      return null;
    },
    
    collapse: function(row)
    {
      var button = row.find("[action=collapse]");
      if (button !== undefined)
        $(button).attr("action","expand");
      var next = row.next();
      if (next.attr('class') == 'expanded') next.hide();
      return this;
    }
  });
}) (jQuery);
