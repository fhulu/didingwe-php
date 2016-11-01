$.widget( "custom.wizard", {
  _create: function() {
    this.stack = new Array();
    this.first_step = 0;
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

  createPages: function() {
    var me = this;
    var opts = me.options;
    var step = opts.render.create(opts, 'step', true);
    var pending_style = opts.bookmarks.state_styles['pending'];
    if ($.isArray(pending_style)) pending_style = pending_style.join(' ')
    $.each(opts.steps, function(i, info) {
      step.clone().attr('step', info.id).hide().appendTo(me.element);
      me.child('.wizard-bookmark', i).addClass(pending_style);
    })
  },


  createNavigation: function() {
    var opts = this.options;
    opts.render.create(opts, 'navigation', true);
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
    if (!page.find('.wizard-content').exists() || props.clear) {
      this.child('.wizard-navigate').empty();
      this.loadPage(page, index);
    }
    else {
      this.updateNavigation(index, props);
      page.show().triggerHandler('reload');
    }

    this.updateBookmark(index, 'active');
    this.stack.push(index);
  },

  updateBookmark: function(index, state)
  {
    var styles = this.options.bookmarks.state_styles;
    var bm = this.child('.wizard-bookmark',index)
    $.each(styles, function(key, style) {
      if ($.isArray(style)) style = style.join(' ')
      bm.removeClass(style);
    });
    var style = styles[state];
    if ($.isArray(style)) style = style.join(' ')
    bm.addClass(style);
  },

  updateNavigation: function(index, info) {
    var me = this;
    var opts = me.options;
    var bar = me.child('.wizard-navigation').remove();
    if (info.navigate !== undefined && !info.navigate) return;
    var orig_navigation = mkn.copy(opts.navigation);
    var navigation = opts.render.initField(opts.navigation, opts);
    var navs = navigation.contents;
    $.extend(navs, info.navigate);
    var last_step = me.options.steps.length-1;
    $.each(navs, function(i, nav) {
      if (nav.id == 'next' && info.next !== false && index != last_step) {
        nav.path = info.path;
        nav.show = true;
      }
      if (nav.id == 'prev' && info.prev !== false && index != me.first_step) nav.show = true;
    });

    opts.render.create(navigation).appendTo(me.element);
    if (info.prev === false) me.element.find('.wizard-bookmark').each(function(i) {
      if (i < index) me.updateBookmark(i, 'committed');
    });
    me.child('.wizard-next').bindFirst('click', function() {
      if (me.next_step === undefined)
        me.next_step = typeof info.next === 'string'? info.next: index+1;
    });
    opts.navigation = orig_navigation;
  },


  hidePage: function(index, state)
  {
    this.child('.wizard-page', index).hide();
    this.updateBookmark(index, state)
  },

  loadPage: function(page, index)
  {
    var me = this;
    var options = me.options;
    var props = options.steps[index];
    var path = options.path;
    if (props.id.indexOf('/') >= 0)
      path = props.id;
    else if (props.url !== undefined)
      path = props.url;
    else if (path.indexOf('/') === -1)
      path += '/' + props.id;
    else
      path = path.substr(0, path.lastIndexOf('/')+1) + props.id;
    page.empty();
    mkn.showPage({url: path}).then(function(content, info) {
      content.addClass('wizard-content').appendTo(page);
      page.show();
      path = info.path;
      info = options.steps[index] = $.extend({}, info, options.steps[index]);
      info.path = path;
      me.updateNavigation(index, info);
    });
  },


  bindActions: function()
  {
    var me = this;
    me.element.on('wizard-jump', function(event, params) {
      me.jumpTo(params);
    })

    .on('wizard-next', function() {
      me.jumpTo(me.stack[me.stack.length-1]+1);
    })

    .on('wizard-prev', function() {
      me.jumpTo(me.stack[me.stack.length-2]);
    })

    .on('processed', function(event, result) {
      if (result && result._responses && result._responses.errors) return;
      if (result && result.next_step) me.next_step = result.next_step;
      if (!me.stack.length || !me.next_step) return;
      if (me.next_step === true) me.element.trigger('wizard-next');
      if (me.next_step) me.jumpTo(me.next_step);
    })

    .find('.wizard-bookmark').click(function() {
      if ($(this).hasClass('wizard-state-done')) me.jumpTo($(this).attr('step'));
    })

  },

  nextStep: function(step)
  {
    this.next_step = step;
  }
})
