(function( $ ) {
  $.widget( "ui.table", {
    options: {
      pageSize: 30,
      sortField: null,
      sortOrder: 'asc',
      url: null,
      method: 'get',
      onRefresh: function(table) { return this; },
      onExpand: function(row) { return this; },
      onCollapse: function(row) { return this; }
    },
    
    _create: function() 
    {
      this.row = null;
      this.row_count = 0;
      this.data = { _offset: 0 } ;
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
        
    bind_paging: function()
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
    
    bind_titles: function()
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
    
    bind_actions: function()
    {
      var self = this;
      var table = this.element;
      table.find(".actions div[edit=off]").click(function() {        
        if (self.editor == null)
          self.editor = self._create_editor('edit', '<td></td>');
        var row = $(this).parent().parent();
        self.editor.children().each(function(i) {
          if ($(this).attr('edit') === undefined) return true;
          ++i;
          var selector = "td:nth-child("+i+")";
          var td = row.find(selector);
          var val = td.html();
          td.replaceWith($(this).clone());
          row.find(selector).children().val(val);
        });
      });
    },
    
    refresh: function()
    {
      this.data._size = this.options.pageSize;
      if (this.options.sortField != null) {
        this.data._sort = this.options.sortField;
        this.data._order = this.options.sortOrder;
      };

      var self = this;
      $.ajax({url: this.options.url, type: this.options.method, data: this.data, success: function(data) {
        self.result = $(data);
        self.show_header();
        self.update('tbody');
        self.bind_paging();
        self.bind_titles();
        self.bind_actions();

        self.options.onRefresh(self.element);
      }});
      return this;
    },
    
    _create_editor: function(type, col_text)
    {
      var table = this.element;
      var titles = table.find(".titles");
      var editor = $("<tr class="+type+"></tr>");
      titles.find("th").each(function() {
        var name = $(this).attr('name');
        var col = $(col_text);
        col.appendTo(editor);
        var attr = $(this).attr(type);
        if (attr == undefined) return true;
        col.attr(type, attr);
        var width = parseInt($(this).css('width')) * 0.8;
        if (attr.indexOf('lists/') == 0)
          col.html(jq_submit('/?a='+attr));
        else
          col.append("<input type='text' name='"+name+"' style='width:"+width+"' ></input>");
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
        var name = $(this).attr('name')  
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
      if (!this.options.onExpand($(button).parent().parent())=== false) return this;
      var self = this;
      $(button).attr("expand","expanded").click(function() { self.collapse(button); });
      return this;
    },
    
    collapse: function(button)
    {
      if (!this.options.onCollapse($(button).parent().parent()) === false) return this;
      var self = this;
      $(button).attr("expand","collapsed").click(function() { self.expand(button); });
      return this;
    }
  });
}) (jQuery);
