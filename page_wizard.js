(function( $ ) {
  $.widget( "ui.pageWizard", {
    options: {
      lastUrl: '/'
    },

    _create: function() {
      this.stack = new Array();   
      var container = this.element;
      container.addClass('page-wizard');
      this.pages = container.children();
      this.index = 0;
      var self = this;
      this.pages.each(function() {
        var page = $(this);
        page.hide();
        var bar = $("<div class='page-wizard-bar ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix'></div>");
        var nav = $("<span class=page-wizard-nav></span>");
        var add_button = function(caption) {
          var name = caption.toLowerCase();
          var text = page.attr(name);
          if (text == undefined) return;
          if (text == '') text = caption;
          var button = $("<button wizard="+name+">"+text+"</button>");
          button.button();
          nav.append(button);
        }
        
        add_button('Back');
        add_button('Next');
        bar.append(nav);
        
        var display = page.attr('wizard');
        if (display === undefined || display == 'both' || display == 'bottom')
          page.append(bar.clone());
        if (display === undefined || display == 'both' || display == 'top') {
          var caption = page.attr('caption');
          if (caption === undefined) caption = '';
          bar.append("<span class='page-wizard-title ui-dialog-title'>"+caption+"</span>");
          page.prepend(bar);
        }
      });
      container.find('[wizard=next]').click(function() {
        var page = self.currentPage();
        var trigger = page.attr('id')+'_next';
        //if (wizard.trigger(trigger))
        console.log(trigger);
        self.goNext($(this).attr('steps'));
      });

      container.find('[wizard=back]').click(function() {
        self.goBack();
      });
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
      return this;
    },
    
    goNext: function(steps) {
      steps = steps==undefined? 1: parseInt(steps);
      this.stack.push(steps);
      console.log(this.index,steps);
      this.currentPage().hide();
      this.index += steps;
      this.currentPage().show();
      return this;
    },
    
    goBack: function() {
      var index = 0;
      $.each(this.stack, function(i, v) {
        index += v;
      });
         
      index -= this.stack.pop();
      this.pages.eq(this.index).hide();
      this.index = index;
      return this;
    },

    close: function() 
    {
      this.pages.eq(this.index).hide();
      window.location.href = this.options.lastUrl;
    }
  })
}) (jQuery);
