$.fn.page = function(options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {method: 'post'};
  } 
  var obj = $(this);
  var id = obj.attr('id');
  var parent_id = obj.parent().attr('id');
  if (parent_id === undefined) parent_id = '';
  var selector = '#'+id;
  options = $.extend({
    method: 'post',
    url: '/?a=page/read',
    error: undefined,
    autoLoad: true
  }, options);
  
  var page  = {
    object: obj,
    options: options,
    data: null,
    id: id,
    creation: null,
    links: "",
    load: function(url) 
    {
      if (url === undefined) url = options.url;
      var data = $.extend({page:id}, options.data);
      $.json(url, { data: data }, this.read);
    },
   
    expand_value: function(values,value)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string') {
          value = value.replace('$'+code, subst);
          if (value.indexOf('$') < 0) return;
        }
      });
      return value;
    },
    
    expand_fields: function(parent_id, data)
    {
      $.each(data, function(field, value) {
        if (typeof value === 'string' && value.indexOf('$') >= 0 && field !== 'template' && field != 'attr') {
          value = value.replace('$code', parent_id);
          data[field] = page.expand_value(data, value);
        }
        else if ($.isPlainObject(value)) {
          page.inherit_values(data, value);
          data[field] = page.expand_fields(field, value);
        }
      });
      return data;
    },
    
    expand_attr: function(html, values, attr)
    {
      if (attr === undefined) return html;
      attr = page.expand_value(values, attr);
      return html.replace(/^<(\w+) ?/,'<$1 '+attr + ' ');
    },
    
    inherit_values: function(parent, child)
    {
      $.each(parent, function(key, value) {
        if (typeof value !== "string") return;
        if (key === "html" || key === 'code' || key === 'template') return;
        if (child[key] !== undefined) return;
        child[key] = value;
      });
    },
    
    expand_template: function(field, child, object)
    {
      if (object.template === undefined) return false;
      var expanded = page.expand_value(child, object.template);
      expanded = expanded.replace('$code', field);
      if (child.html === undefined) {
        if (expanded.indexOf('$field') >= 0) return false;
        child.html = expanded;
      }
      else {
        child.html = page.expand_attr(child.html, child, object.attr);
        child.html = expanded.replace('$field', child.html);
      }
      return true;
    },
    
    expand_children: function(object)
    {
      if (!$.isPlainObject(object) && !$.isArray(object)) return;
      if (object.html === undefined) {
        object.html = "";
        if (object.template === undefined) object.template = "$field";
      }
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child) && !$.isArray(child) || child === null) return;
        page.inherit_values(object, child);
        if (child.html !== undefined)
          child.html = page.expand_attr(child.html, child, child.attr);
        var expanded = page.expand_template(field, child, object);
        page.expand_children(child); 
        if (!expanded)
          page.expand_template(field, child, object);
        object.html = object.html.replace('$'+field, child.html);
        if (object.template !== undefined)
          object.html += child.html;
        object.html = page.expand_value(object, object.html);
      });
    },

    custom_create: function(object)
    {
      $.each(object, function(f, child) {
        if ($.isPlainObject(child)) page.custom_create(child);
      });
      
      if (object.create !== undefined) {
        var create = object.create;
        object.create = undefined;
        object.creation = $('#'+object.code)[create](object).data(create);
      }      
    },
    
    load_link: function(link,type, callback)
    {
      if (page.links.indexOf("["+link+"]")>=0) return;

      if (type == 'css') {
        $('<link>').attr('ref','stylesheet')
                .attr('type','text/css')
                .attr('href', link)
                .appendTo('head');
      }
      else if (type === 'script') {
        var script = $('<script></script>').attr('type','text/javascript').attr('src', link);
        if (callback !== undefined) {
          script.onreadystatechange = callback;
          script.onload = callback;
        }
        script.appendTo('head');
      }
      page.links +="["+link+"]"
    },
    
    load_links: function(object, type, callback)
    {
      if (object[type] !== undefined) {
        var links = object[type].split(',');
        object[type] = undefined;
        var loaded = 0;
        $.each(links, function(i, link) {
          page.load_link(link,type, function() {
            console.log('loading', link, loaded)
            if (++loaded == links.length && callback !== undefined)
              callback();
          });
        });
      }      
      $.each(object, function(f, child) {
        if ($.isPlainObject(child)) page.load_links(child, type);
      });      
    },
    
    load_values: function(object)
    {
      var params = getQueryParams(location.search);
      if (params.load === undefined) return;
      object.loadChildren('/?a=page/load', {data:params});
    },
    
    read: function(data)
    {
      data = page.expand_fields(id,data);
      page.expand_children(data);
      var html = $(data.html);
      
      obj.html(html.html());
      var count=0;
      var parent = obj.parent();
      parent.on('loaded', function() {
        page.load_links(data,'css');
        page.load_links(data,'script', function() {
          //page.custom_create(data);
        });
        page.custom_create(data);
        page.assign_handlers(data);
        obj.trigger('read', [data]);
        page.load_values(obj);
      });
      page.load_data(parent);
    },
    
    get_config: function(config, domid)
    {
      var found;
      $.each(config.children, function(id, obj) {
        if (id === domid) {
          found = obj;
          return;
        }
      });
      if (found) return found;
      
      $.each(config.children, function(id, obj) {
        var found = page.get_config(obj, domid);
        if (found !== null) return found;
      });
      return null;
    },
    
    load_data: function(parent) 
    {
      var children = parent.find('[has_data]');
      var count = children.length;
      console.log('children count', count)
      if (!count) {
        parent.trigger('loaded');
        return;
      }
      var loaded = 0;
      children.each(function() {
        var object = $(this);
        object.removeAttr('has_data');
        var field_id = object.attr('id');
        var params = {_page: id, _field:field_id};
        $.json('/?a=page/data', {data:params}, function(result) {
          result.html = object.html();
          page.expand_children(result);
          object.html(result.html);
          if (++loaded == count) 
            parent.trigger('loaded', result);
        });
      });
    },

    assign_handlers: function(object)
    {
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child)) return;
        var obj = $('#'+field);
        var data = {_page: id, _field: field };
        if (child.selector !== undefined || child.action !== undefined && obj.attr('action')===undefined)  {
          obj.attr('action','');
          var selector = child.selector;
          child.action = child.selector = undefined;
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+id+"$2");
            obj.checkOnClick(selector, '/?a=page/action', {data: data }, function(result) {
              obj.trigger('processed', [result]);
            });
          }
          else obj.click(function() {
            $.json('/?a=page/action', {data: data}, function(result) {
              obj.trigger('processed', [result]);
            });
          });
        }
        else if (child.url !== undefined) {
          obj.click(function() { location.href = child.url; });
        }
        page.assign_handlers(child);
      });
    }
  };
  if (options.autoLoad) page.load();
  return page;
}


