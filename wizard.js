(function( $ ) {
  $.widget( "ui.wizard", {
    _create: function() {
      this.stack = new Array();   
      var self = this;
      this.width =  this.options.width;
      var num_steps = this.options.steps.length;
      this.page_width = this.options.width - (num_steps*10);
      this._createPages();
      this._bindActions();
      this.stack = new Array();
      this._jumpTo(0);
    },
    
    _createPages: function()
    {
      var self = this;
      $.each(this.options.steps, function(i, info) {
        var name = info;
        var props = {};
        if ($.isPlainObject(name)) {
          for (var k in info) {
            name = k;
            props = info[k];
            break;
          }
        }
        props.name = name;
        self.options.steps[i] = props;
        self._createPage(i);
      })
    },
    
    _createPage: function(index) 
    {
      var props = this.options.steps[index];
      var page = $('<div class=wizard-page>').attr('name',props.name).hide().appendTo(this.element);
      var heading = $('<div class=wizard-heading>').hide().appendTo(page);
      $('<div class=wizard-number>').text(index+1).appendTo(heading);
      $('<span class=wizard-title>').appendTo(heading);
      var height = parseInt(this.element.css('height'));
      var width = parseInt(this.element.css('width'));
      var content = $('<div class=wizard-content>').appendTo(page);
      var nav = $('<div class=wizard-nav>').appendTo(page);
      content.height(height-parseInt(nav.css('height')));
      var offset = 24*index;
      page.css('left', offset+'px');
      heading.width(height);
      heading.css('left', -height/2+'px');
      heading.css('top', height/2-12+'px');
      heading.hide();
      if (index > 0) {
        var prev = this.element.find('.wizard-page').eq(index-1);
        var color = prev.find('.wizard-heading').css('background-color');
        color = darken(rgbToHex(color), 1.2);
        heading.css('background-color', color);
      }
    },
    
    _jumpTo: function(index)
    { 
      if (this.stack.length) {
        var top_index = this.stack[this.stack.length-1];
        if (index === top_index) return;
        if (top_index < index) {  // going forward
          this.stack.push(index);
          var page = this.element.find('.wizard-page').eq(top_index);
          page.find('#validate').click();
          return;
        }
        
        do { // going backwards
          top_index = this.stack.pop();
          this._hidePage(top_index, false);
        } while (top_index >   index);
      }
      
      this.stack.push(index);
      this._showPage(index);
    },
    
    _showPage: function(index)
    {
      var page = this.element.find('.wizard-page').eq(index);
      page.addClass('wizard-current').show();
      if (!page.hasClass('wizard-loaded')) 
        this._loadPage(page, index);
      page.find('.wizard-heading').hide();
      page.find('.wizard-content,.wizard-nav').show();
    },
        
        
    _hidePage: function(index, show_heading)
    {
      var page = this.element.find('.wizard-page').eq(index);
      page.removeClass('wizard-current');
      if (show_heading) page.find('.wizard-heading').show();
      page.find('.wizard-content,.wizard-nav').hide();
      page.removeClass('wizard-done');
    },
    
    _loadPage: function(page, index)
    {
      page.addClass('wizard-loading');
      var props = this.options.steps[index];
      var path = this.options.path;
      path = path.substr(0, path.lastIndexOf('/')+1) + props.name;
      var tmp = $('<div></div>');
      tmp.page({path: path, key: this.options.key});
      var self = this;
      var content = page.find('.wizard-content');
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object) {
        object.height(content.height());
        object.css('left', content.css('left'));
        object.addClass('wizard-content');
        content.replaceWith(object);
        self._createNavigation(page, props, index);
        page.addClass('wizard-loaded').removeClass('wizard-loading');
      });
    },
    
    _createNavigation: function(parent, props, index)
    {
      var num_steps = this.options.steps.length;
      if (props.prev === undefined) props.prev = index > 0 && num_steps > 1;
      if (props.next === undefined) props.next = index >= 0 && index < num_steps-1;
      if (!props.prev && !props.next) return;
      var nav = parent.find('.wizard-nav');
      if (props.prev) 
        $('<button class="wizard-prev action">').text(this.options.prev_name).appendTo(nav);
      if (props.next) 
        $('<button class="wizard-next action">').text(this.options.next_name).appendTo(nav);
      var self = this;
      nav.find('.wizard-prev').click(function() {
        nav.trigger('wizard-jump', [$(this),self.stack[self.stack.length-2]]);
      })
      nav.find('.wizard-next').click(function() {
        var dest = typeof props.next === 'string'? props.next: index+1;
        nav.trigger('wizard-jump', [$(this),dest]);
      })
    },
    
    _bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, object, index) {
        if (typeof index === 'string') {
          var page = self.element.find('.wizard-page[name="'+index+'"]');
          index = self.element.find('.wizard-page').index(page);
        }
        self._jumpTo(index);
      })
      
      this.element.on('processed', function(event, result) {
        if (!result) return;
        var index = self.stack.pop();
        self._hidePage(index, true);
        index = self.stack[self.stack.length-1];
        self._showPage(index);
      })
    }    
  })
}) (jQuery);
