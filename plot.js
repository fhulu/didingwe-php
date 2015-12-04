
$.widget("ui.plotter", {
  options: {
  },

  _create: function()
  {
    var self= this;
    if (this.options.load) {
      var data = { action: 'data', path: this.options.path + '/load', key: this.options.key };
      $.json('/', {data: data}, function(data) {
        self.plot(data);
      });
    }
    else if (this.options.data)
      self.plot(this.options.data);
  },

  to_jqplot: function(field, value, sub_value)
  {
    if (!field) return;
    if (sub_value !== undefined) {
      field = field[value];
      value = sub_value;
    }
    if (field && field[value]) field[value] = $.jqplot[field[value]];
  },

  plot: function(data)
  {
    var options = $.extend({}, this.options);
    options.title = { text: options.name, color: options.titleColor }
    this.to_jqplot(options.axesDefaults, "tickRenderer");
    this.to_jqplot(options.axes, "xaxis", "renderer");
    this.to_jqplot(options.axes, "yaxis", "renderer");

    this.plot = $.jqplot(this.options.id, data, this.options );
  }

});
