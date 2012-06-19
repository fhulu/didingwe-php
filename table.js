(function( $ ) {
  $.widget( "ui.table", {
    options: {
      pageSize: 30,
      minPageSize: 10,
      sortField: null,
      sortOrder: 'asc',
      url: null,
      method: 'get',
      onRefresh: function(table) {},
      onExpand: function(row) { return true; },
      onCollapse: function(row) { return true; }
    },
    
    _create: function() 
    {
      this.row = null;
      this.row_count = 0;
      this.data = { _offset: 0 } ;
      this.result = null;
      this.refresh();
    },
  
    readPageSize: function()
    {
      size = parseInt(this.element.find(".paging [type='text']").val());
      return Math.min(size, this.row_count);
    },
    
    savePageSize: function()
    {
      this.options.pageSize = this.readPageSize();
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
    
    show_heading: function()
    {
      var table = this.element;
      var thead = table.find('thead');
      if (!thead.exists()) 
        thead = $('<thead></thead>').appendTo(table);;

      this.update('.heading', thead);
      
      var self = this;
      table.find(".filtering").click(function() {
        if (!table.find('.filter').exists()) 
          self.show_filter(table);
        else
          self.hide_filter(table);
      });
      
      this.update('.titles', thead);
    },
    
    toggle_paging: function()
    {
      table.find("[nav='next']").prop('disabled', this.data._offset + this.options.pageSize >= this.row_count);
      table.find("[nav='prev']").prop('disabled', this.data._offset <= 0);    
    },
    
    bind_paging: function()
    {
      var table = this.element;
      var paging = table.find('.paging');
      if (!paging.exists()) return;

      this.row_count = parseInt(paging.attr('rows'));
    
      var self = this;
      table.find("[nav='next']").click(function() {
        var new_size = self.readPageSize();
        if (new_size == self.options.pageSize) {
          self.options.pageSize = new_size;
          self.data._offset += size;
          if (self.data_.offset > self.row_count) self.offset = Math.min(0, self.row_count-self.options.pageSize);
        }
        else   
          self.options.pageSize = new_size;
        self.toggle_paging();
        self.refresh();
      });
    
      table.find("[nav='prev']").click(function() {
        self.offset -= self.savePageSize().options.pageSize;
        if (self.offset < 0) self.offset = 0;
        self.toggle_paging();
        self.refresh();
      });
    
      table.find(".paging [type='text']").bind('keyup input cut paste', function(e) {
        var me = $(this);
        if (e.keyCode == 13)
          self.savePageSize().refresh();
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
        self.show_heading();
        self.update('tbody');
        self.bind_paging();
        self.bind_titles();

        self.options.onRefresh(self.element);
      }});
      return this;
    },
    
    
    show_filter: function(table)
    {
      var titles = table.find(".titles");
      var filter = $("<tr class=filter></tr>").insertAfter(titles);
      titles.find("th").each(function() {
        var name = $(this).attr('name');
        if ($(this).attr('sort') === undefined) 
          filter.append("<th></th>");
        else 
          filter.append("<th><input type='text' style='width:100%;' name='"+name+"'></input></th>");
      });
      
      var self = this;
      filter.find("input").bind('keyup input cut paste', function() { self.filter(filter); });
    },
    
    hide_filter: function(table)
    {
      table.find(".filter").remove();
      this.data = { _offset: 0 };
    },
    
    filter: function(tr)
    {
      var self = this;
      tr.find("input").each(function() {
        var val = $(this).val();
        var name = $(this).attr('name')  
        if (val.trim() == '')
          delete self.data[name];
        else
          self.data[name] = val;
      });
      self.refresh();
    },
    
    expand: function(button)
    {
      if (!this.options.onExpand($(button).parent().parent())) return this;
      var self = this;
      $(button).attr("expand","expanded").click(function() { self.collapse(button); });
      return this;
    },
    
    collapse: function(button)
    {
      if (!this.options.onCollapse($(button).parent().parent())) return this;
      var self = this;
      $(button).attr("expand","collapsed").click(function() { self.expand(button); });
      return this;
    }
  });
}) (jQuery);
