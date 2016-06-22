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
    me.params = { action: 'data', path: opts.path, key: opts.key  };
    var inputs = me.inputs = opts.render.create(opts, 'inputs', true)
      .insertAfter(el.hide())
      .append(el)
    me.searcher = inputs.find('.search').on('keyup input cut paste', function() {
      me._load();
    });

    me.drop = opts.render.create(opts, 'drop', true).appendTo(me.inputs);

    me.dropper = inputs.find('.isearch.show-all').click(function() {
      me.searcher.val("");
      me._load();
    });

    if (opts.add && opts.add.url) inputs.find('.isearch.add').show();

    el.on('isearch_add', function( event, data) {
      el.val(data[0]);
      me.searcher.val(data[1]);
    });

    inputs.on('mouseleave', function() { me.drop.hide() });
  },


  _load: function() {
    var me = this;
    var el = me.element;
    var opts = me.options;
    me.params.term = me.searcher.val();
    el.val("");
    $.json('/', {data: me.params}, function(data) {
      if (!data) return;
      if (data._responses)
        el.triggerHandler('server_response', [data]);
      el.trigger('loaded', [data]);
      me._populate(data);
      delete data.rows;
      $.extend(self.params, data);
    });
  },

  _populate: function(data) {
    var me = this;
    var opts = me.options;
    var drop = me.drop;
    drop.children().remove();
    $.each(data.rows, function(i, row) {
      var option = mkn.copy(opts.option);
      option.array = row;
      option = opts.render.initField(option, opts);
      option.label = me._boldTerm(option.label, me.params.term);
      opts.render.create(option).appendTo(drop);
    })
    drop.children().click(function() {
      me.element.val($(this).attr('value'));
      me.searcher.val($(this).attr('chosen'));
      drop.hide();
    });
    drop.show();
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
