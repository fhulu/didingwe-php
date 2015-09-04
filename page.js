$.fn.page = function(options, callback)
{
  if (options instanceof Function) callback = options;
  var self = this;
  var page  = {
    parent: self,
    object: null,
    options: options,
    data: null,
    creation: null,
    links: "",
    loading: 0,
    load: function()
    {
      var path = options.path;
      if  (path[0] === '/') path=options.path.substr(1);
      var data = { path: path, action: 'read', key: options.key};
      $.extend(data, options.request);
      $.json('/', { data: data }, function(result) {
        page.parent.trigger('server_response');
        result.values = $.extend({}, options.values, result.values );
        if (result.path) page.show(result);
      });
    },

    show: function(data)
    {
      var self = this;
      this.globals = {};
      $.each(data.fields, function(code, value) {
        self.globals[code] = value;
      });
      this.data = data;
      this.types = this.data.types;
      var parent = page.parent;
      this.id = options.page_id = data.path.replace('/','_');
      var values = data.fields.values || data.values;
      if (data.fields.name === undefined)
        data.fields.name = toTitleCase(data.path.split('/').pop().replace('_',' '));
      data.fields.path = data.path;
      data.fields.sub_page = false;
      data.fields.id = this.id;
      var r = new mkn.render({invoker: page.parent, types: data.types, id: this.id, key: options.key} );
      var result = r.create(data.fields);
      var object = result[1];
      page.object = object;
      data.fields = result[0];
      data.values = values;
      assert(object !== undefined, "Unable to create page "+this.id);
      object.addClass('page').appendTo(parent);
      parent.trigger('read_'+this.id, [object, data.fields]);


      object.on('create_child', function(event, field, parent) {
        if (parent === undefined) parent = event.trigger;
        var result = page.create(field);
        var child = result[1];
        child.appendTo(parent);
        child.value(field.value);
      });

      var children = object.find("*");
      children.on('show', function(e, invoker,show) {
        if (show === undefined) return false;
        $(this).toggle(parseInt(show) === 1 || show === true);
        e.stopImmediatePropagation();
      });

      children.on('refresh', function(e) {

      });
      return this;
    },

    trigger_response: function(result, invoker)
    {
      if (result && result._responses)
        this.parent.trigger('server_response', [result, invoker]);
    }

  };
  options.fields && page.show(options) || page.load();
  return page.object;
}
