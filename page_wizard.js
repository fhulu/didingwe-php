(function( $ ) {
  $.widget( "ui.pageWizard", {
    options: {
      url: '/',
      startPage: 0,
      autoStart: true,
      title: ''
    },

    _create: function() 
    {
      this.stack = new Array();   
      var container = this.element;
      container.addClass('page-wizard');
      this.pages = container.children();
      this.index = 0;
      this.pages.each(function() {
        var page = $(this);
        page.hide();
        var bar = $("<div class='page-wizard-bar ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix'></div>");
        //var bar = $("<div class='page-wizard-bar'></div>");
        var add_button = function(caption) {
          var name = caption.toLowerCase();
          var text = page.attr(name);
          if (text == undefined) return;
          if (text == '') text = caption;
          var button = $("<button wizard="+name+" class='page-wizard-button page-wizard-"+name+"'>"+text+"</button>");
          //button.button();
          bar.append(button);
        }        
        add_button('Back');
        add_button('Next');
        
        var display = page.attr('wizard');
        if (display === undefined || display == 'both' || display == 'bottom')
          page.append(bar.clone());
        if (display === undefined || display == 'both' || display == 'top') {
          var caption = page.attr('caption');
          if (caption === undefined) caption = '';
          bar.append("<span class='page-wizard-title ui-dialog-title'>"+caption+"</span>");
          page.prepend(bar);
        }

        var id = page.attr('id');
        page.find('[wizard]').each(function() {
          var cls = id + '_' + $(this).attr('wizard');
          $(this).addClass(cls);
        });
      });
      var self = this;
      setTimeout(function() { self.bindActions(); }, 1000);      
      if (self.options.autoStart) self.start(self.options.startPage);
    },
    
    bindActions: function()
    {
      var self = this;
      var container = this.element;
      this.element.find('[wizard=next]').click(function() {
        var page = self.currentPage();
        var trigger = page.attr('id')+'_next';
        if (container.trigger(trigger))
          self.goNext($(this).attr('steps'));
      });

      container.find('[wizard=back]').click(function() { self.goBack(); });
    },
    
    at: function(index)
    {
      return this.pages.eq(index);
    },
    
    currentPage: function()
    {
      return this.at(this.index);
    },
    
    start: function(index) {
      this.element.show();
      if (index == undefined) index = 0;
      this.stack = new Array();
      this.stack.push(index);
      this.index = index;
      this.pages.eq(index).show();
      var page = this.currentPage();
      document.title = this.options.title + ' - ' + page.attr('caption');
      return this;
    },
    
    goNext: function(steps) 
    {
      steps = steps==undefined? 1: parseInt(steps);
      this.stack.push(steps);
      this.currentPage().hide();
      this.index += steps;
      var page = this.currentPage();
      page.show();
      document.title = this.options.title + ' - ' + page.attr('caption');
      return this;
    },
    
    goBack: function() 
    {
      var index = 0;
      $.each(this.stack, function(i, v) {
        index += v;
      });
      
      this.currentPage().hide();
      this.index -= this.stack.pop();
      this.currentPage().show();
      return this;
    },
    
    close: function()
    {
      window.location.href = this.options.url;
    }
  })
}) (jQuery);
