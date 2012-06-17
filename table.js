(function( $ ) {
  $.widget( "ui.table", {
    options: {
      pageSize: 30,
      minPageSize: 10,
      sortField: null,
      sortOrder: 'asc',
      url: null,
      onRefresh: function(table) {},
      onExpand: function(table) { return true; },
      onCollapse: function(table) { return true; }
    },
    
    _create: function() 
    {
      this.row = null;
      this.offset = 0;
      this.row_count = 0;
      this.refresh();
    },
  
    readPageSize: function()
    {
      size = parseInt(this.table.find(".paging [type='text']").val());
      return Math.min(size, this.row_count);
    },
    
    savePageSize: function()
    {
      this.options.pageSize = this.readPageSize();
      return this;
    },

    refresh: function()
    {
      var data = { size: this.options.pageSize, offset: this.offset };
      if (this.options.sortField != null) {
        data.sort = this.options.sortField;
        data.order = this.options.sortOrder;
      };
      var self = this;
      $.get(this.options.url, data, function(data) {
        self.element.html(data);
        var table = self.table = self.element.find('table');
        self.options.sortOrder = table.find('[order]').attr('order');
        self.row_count = parseInt(table.attr('rows'));
        table.find("[nav='next']").prop('disabled', self.offset + self.options.pageSize >= self.row_count);
        table.find("[nav='prev']").prop('disabled', self.offset <= 0);

        table.find("th[sort]").click(function() {
          var field = table.find('[order]');
          self.options.sortOrder = 'asc';
          if ($(this).is(field) && field.attr('order') == 'asc') self.options.sortOrder = 'desc';
          field.removeAttr('order');
          $(this).attr('order', self.options.sortOrder);
          self.options.sortField = $(this).attr('name');
          self.refresh();
        });
    
        table.find("[nav='next']").click(function() {
          var new_size = self.readPageSize();
          if (new_size == self.options.pageSize) {
            self.options.pageSize = new_size;
            self.offset += size;
            if (self.offset > self.row_count) self.offset = Math.min(0, self.row_count-self.options.pageSize);
          }
          else   
            self.options.pageSize = new_size;
          self.refresh();
        });
      
        table.find("[nav='prev']").click(function() {
          self.offset -= self.savePageSize().options.pageSize;
          if (self.offset < 0) self.offset = 0;
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
        
        table.find("td div[expand=collapsed]").click(function() { self.expand(this); });        
        table.find("td div[expand=expanded]").click(function() { self.collapse(this); });

        self.options.onRefresh(table);
      });
      return this;
    },
    
    expand: function(button)
    {
      if (!this.options.onExpand(this.table)) return this;
      var self = this;
      $(button).attr("expand","expanded").click(function() { self.collapse(button); });
      return this;
    },
    
    collapse: function(button)
    {
      if (!this.options.onCollapse(this.table)) return this;
      var self = this;
      $(button).attr("expand","collapsed").click(function() { self.expand(button); });
      return this;
    }
  });
}) (jQuery);
