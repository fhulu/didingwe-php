$.fn.page = function(options)
{
  var defer = $.Deferred();
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
      var data = $.extend({key: options.key}, options.request, {path: path, action: 'read'});
      $.json('/', { data: data }, function(result) {
        page.parent.triggerHandler('server_response', result);
        result.values = $.extend({}, options.values, result.values );
        if (result.path) page.show(result);
      });
    },

    show: function(data)
    {
      var self = this;
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
      var r = new mkn.render({invoker: page.parent, types: data.types, id: this.id, key: options.key, request: options.request} );
      var object = r.render(data, 'fields');
      page.object = object;
      data.values = values;
      object.addClass('page').appendTo(parent);
      if ($.isPlainObject(data.fields.parent))
        mkn.setClass(parent, data.fields.parent.class);

      parent.trigger('read_'+this.id, [object, data.fields]);
      defer.resolve(object, data.fields);
      return this;
    },

    trigger_response: function(result, invoker)
    {
      if (result && result._responses)
        this.parent.trigger('server_response', [result, invoker]);
    }

  };
  options.fields && page.show(options) || page.load();
  return defer.promise();
}
