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
      $.json(url, { data: data }, this.show);
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
      var reserved = ['html','code','template','create','css','script'];
      $.each(parent, function(key, value) {
        if (typeof value !== "string" || reserved.indexOf(key)>=0) return;
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
        if (child.hidden === undefined) {
          var expanded = page.expand_template(field, child, object);
          page.expand_children(child); 
          if (!expanded)
            page.expand_template(field, child, object);
        }
        object.html = object.html.replace('$'+field, child.html);
        if (object.template !== undefined)
          object.html += child.html;
        object.html = page.expand_value(object, object.html);
      });
    },

    
    load_link: function(link,type, callback)
    {
      if (page.links.indexOf("["+link+"]")>=0)  {
        callback();
        return;
      }

      var element;
      if (type == 'css') {
        element = document.createElement('link');
        element.rel = 'stylesheet';
        element.type = 'text/css';
        element.media = 'screen';
        element.href = link;
      }
      else if (type === 'script') {
        element = document.createElement('script');
        element.src =  link;
        element.type = 'text/javascript';
      }
      if (element === undefined) {
        console.log('Error loading ', link);
        return;
      }
      var loaded = false;
      if (callback !== undefined) element.onreadystatechange = element.onload = function() {
        if (!loaded) callback();
        loaded = true;
      }
      var head = document.getElementsByTagName('head')[0];
      head.appendChild(element);
      page.links +="["+link+"]";
    },
    
    load_links: function(object, type, options, callback)
    {
      var links = options[type];
      if (links === undefined) {
        if (callback !== undefined) callback();
        return;
      }
      links = links.split(',');
      var loaded = 0;
      $.each(links, function(i, link) {
        page.load_link(link,type, function() {
          if (callback !== undefined && ++loaded == links.length) {
            callback();
          }
        });
      });      
    },
    
    load_values: function(object)
    {
      if (options.data === undefined || options.data.load === undefined) return;
      object.loadChildren('/?a=page/load', {data:options.data});
    },
    

    set_option: function(parent, options, callback)
    {
      var object = parent.find('#'+options.code);
      if (!object.exists()) {
        if (callback !== undefined) callback();
        return;
      }
      
      page.load_links(object, 'css', options);
      page.load_links(object, 'script', options, function() {
        object.customCreate(options);
        if (callback !== undefined) callback();
      });      
    },
    
    set_options: function(parent, options, callback)
    {
      var count = $.jsonSize(options);
      var set = 0;
      $.each(options, function(f, option) {
        if (!$.isPlainObject(option)) {
          if (++set === count) page.set_option(parent,options, callback);
        }
        else page.set_options(parent,option, function() {
          if (++set === count) page.set_option(parent,options, callback);
        });
      });      
    },
    
    show: function(data)
    {
      data = page.expand_fields(id,data);
      page.expand_children(data);
      var parent = obj.parent();
      obj.html(data.html);
      obj.on('loaded', function() {
        page.set_options(parent, data,function(){
          page.assign_handlers(obj,data);
          obj.trigger('read', [data]);
          page.load_values(obj);
        });
      });
      page.load_data(obj);
    },
    
    load_data: function(parent) 
    {
      var children = parent.find('[has_data]');
      var count = children.length;
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

    assign_handlers: function(parent,object)
    {
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child)) return;
        var obj = parent.find('#'+field);
        var data = {_page: id, _field: field };
        if ((child.selector !== undefined || child.action !== undefined) && obj.attr('action')===undefined)  {
          obj.attr('action','');
          var selector = child.selector;
          var action = child.action;
          child.action = child.selector = undefined;
          if (action === undefined) action = '';
          if (action.indexOf('dialog:') === 0) {
            obj.click(function() {
              alert('what')
              var div = $('<div></div>');
              div.attr('id', action.substr(7));
              div = div.page();
              div.on('read', function(event, options) {
                div.dialog($.extend({modal:true}, options));
              });
            });
          }
          else if (action.indexOf('url:') === 0) {
            obj.click(function() { document.location = action.substr(4); });
          }
          else if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+id+"$2");
            obj.checkOnClick(selector, '/?a=page/action', {data: data }, function(result) {
              obj.trigger('processed', [result]);
              if (result.url !== undefined) document.location = result.url;
            });
          }
          else $.json('/?a=page/action', {data: data}, function(result) {
             obj.trigger('processed', [result]);
          });
        }
        else if (child.url !== undefined) {
          obj.click(function() { location.href = child.url; });
        }
        page.assign_handlers(parent, child);
      });
    }
  };
  if (options.autoLoad) page.load();
  return obj;
}


