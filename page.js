$.fn.page = function(options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {method: 'post'};
  } 
  var self = this;
  options = $.extend({
    method: 'post',
    url: '/?a=page/read',
    error: undefined,
    autoLoad: true
  }, options);
  
  var page  = {
    parent: self,
    object: null,
    options: options,
    data: null,
    creation: null,
    links: "",
    load: function(url) 
    {
      if (url === undefined) url = options.url;
      $.json(url, { data: options.data }, this.show);
    },
   
    expand_value: function(values,value)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string' && value !== null) {
          value = value.replace(new RegExp('\\$'+code, 'g'), subst);
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
    
    load_links: function(type, options, callback)
    {
      var links = options[type];
      if (links === undefined || links === null) {
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
    

    set_option: function(parent, id, options, callback)
    {
      var object = parent.find('#'+id);
      if (!object.exists()) {
        if (callback !== undefined) callback();
        return;
      }
      
      page.load_links('css', options);
      page.load_links('script', options, function() {
        if (options.create !== undefined && options.create !== null) {
          var create_opts = $.extend({key: page.options.data.key}, options);
          object.customCreate(create_opts);
        }
        if (callback !== undefined) callback();
      });      
    },
    
    set_options: function(parent, id, options, callback)
    {
      var count = $.jsonSize(options);
      var set = 0;
      $.each(options, function(key, option) {
        if (!$.isPlainObject(option)) {
          if (++set === count) page.set_option(parent, id, options, callback);
        }
        else page.set_options(parent, key, option, function() {
          if (++set === count) page.set_option(parent, id, options, callback);
        });
      });      
    },
    
    show: function(data)
    {
      var id = options.data.page;
      data = page.expand_fields(options.data.page,data);
      page.expand_children(data);
      var parent = page.parent;
      var object = $(data.html).appendTo(parent);
      object.on('loaded', function() {
        page.set_options(parent, options.data.page, data,function(){
          page.assign_handlers(object, id, data);
          page.load_values(object);
          parent.trigger('read_'+id, [object,data]);
        });
      });
      page.load_data(object);
    },
    
    load_data: function(parent) 
    {
      var id = parent.attr('id');
      var children = parent.find('[has_data]');
      var count = children.length;
      if (!count) {
        parent.trigger('loaded');
        return;
      }
      var self = this;
      var loaded = 0;
      children.each(function() {
        var object = $(this);
        object.removeAttr('has_data');
        var field_id = object.attr('id');
        if (field_id === undefined) field_id = id;
        var params = {_page: id, _field:field_id, key: self.options.data.key};
        $.json('/?a=page/data', {data:params}, function(result) {
          if (result === undefined || result === null) {
            console.log('No page data result for page: ', id, ' field: ', field_id);
            return;
          }
          result.html = object.html();
          page.expand_children(result);
          object.html(result.html);
          if (++loaded == count) 
            parent.trigger('loaded', result);
        });
      });
    },

    assign_handlers: function(parent, id, data)
    {
      var key = this.options.data.key;
      $.each(data, function(field, child) {
        if (!$.isPlainObject(child)) return;
        var obj = parent.find('#'+field);
        if (!obj.exists()) {
          page.assign_handlers(parent, id, child);
          return;
        }
        var data = { _page: id, _field: field };
        if (key !== undefined) data.key = key;
        if ((child.selector !== undefined || child.action !== undefined) && obj.attr('action')===undefined && child.action !== null)  {
          obj.attr('action','');
          var selector = child.selector;
          var action = child.action;
          child.action = child.selector = undefined;
          if (action === undefined) action = '';
          if (action.indexOf('dialog:') === 0) {
            obj.click(function() {
              var dialog = action.substr(7);
              var params = { page: dialog };
              if (key !== undefined) params.key = key;
              var tmp = $('<div></div>').page({data:params});
              tmp.on('read_'+dialog, function(event, object, options) {
                object.dialog($.extend({modal:true,title: options.desc}, options));
              });
            });
          }
          else if (action.indexOf('url:') === 0) {
            obj.click(function() { document.location = action.substr(4); });
          }
          else if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+id+"$2");
            obj.checkOnClick(selector, '/?a=page/action', {method: 'get', data: data }, function(result) {
              if (result === null) result = undefined;
              obj.trigger('processed', [result]);
              if (result === undefined) return;
              if (result.url !== undefined) location.href = result.url;
              if (result.alert !== undefined) alert(result.alert);
              if (result.close_dialog !== undefined) {
                var dialog = obj.parents('.ui-dialog-content').eq(0);
                dialog.dialog('close');
              }
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
        page.assign_handlers(obj, field, child);
      });
    }
  };
  if (options.autoLoad) page.load();
  return this;
}


