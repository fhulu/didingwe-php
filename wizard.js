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
      this.createBookmarks();
      this.createPages();
      this.bindActions();
      this.stack = new Array();
      this.jumpTo(0);
    },


    child: function(selector, index) {
      if (!index) index = 0;
      return this.element.children(selector).eq(index);
    },

    createBookmarks: function() {
      var me = this;
      var type = me.options.bookmarks;
      if (!type) return;
      me.bookmarkHolder = $('<div>').addClass('wizard-bookmark-holder-'+type).appendTo(me.element);
      $.each(me.options.steps, function(i, info) {
        me.createBookmark(i, info);
      })
    },


    createBookmark: function(index, info) {
      var state = index==0? 'active': 'pend';
      var bookmark = $('<div>')
        .addClass('wizard-bookmark')
        .addClass('wizard-state-'+state)
        .attr('step',index).appendTo(this.bookmarkHolder);
      $('<div>').addClass('wizard-bookmark-number').text(++index+'.').appendTo(bookmark);
      $('<div>').addClass('wizard-bookmark-title').text(info.name).appendTo(bookmark);
    },

    createPages: function() {
      var me = this;
      $.each(this.options.steps, function(i, info) {
        $('<div>').addClass('wizard-page').hide().appendTo(me.element);
      })
    },

    jumpTo: function(index) {
      if ($.isPlainObject(index)) index = index.index;
      if (typeof index === 'string') {
        var page = this.child('.wizard-page[step="'+index+'"]');
        if (!page.exists()) return;
        index = this.children('.wizard-page').index(page);
      }
      if (this.stack.length) {
        var top_index = this.stack[this.stack.length-1];
        if (index === top_index) return;
        if (top_index < index) {  // going forward
          this.hidePage(top_index, 'done');
        }
        else do { // goin backwards
            top_index = this.stack.pop();
            this.hidePage(top_index, 'visited');
        } while (top_index >  index);
      }

      this.showPage(index);
      delete this.next_step;
    },

    showPage: function(index) {
      var page = this.child('.wizard-page', index);
      var props = this.options.steps[index];
      if (!page.hasClass('wizard-loaded') || props.clear)
        this.loadPage(page, index);
      else
        page.triggerHandler('reload');
      page.addClass('wizard-current').show();
      this.activateBookmark(index);
      this.stack.push(index);
    },

    activateBookmark: function(index)
    {
      this.child('wizard-bookmark',index)
        .removeClass('wizard-state-pend')
        .addClass('wizard-state-active');
    },

    hidePage: function(index, state)
    {
      this.child('.wizard-page', index).hide();
      this.child('.wizard-bookmark', index).addClass('wizard-state-'+state);
    },

    loadPage: function(page, index)
    {
      page.addClass('wizard-loading');
      var props = this.options.steps[index];
      var path = this.options.path;
      if (props.id.indexOf('/') >= 0)
        path = props.id;
      else if (props.url !== undefined)
        path = props.url;
      else if (path.indexOf('/') === -1)
        path += '/' + props.id;
      else
        path = path.substr(0, path.lastIndexOf('/')+1) + props.id;
      var tmp = $('<div>');
      tmp.page({path: path, key: this.options.key});
      if (path[0] === '/') path = path.substr(1);
      var self = this;
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object) {
        page.replaceWith(object);
        page = object;
        page.attr('step', index)
          .addClass('wizard-page')
          .addClass('wizard-loaded');
        var prev = page.find('#prev');
        if (props.prev === false || index === 0) {
          prev.hide();
          self.first_step = index;
        }

        var next = page.find('#next');
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


    bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, params) {
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

      this.element.find('.wizard-bookmark.wizard-state-visited').click(function(i) {
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
