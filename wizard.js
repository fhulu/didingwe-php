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
      this.jumpTo(0);
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
      var bookmark = $('<div class=wizard-bookmark>').appendTo(page);
      this.bookmark_width = bookmark.outerHeight();
      $('<span class=wizard-bookmark-number>').appendTo(bookmark);
      $('<span class=wizard-bookmark-title>').appendTo(bookmark);
      var height = parseInt(this.element.height());
      var content = $('<div class=wizard-content>').appendTo(page);
      content.height(height);
      bookmark.width(height);
      var offset = (height - this.bookmark_width)/2;
      bookmark.css('left', (-offset)+'px');
      bookmark.css('top', offset+'px');
      bookmark.hide();
    },

    jumpTo: function(index)
    {
      if ($.isPlainObject(index)) index = index.index;
      if (typeof index === 'string') {
        var page = this.element.find('.wizard-page[name="'+index+'"]');
        if (!page.exists()) return;
        index = this.element.find('.wizard-page').index(page);
      }
      if (this.stack.length) {
        var top_index = this.stack[this.stack.length-1];
        if (index === top_index) return;
        if (top_index < index) {  // going forward
          this._hidePage(top_index, true);
        }
        else do { // goin backwards
            top_index = this.stack.pop();
            this._hidePage(top_index, false);
        } while (top_index >  index);
      }

      this._showPage(index);
      this.next_step = undefined;
    },

    _showPage: function(index)
    {
      var page = this.element.find('.wizard-page').eq(index);
      page.addClass('wizard-current').show();
      if (!page.hasClass('wizard-loaded'))
        this._loadPage(page, index);
      else
        page.find('.wizard-content').trigger('reload');

      var bookmark = page.find('.wizard-bookmark').hide();
      if (index > 0) {
        var prev = this.element.find('.wizard-page').eq(index-1);
        var color = prev.find('.wizard-bookmark').css('background-color');
        color = darken(rgbToHex(color), 1.15);
        bookmark.css('background-color', color);
      }
      page.find('.wizard-content,.wizard-nav').show();
      var offset = this.stack.length * this.bookmark_width - 6;
      page.css('left', offset+'px');
      page.width(this.element.width()-offset);
      this.stack.push(index);
    },


    _hidePage: function(index, show_heading)
    {
      var page = this.element.find('.wizard-page').eq(index);
      page.removeClass('wizard-current');
      if (show_heading) {
        page.find('.wizard-bookmark-number').text(this.stack.length+' ');
        page.find('.wizard-bookmark-title').text(page.find('.wizard-content').attr('title'));
        page.find('.wizard-bookmark').show();
      }
      else {
        page.find('.wizard-bookmark').hide();
      }
      page.find('.wizard-content,.wizard-nav').hide();
      page.removeClass('wizard-done');
    },

    _loadPage: function(page, index)
    {
      page.addClass('wizard-loading');
      var props = this.options.steps[index];
      var path = this.options.path;
      if (props.url !== undefined)
        path = props.url;
      else if (path.indexOf('/') === -1)
        path += '/' + props.name;
      else
        path = path.substr(0, path.lastIndexOf('/')+1) + props.name;
      var tmp = $('<div>');
      tmp.page({path: path, key: this.options.key});
      var content = page.find('.wizard-content');
      if (path[0] === '/') path = path.substr(1);
      var self = this;
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object) {
        object.height(content.height());
        object.css('left', content.css('left'));
        object.addClass('wizard-content');
        page.addClass('wizard-loaded').removeClass('wizard-loading');
        content.replaceWith(object);
        var prev = object.find('#prev');
        if (props.prev === false || index === 0)
          prev.hide();

        var next = object.find('#next');
        if (props.next === false && index === self.options.steps.length-1)
          next.hide();
        else next.bindFirst('click', function() {
          if (self.next_step === undefined)
            self.next_step = typeof props.next === 'string'? props.next: index+1;
        });
      });
    },


    _bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, object, index) {
        self.jumpTo(index);
      });

      this.element.on('wizard-next', function() {
        self.jumpTo(self.stack[self.stack.length-1]+1);
      });

      this.element.on('wizard-prev', function() {
        self.jumpTo(self.stack[self.stack.length-2]);
      });

      this.element.on('processed', function(event, result) {
        console.log('processed', self.stack.length, self.next_step, result);
        if (result || !self.stack.length || !self.next_step) return;
        self.jumpTo(self.next_step);
      });

      this.element.find('.wizard-bookmark').click(function() {
        var index = parseInt($(this).find('.wizard-bookmark-number').text())-1;
        self.jumpTo(self.stack[index]);
      });
    },

    nextStep: function(step)
    {
      this.next_step = step;
    }
  })
}) (jQuery);
