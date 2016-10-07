$.widget( "custom.wizard", {
  _create: function() {
    this.stack = new Array();
    var el = this.element;

    this.first_step = 0;
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
    var opts = me.options;
    if (!$.isPlainObject(opts.bookmarks)) return;
    opts.render.create(opts, 'bookmarks', true).appendTo(me.element);
  },

  createPages: function() {
    var me = this;
    var opts = this.options;
    var step = opts.render.create(opts, 'step', true);
    $.each(this.options.steps, function(i, info) {
      step.clone().attr('step', info.id).hide().appendTo(me.element);
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
    if (!page.find('.wizard-content').exists() || props.clear) {
      this.child('.wizard-navigate').empty();
      this.loadPage(page, index);
    }
    else {
      this.updateNavigation(index, props);
      page.removeClass('wizard-hide').triggerHandler('reload');
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
    var navs = $.extend([], me.options.navigate, info.navigate);
    var last_step = me.options.steps.length-1;
    $.each(navs, function(i, nav) {
      if (nav.id == 'next') {
        if (info.next === false || index == last_step) return;
        nav.path = info.path;
      }
      if (nav.id == 'prev' && index == me.first_step) return;
      me.options.render.create(navs, i).appendTo(bar);
    });

    if (info.prev === false) me.element.find('.wizard-bookmark').each(function(i) {
      if (i < index) $(this).addClass('wizard-state-committed');
    });
    me.child('.wizard-next').bindFirst('click', function() {
      if (me.next_step === undefined)
        me.next_step = typeof info.next === 'string'? info.next: index+1;
    });
  },


  hidePage: function(index, state)
  {
    this.child('.wizard-page', index).addClass('wizard-hide');
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
    page.empty().removeClass('wizard-hide');
    var content = $('<div class=wizard-content>').appendTo(page);
    var tmp = $('<div class=loading>').appendTo(content);
    content.page({path: path, key: options.key}).then(function(object, info) {
      tmp.replaceWith(object);
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
