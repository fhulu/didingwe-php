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
      this._showPage(0);
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
      var page = $('<div class=wizard-page>').attr('name',props.name).appendTo(this.element);
      var heading = $('<div class=wizard-heading>').appendTo(page);
      $('<div class=wizard-number>').text(index).appendTo(heading);
      $('<span class=wizard-title>').appendTo(heading);
    },
    
    _showPage: function(index)
    { 
      if (this.stack.length) {
        var prev_page;
        var prev_index = this.stack[this.stack.length-1];
        if (prev_index < index) {
           var prev_page = this.element.find('.wizard-page').eq(prev_index);
           prev_page.removeClass('wizard-current');
           prev_page.addClass('wizard-done');
        }
        else do { 
          prev_page = this.element.find('.wizard-page').eq(prev_index);
          prev_page.removeClass('wizard-done');
          prev_index = this.stack.pop();
        } while (prev_index > index);
        prev_page.find('.wizard-content,.wizard-nav').hide();
        
      }

      var page = this.element.find('.wizard-page').eq(index);
      page.addClass('wizard-current');
      var content = page.find('.wizard-content');
      if (!content.exists()) 
        this._loadPage(page, index);
      else content.show();
      this.stack.push(index);
      page.find('.wizard-nav').show();
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
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object) {
        object.css('height','100%');
        object.addClass('wizard-content').appendTo(page);
        self._createNavigation(page, props, index);
        page.addClass('wizard-loaded').removeClass('wizard-loading');
      });
    },
    
    _createNavigation: function(parent, props, index)
    {
      var num_steps = this.options.steps.length;
      if (props.prev === undefined) props.prev = index > 0 && num_steps > 1;
      if (props.next === undefined) props.next = index >= 0 && index < num_steps-1;
      var nav = $('<div class=wizard-nav>').appendTo(parent);
      if (!props.prev && !props.next) return;
      if (props.prev) 
        $('<button class="wizard-prev action">').text(this.options.prev_name).appendTo(nav);
      if (props.next) 
        $('<button class="wizard-next action">').text(this.options.next_name).appendTo(nav);
      var self = this;
      nav.find('.wizard-prev').click(function() {
        nav.trigger('wizard-jump', [self.stack[self.stack.length-2]]);
      })
      nav.find('.wizard-next').click(function() {
        if (typeof props.next === 'string') {
          var page = self.element.find('.wizard-page[name="'+props.next+'"]');
          index = self.element.find('.wizard-page').index(page);
        }
        else index += 1;
        nav.trigger('wizard-jump', [index]);
      })
    },
    
    _bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, index) {
        self._showPage(index);
      })
    }    
  })
}) (jQuery);
