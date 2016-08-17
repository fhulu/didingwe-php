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
    var inputs = me.inputs = opts.render.create(opts, 'inputs', true)
      .insertAfter(el)
      .append(el)

    if (!el.is(":visible")) inputs.hide();
    el.on('toggle', function(e, v) {
      el.hide()
      inputs.toggle(v);
    }).hide()
    me.searcher = inputs.find('.search').on('keyup input cut paste', function() {
      if (me.params.term == me.searcher.val()) return;
      me.params.offset = 0;
      me._load();
    });

    me.drop = opts.render.create(opts, 'drop', true)
      .on('click', '.isearch.option', function() {
        el.trigger('selected', [$(this)]);
      })
      .scroll($.proxy(me._scroll,me))
      .appendTo(me.inputs);

    el.on('selected', function(e, option) {
      el.val(option.attr('value'));
      me.searcher.val(option.attr('chosen'));
      me.drop.hide();
      me.dropped = false;
    });

    me.dropper = inputs.find('.isearch.show-all').click(function() {
      if (me.dropped) {
        if (!me.drop.is(':visible')) me.drop.show();
        return;
      }
      me.params.offset = 0;
      me.searcher.val("");
      me._load();
    });

    if (opts.adder && opts.adder.url) inputs.find('.isearch.adder').show();

    el.on('isearch_add', function( event, data) {
      el.val(data[0]);
      me.searcher.val(data[1]);
      me.dropped = false;
      me.drop.hide();
    });

    inputs.on('mouseleave', function() {
      me.drop.hide();
      me.dropped = false;
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
    me.params.term = me.searcher.val();
    el.val("");
    me.justDropped = me.dropped = true;
    me.drop.show();
    $.json('/', {data: me.params}, function(data) {
      if (data._responses)
        el.triggerHandler('server_response', [data]);
      el.trigger('loaded', [data]);
      me._populate(data);
      me._loading(false);
      delete data.rows;
      $.extend(me.params, data);
    });
  },

  _populate: function(data) {
    var me = this;
    var opts = me.options;
    var drop = me.drop;
    if (!me.params.offset) drop.scrollTop(0).children().remove();
    var maxHeight = parseInt(drop.css('max-height'));
    me.autoScrolls = 0;
    $.each(data.rows, function(i, row) {
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
