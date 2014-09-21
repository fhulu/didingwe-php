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
      var key = options.data.key;
      data = page.expand_fields(id,data);
      page.expand_children(data);
      var parent = page.parent;
      var object = $(data.html).addClass('page').appendTo(parent);
      object.on('loaded', function() {
        page.set_options(parent, id, data,function(){
          page.bind_actions(parent, data, key);
          page.load_values(object);
          parent.trigger('read_'+id, [object,data]);
        });
      });
      object.on('_new_action', function(e, obj,field, options) {
        page.bind_action(obj, field, options);
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

    bind_actions: function(parent, data, key)
    {
      $.each(data, function(field, options) {
        if (!$.isPlainObject(options)) return;
        var obj = parent.find('#'+field);
        if (!obj.exists()) {
          page.bind_actions(parent, options, key);
          return;
        }
        options.key = key;
        page.bind_action(obj, field, options);
        page.bind_actions(obj, options, key)
      });
    },
    
    bind_action: function(obj, field, options)
    {
      var action = options.action;
      if (action === undefined) return;
      var key = options.key;
      var selector = options.selector;
      options = undefined;
      obj.click(function(event) {
        if (action.indexOf('dialog:') === 0) {
          page.showDialog(action.substr(7), {key: key});
        }
        else if (action.indexOf('url:') === 0) {
          document.location = action.substr(4);
        }
        else if (action === '') {
          var page_id = obj.parents('[id]').eq(0).attr('id');
          var data = { _page: page_id, _field: field, key: key };
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+page_id+"$2");
            obj.jsonCheck(event,selector, '/?a=page/action', { data: data }, function(result) {
              if (result === null) result = undefined;
              obj.trigger('processed', [result]);
              if (result !== undefined) page.accept(result._responses, obj);
            });
          } 
          else  $.json('/?a=page/action', {data: data}, function(result) {
            obj.trigger('processed', [result]);
            if (result !== undefined) page.accept(result._responses, obj);
          });
        }
      });
    },
    
    accept: function(responses, invoker)
    {
      if (!$.isPlainObject(responses)) return;
      var parent = invoker.parents('.ui-dialog-content').eq(0);
      if (!parent.exists()) parent = page.parent;
      var self = this;
      $.each(responses, function(key, val) {
        switch(key) {
          case 'alert': alert(val); break;
          case 'show_dialog': self.showDialog(val, responses.options); break;
          case 'close_dialog': parent.dialog('destroy').remove(); break;
          case 'redirect': location.href = val; break;
          case 'update': parent.setChildren(val); break;
        }
      });      
    },

    showDialog: function(dialog, options)
    {
      var params = { page: dialog, key: options.key };
      var tmp = $('<div></div>').page({data:params});
      tmp.on('read_'+dialog, function(event, object, options) {
        object.dialog($.extend({modal:true}, options));
      });
    },
        
  };
  if (options.autoLoad) page.load();
  return this;
}


