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
    me.searcher = el.find('.isearch-searcher').on('keyup input cut paste', function() {
      if (me.params.term == $(this).val()) return;
      me.params.offset = 0;
      me._load();
    });

    var dropper = el.find('.isearch-dropper').click(function() {
      if (me.dropped) {
        if (!me.drop.is(':visible')) me.drop.show();
        return;
      }
      me.params.offset = 0;
      me.searcher.val("");
      me._load();
    });

    var adder = el.find('.isearch-adder').click(function() {
    });
    me.drop = el.find('.isearch-drop').on('click', '.isearch-option', function() {
      el.trigger('selected', [$(this)]);
    })
    .on('mouseleave', function() {
      $(this).hide();
      me.dropped = false;
    })
    .scroll($.proxy(me._scroll,me))

    if (opts.adder && opts.adder.url) inputs.find('.isearch.adder').show();

    el.on('selected', function(e, option) {
      el.attr('value', option.attr('value'));
      me.searcher.val(option.attr('chosen'));
      me.drop.hide();
      me.dropped = false;
    })
    .on('added', function( event, data) {
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
    me.params.term = me.searcher.val();
    el.val("");
    me.justDropped = me.dropped = true;
    me.drop.show();
    $.json('/', {data: me.params}, function(result) {
      if (result._responses)
        el.triggerHandler('server_response', [result]);
      if (!result.data) return;
      el.trigger('loaded', [result]);
      me._populate(result.data);
      me._loading(false);
      delete result.data;
      $.extend(me.params, result);
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
