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
  $.widget( "ui.table", {
    options: {
      pageSize: 0,
      sortField: null,
      sortOrder: 'asc',
      url: null, 
      method: 'post',
      refreshesEvery: 0,
      data: {}
    },
    
    _create: function() 
    {
      this.row = null;
      this.row_count = 0;
      this.data = {_offset: 0, _size: 0, _checked: '' } ;
      this.result = null;
      this.filter = null;
      this.editor = null;
      this.timer = null;
      this.refresh();
      var self = this;
      if (this.options.refreshesEvery > 0)
        setInterval(function() { self.refresh(); }, this.options.refreshesEvery);
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
      $.send(url, {data: $.extend({}, this.options.data, this.data, data), method: this.options.method}, callback);
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
      if (document.title == '' ) 
        document.title = table.find('.heading').text();
      
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
        var val = td.text();
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
        else if (type.indexOf('multi:')==0) {
          var select = $(this).find('select');
          val = select.val().join();
          text = select.text().join();
        } else {
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
      this.ajax(url, data, function() {
        self.refresh();
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
    
    
    searchRow: function(url)
    {
      var self = this;
      var table = this.element;
      var body = table.find("tbody");
      var key = body.attr('key');      
      var row = $("<tr new></tr>");
      var button = body.find(".actions div[action=add]");
      if (button !== undefined) 
        row.insertBefore(button.parent().parent().parent());
      else
        body.append(row);
      var colspan = table.find('tr.titles').children().length;
      console.log('colspan=',colspan);
      var td = $("<td colspan="+colspan+"></td>");
      var input = $("<input class='search' value=''></input>");
      td.append(input);
      td.append($('<i> <-- type the first few letters of the item you want to add here </i>'));
      row.append(td);

      input
        .addClass('fullwidth') 
        .autocomplete({
          start: 0,
          source: url,
          prevTerm: '',
          select: function( event, ui ) {
            console.log(ui.item);
            var titles = table.find("thead .titles th");
            row.children().remove('*');
            var data = {};
            titles.each(function() {
              var name = $(this).attr('name'); 
              var text = ui.item[name];
              if (text === undefined) text = '';
              row.append($("<td>"+text+"</td>"));
              data[name] = text;
            });
          //  data['parent_id'] = table.parent().parent().attr('id');
            data['id'] = ui.item[key];
            $.send(body.attr('searchadd'), {data: data}, function(){
              self.refresh();
            });
            
          },
          search: function () {
            var start = input.autocomplete('option', 'start');
            var changed = 0;
            var val = $(this).val();
            if (val != input.autocomplete('option', 'prevTerm')) {
              changed = 1;
              input.autocomplete('option', 'prevTerm', val);
            }
            input.autocomplete('option','source', url+'&start='+start+'&changed='+changed);
            
          }
        })
        .data( "autocomplete" )._renderItem = function( ul, item ) {
          var text='';
          if (item.id != undefined) {
            $.each(item, function(key, val) {
              if (val != undefined && key != 'term' && key != 'id')
                text += ' ' + val;
            });
            //if (text.length > 50) text = text.substr(0, 50) + '...';
            text = "<a>" + text.replace(new RegExp("("+preg_quote(item.term)+")", "gi"),"<b>$1</b>")+"</a>";
          }
          else {
            if (item.type == 'nav') {
              if (item.next != undefined) 
                text += "<button style='float:right' nav=next>>></button>";
                
              if (item.prev != undefined)
                text += "<button style='float:left' nav=prev><<</button>";
            }
            else text = item.label;
          }
          var li = $( "<li></li>" )
            .data( "item.autocomplete", item )
            .append( text )
            .appendTo( ul );
          $("li>button[nav]").click(function(){
            input
              .autocomplete('option','start', item[$(this).attr('nav')])
              .autocomplete('search');
          });
          return li;
        };
    },
    
    addRow: function(row)
    {
      var body = this.element.children("tbody").eq(0);
      var search = body.attr('search');
      if (search !== undefined) {
        this.searchRow(search);
        return;
      }
      if (this.editor == null)
        this.editor = this._create_editor('edit', '<td></td>');
      
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
      actions.html("<div action=save></div><div action=delete></div>")   
     var footer = body.children(".footer");
     if (footer !== undefined) 
       row.insertBefore(footer);
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
          data.keys += '|' + row.attr(key);
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
        var target = $(this).attr('target');
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
          if (target == 'ajax') {
            self.data._checked = '';
            self.element.find('[action=checkrow]:checked').each(function(){
              var row = $(this).parent().parent();
              if (row.attr(key) !== undefined)
                self.data._checked += '|' + row.attr(key);
            })
            self.data._checked = self.data._checked.substr(1);
            self.ajax(url);
            self.refresh();
          }
          else
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
        .on('add', function() {self.addRow($(this));})
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
      var list = decodeURIComponent(attr.substr(attr.indexOf(':')+1));
      var select = $("<select></select>");
      if (list[0] == '?') 
        select.loadHtml('/?a='+list.substr(1), { async: false});
      else if (list[0]=='#') 
        select.jsonLoadOptions('/?a='+list.substr(1), {async: false});
      else {
        var values = list.split(',');
        for (var i=0; i<values.length; ++i) {
          select.append("<option>"+values[i]+"</option>")
        }
      } 
      col.append(select);
    },
   
    _create_multi: function(col, attr)
    {
      this._create_list(col, attr);
      var select = col.children('select');
      select.attr('multiple','multiple');
      select.multiselect();
    },

    _create_editor: function(type, col_text)
    {
      var table = this.element;
      var thead = table.children("thead");
      var titles = thead.children(".titles").eq(0);
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
        else if (attr.indexOf('multi:') == 0)
          self._create_multi(col, attr);
        else if (attr == '')
          col.append("<input type='text' size="+width/10+" style='width:"+width+";'></input>");
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
      row.after("<tr class=expanded></tr>");
      row = row.next();
      var td = $("<td colspan="+row.children().length+"></td>");
      row.append(td);
      row.attr(key, key_value);
      var url = this.get_body('expand');
      console.log(url);
      if (url === undefined) return row;
      var expand_type = this.get_body('expand_type');
      var data = {};
      data[key] = key_value;
      if (expand_type == 'table') {
        var table = $("<table class='expanded'></table>");
        table.table({url: url, data: data, method: 'get'} );
        td.append(table);
        return row;
      }
  
        if (url.indexOf('#') != 0) { 
          row.find('td').loadHtml(url, {method: this.options.method, data: data} );
          return row;
        }
        var id_sep = url.split(',');
        var id = id_sep[0];
        var detail = $(id).clone();
        row.find('td').html(detail.html());
        if (id_sep.length > 1) {
          var sep = id_sep[1];
          row.find('[id]').each(function() {
            $(this).attr('id', $(this).attr('id')+sep+key_value);
          });
          row.find('[name]').each(function() {
            $(this).attr('name', $(this).attr('name')+sep+key_value);
          });
        }
        return row;
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
