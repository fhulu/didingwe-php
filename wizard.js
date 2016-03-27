(function( $ ) {
  $.widget( "ui.wizard", {
    _create: function() {
      this.stack = new Array();
      var self = this;
      this.width = this.element.width();
      this.render = new mkn.render({
        parent: this.element,
        types: this.options.types,
        id: this.element.id,
        key: this.options.key
      });
      this.render.expandFields(this.options, "steps", this.options.steps);

      var num_steps = this.options.steps.length;
      this._createPages();
      this._bindActions();
      this.stack = new Array();
      this.jumpTo(0);
    },

    _createPages: function()
    {
      var self = this;
      $.each(this.options.steps, function(i, info) {
        self._createPage(i);
      })
    },

    _createPage: function(index)
    {
      var props = this.options.steps[index];
      var page = $('<div class=wizard-page>')
          .attr('step',props.id)
          .hide()
          .css('width','100%')
          .appendTo(this.element);

      if (this.options.bookmarks)
        this._createBookmark(page);
      else
        $('<div class=wizard-content>').appendTo(page);
    },

    _createBookmark: function(page)
    {
      var bookmark = $('<div class="wizard-bookmark wizard-bookmark-active">').appendTo(page);
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
      console.log("jumping to ", index);
      if ($.isPlainObject(index)) index = index.index;
      if (typeof index === 'string') {
        var page = this.element.find('.wizard-page[step="'+index+'"]');
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
      var props = this.options.steps[index];
      if (!page.hasClass('wizard-loaded') || props.clear)
        this._loadPage(page, index);
      else
        page.find('.wizard-content').triggerHandler('reload');

      if (this.options.bookmarks)
        this._showBookmark(page, index);
      page.addClass('wizard-current').show();
      this.stack.push(index);
    },

    _showBookmark: function(page, index)
    {
      var bookmark = page.find('.wizard-bookmark').hide();
      if (index > 0) {
        var prev = this.element.find('.wizard-page').eq(index-1);
        var color = prev.find('.wizard-bookmark-active').css('background-color');
        color = darken(rgbToHex(color), 1.15);
        bookmark.css('background-color', color);
      }
      page.find('.wizard-content,.wizard-nav').show();
      var offset = this.stack.length * this.bookmark_width - 6;
      page.css('left', offset+'px');
      page.width(this.width-offset);
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
        path += '/' + props.id;
      else
        path = path.substr(0, path.lastIndexOf('/')+1) + props.id;
      var tmp = $('<div>');
      tmp.page({path: path, key: this.options.key});
      var content = page.find('.wizard-content');
      if (path[0] === '/') path = path.substr(1);
      var self = this;
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object) {
        object.height(content.height());
        object.css('left', content.css('left'));
        object.width(page.width());
        object.addClass('wizard-content');
        page.addClass('wizard-loaded').removeClass('wizard-loading');
        content.replaceWith(object);
        var prev = object.find('#prev');
        if (props.prev === false || index === 0) {
          prev.hide();
          self.first_step = index;
          self.element.find('.wizard-bookmark-active').each(function(i) {
            if (i < index)
              $(this).removeClass('wizard-bookmark-active').addClass('wizard-bookmark-inactive');
          });
        }

        var next = object.find('#next');
        var is_last = index === self.options.steps.length-1;
        if (props.next === false || is_last)
          next.hide();
        if (is_last) return;
        object.find('.wizard-next').bindFirst('click', function() {
          if (self.next_step === undefined)
            self.next_step = typeof props.next === 'string'? props.next: index+1;
        });
      });
    },


    _bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, params) {
        console.log("wizard-jump", params);
        self.jumpTo(params);
      });

      this.element.on('wizard-next', function() {
        self.jumpTo(self.stack[self.stack.length-1]+1);
      });

      this.element.on('wizard-prev', function() {
        self.jumpTo(self.stack[self.stack.length-2]);
      });

      this.element.on('processed', function(event, result) {
        if (result) {
          if (result._responses || !self.stack.length || !self.next_step) return;
          if (result.next_step) self.next_step = result.next_step;
        }
        self.jumpTo(self.next_step);
      });

      this.element.find('.wizard-bookmark-active').click(function(i) {
        if (i >= self.first_step)
          self.jumpTo(self.stack[index]);
      });
    },

    nextStep: function(step)
    {
      this.next_step = step;
    }
  })
}) (jQuery);
