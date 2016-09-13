$.widget( "custom.isearch", {
  options: {
    categoryPrefix: "-",
    fields:  ['value','name'],
    choose: "$name",
    chosen: "$name",
    flags: []
  },

  _create: function ()
  {
    var me = this;
    var el = me.element;
    var opts = me.options;
    me.dropped = me.justDropped = false;
    me.params = { action: 'data', path: opts.path, key: opts.key, offset: 0, size: opts.drop.autoload  };
    var el = me.element.on('keyup input cut paste', function() {
      if (me.params.term == el.val()) return;
      me.params.offset = 0;
      me._load();
    })
    .on('selected', function(e, option) {
      el.attr('value',option.attr('value'));
      el.val(option.attr('chosen'));
      me.drop.hide();
      me.dropped = false;
    })
    .on('mouseleave', function() {
      me.drop.hide();
      me.dropped = false;
    });

    me.drop = opts.render.create(opts, 'drop', true)
      .on('click', '.isearch.option', function() {
        el.trigger('selected', [$(this)]);
      })
      .scroll($.proxy(me._scroll,me))
      .appendTo(me.inputs);

    var parent = el.parent("[for='"+el.attr('id')+"']");
    if (!parent.exists()) parent = el;
    me.show_all = opts.render.create(opts, 'show_all', true)
      .insertBefore(el)
      .click(function() {
        if (me.dropped) {
          if (!me.drop.is(':visible')) me.drop.show();
          return;
        }
        me.params.offset = 0;
        el.val("");
        me._load();
      });

    if (opts.adder && opts.adder.url) inputs.find('.isearch.adder').show();

    el.on('isearch_add', function( event, data) {
      el.val(data[0]);
      me.searcher.val(data[1]);
      me.dropped = false;
      me.drop.hide();
    });

  },


  _scroll: function(e) {
    var me = this;
    var drop = me.drop;
    if(drop.scrollHeight() - drop.scrollTop() != drop.height() || me._loading()) return;
    if (me.params.total && me.params.offset + me.params.size > me.params.total) return;
    me.params.offset += me.params.size;
    me._load();
  },

  _loading: function(val) {
    if (val == undefined) return this.drop.data('loading');
    this.drop.data('loading', val);
  },

  _load: function() {
    var me = this;
    if (me._loading()) return;
    me._loading(true);
    var el = me.element;
    var opts = me.options;
    me.params.term = el.val();
    me.justDropped = me.dropped = true;
    me.drop.show();
    $.json('/', {data: me.params}, function(data) {
      el.trigger('loaded', [data]);
      if ($.isPlainObject(data) && data._responses) {
        el.triggerHandler('server_response', [data]);
        return;
      }
      me._populate(data);
      me._loading(false);
    });
  },

  _populate: function(data) {
    var me = this;
    var opts = me.options;
    var drop = me.drop;
    if (!me.params.offset) drop.scrollTop(0).children().remove();
    var maxHeight = parseInt(drop.css('max-height'));
    me.autoScrolls = 0;
    $.each(data, function(i, row) {
      var option = mkn.copy(opts.option);
      option.array = row;
      option = opts.render.initField(option, opts);
      option.embolden = me._boldTerm(option.embolden, me.params.term);
      opts.render.create(option).appendTo(drop);
    })
    if (!me.justDropped) return;
    me.justDropped = false;
    drop.scrollTop(0);
  },

  _boldTerm: function(text, term)
  {
    $.each(term.split(' '), function(i, val) {
      text = text.replace(
                new RegExp(
                  "(?![^&;]+;)(?!<[^<>]*)(" +
                  $.ui.autocomplete.escapeRegex(val) +
                  ")(?![^<>]*>)(?![^&;]+;)", "gi"),
                "<strong>$1</strong>")
    });
    return text;
  },

});
