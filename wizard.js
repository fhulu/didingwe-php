(function( $ ) {
  $.widget( "ui.wizard", {
    _create: function() {
      this.stack = new Array();
      var el = this.element.addClass('wizard');
      this.options.render.expandFields(this.options, "steps", this.options.steps);

      this.createBookmarks();
      this.createPages();
      this.createNavigation();
      this.bindActions();
      this.stack = new Array();
      this.jumpTo(0);
    },


    child: function(selector, index) {
      if (!index) index = 0;
      return this.element.find(selector).eq(index);
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
      var bookmark = $('<div>')
        .addClass('wizard-bookmark wizard-state-pend')
        .attr('step',index).appendTo(this.bookmarkHolder);
      $('<div>').addClass('wizard-bookmark-number').text(++index+'.').appendTo(bookmark);
      $('<div>').addClass('wizard-bookmark-title').text(info.name).appendTo(bookmark);
      var me = this;
      bookmark.click(function() {
        if ($(this).hasClass('wizard-state-done'))
          me.jumpTo($(this).attr('step'));
      });

    },

    createPages: function() {
      var me = this;
      $.each(this.options.steps, function(i, info) {
        $('<div>').addClass('wizard-page').hide().appendTo(me.element);
      })
    },


    createNavigation: function() {
      var me = this;
      var nav = $('<div class=wizard-navigate>').appendTo(this.element);
      this.options.render.expandFields(this.options,'navigate',this.options.navigate);
    },

    jumpTo: function(index) {
      if ($.isPlainObject(index)) index = index.index;
      if (typeof index === 'string') {
        var page = this.child('.wizard-page[step="'+index+'"]');
        if (!page.exists()) return;
        index = this.element.children('.wizard-page').index(page);
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
      else {
        page.triggerHandler('reload');
        this.updateNavigation(index, props);
        page.show();
      }

      this.updateBookmark(index, 'active');
      this.stack.push(index);
    },

    updateBookmark: function(index, state)
    {
      var states = ['pend','active', 'done', 'visited'];
      var bm = this.child('.wizard-bookmark',index)
      for (var i in states) {
        bm.removeClass('wizard-state-'+states[i]);
      }
      bm.addClass('wizard-state-'+state);
    },

    updateNavigation: function(index, info) {
      var me = this;
      var bar = me.child('.wizard-navigate').empty();
      if (info.navigate)
        me.options.render.expandFields(info, "navigate", info.navigate);
      var navs = $.extend({}, me.options.navigate, info.navigate);
      var last_step = me.options.steps.length-1;
      $.each(navs, function(i, nav) {
        if (nav.id == 'next') {
          if (info.next === false || index == last_step) return;
          nav.path = info.path;
        }
        if (nav.id == 'prev' && (info.prev === false || index === 0)) {
          self.first_step = index;
          return;
        }
        me.options.render.create(nav).appendTo(bar);
      });

      me.child('.wizard-next').bindFirst('click', function() {
        if (me.next_step === undefined)
          me.next_step = typeof info.next === 'string'? info.next: index+1;
      });
    },


    hidePage: function(index, state)
    {
      this.child('.wizard-page', index).hide();
      this.updateBookmark(index, state)
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
      tmp.on('read_'+path.replace(/\//, '_'), function(event, object, info) {
        page.replaceWith(object);
        page = object.attr('step', index).addClass('wizard-page wizard-loaded');
        path = info.path;
        info = self.options.steps[index] = $.extend({}, info, self.options.steps[index]);
        info.path = path;
        self.updateNavigation(index, info);
      });
    },


    bindActions: function()
    {
      var self = this;
      this.element.on('wizard-jump', function(event, params) {
        self.jumpTo(params);
      })

      .on('wizard-next', function() {
        self.jumpTo(self.stack[self.stack.length-1]+1);
      })

      .on('wizard-prev', function() {
        self.jumpTo(self.stack[self.stack.length-2]);
      })

      .on('processed', function(event, result) {
        if (result) {
          if (result._responses || !self.stack.length || !self.next_step) return;
          if (result.next_step) self.next_step = result.next_step;
        }
        if (self.next_step) self.jumpTo(self.next_step);
      })
    },

    nextStep: function(step)
    {
      this.next_step = step;
    }
  })
}) (jQuery);
