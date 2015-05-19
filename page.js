$.fn.page = function(options, callback)
{
  if (options instanceof Function) callback = options;
  var post_methods = [ 'call', 'sql', 'sql_values', 'sql_rows' ];
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
      this.parent.on('server_response', function(event, result, invoker) {
        page.respond(result, invoker);
      });
      
      var path = options.path;
      if  (path[0] === '/') path=options.path.substr(1);
      $.json('/', this.server_params('read', path), function(result) {
        page.respond(result);
        result.values = $.extend({}, options.values, result.values ); 
        if (result.path) page.show(result);
      });
    },
   
    server_params: function(action, path, params)
    {
      if (!path) path = this.options.path;
      return { data: $.extend({}, options.request, {key: options.key}, params, 
        {action: action, path: path })};
    },
    
    expand_function: function(value, parent_id)
    {
      var matches = /(copy|css)\s*\((.+)\)/.exec(value);
      if (!matches || matches.length < 1) return value;
      var source = matches[2];
      if (matches[1] === 'copy')
        source = $('#'+source+' #'+parent_id+',[name='+source+'] #'+parent_id);
      else
        source = $(source);
      return source.value();
    },
    
    expand_value: function(values,value,parent_id)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string' && value !== null) {
          subst = page.expand_function(subst, parent_id)
          value = value.replace(new RegExp('\\$'+code, 'g'), subst);
          values[code] = subst;
          if (value.indexOf('$') < 0) return;
        }
      });
      return value;
    },
    
    expand_fields: function(parent_id, data)
    {
      if (!data) return data;
      var self = this;
      $.each(data, function(field, value) {
        if (typeof value !== 'string' || value.indexOf('$') < 0 || field === 'template' && field === 'attr') return;
        value = value.replace('$code', parent_id);
        data[field] = page.expand_value(data, value, parent_id);
      });
      if (data.html)
        data.html = data.html.replace(/\$(value|desc)/, '');
      return data;
    },
        
    load_link: function(link,type, callback)
    {
      var element;
      ++page.loading;
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
      assert(element !== undefined, "Error loading "+link);
      var loaded = false;
      if (callback !== undefined) element.onreadystatechange = element.onload = function() {
        if (!loaded) callback();
        loaded = true;
        --page.loading;
        if (!page.loading) page.parent.trigger('loaded');
      }
      var head = document.getElementsByTagName('head')[0];
      head.appendChild(element);
    },
    
    load_links: function(type, options, callback)
    {
      var links = options[type];
      if (links === undefined || links === null) {
        if (callback !== undefined) callback();
        return;
      }
      if (typeof links === 'string')
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
    
    init_links: function(object, field, types, callback)
    {      
      page.load_links('css', field);
      page.load_links('script', field, function() {
        if (field.create) 
          object.customCreate($.extend({types: types}, page.options, field));
        if (callback !== undefined) callback();
      });      
    },
    
    
    
    merge_type: function(field, type)
    {
      if (field === undefined || this.types === undefined) return field;
      if (type === undefined) type = field.type;
      if (type === undefined) return field;
      if (typeof type === 'string') type = this.merge_type(this.types[type]);
      return merge(type, field);
    },
   
    get_type_html: function(type)
    {
      if ($.isPlainObject(type)) return type;
      if (type.search(/\W/) >= 0) return {html: type};
      return this.merge_type(this.types[type]);
    },
    
    get_template_html: function(template, item)
    {
      if (item.type === 'hidden' || item.template === "none") return '$field';
      var result = template;
      if (typeof template !== 'string') {
        item = $.extend({}, template, item);
        assert(template.html);
        result = template.html;
      }
      if (!$.isPlainObject(item)) return result;
      var matches = getMatches(result, /\$(\w+)/g);
      for (var i in matches) {
        var code = matches[i];
        if (code === 'field') continue;
        var value = item[code] || '';          
        result = result.replace('$'+code, value);
      }
      return result;
    },
    
    append_sub_page: function(parent, regex, template, sub_page)
    {
      var tmp = $('<div></div>');
      var object = tmp.page($.extend({}, options.values, options, sub_page));
      var templated = this.get_template_html(template, sub_page.fields);
      parent.replace(regex, templated+'$1'); 
      this.replace(parent, object, object.attr('id'), 'field');
    },
    
    set_defaults: function(defaults, item)
    {
      var names = [ 'type', 'template', 'mandatory', 'action', 'attr' ];
      var set = false;
      for (var i in names) {
        var name = names[i];
        var value = item[name];
        if (value === undefined) continue;
        if (name === 'template' && value === 'none')
          defaults[name] = '$field';
        else if (name === 'type' || name === 'template')
          defaults[name] = this.get_type_html(item[name]);
        else
          defaults[name] = item[name];
        set = true;
      }
      return set;
    },
    
    append_contents: function(parent, parent_field, name, items, types, defaults)
    {
      if (!defaults) defaults = { template: "$field" };
      var loading_data = false;
      var regex = new RegExp('(\\$'+name+')');
      var new_item_name = '_new_'+name;
      var new_item_html = '<div id="'+new_item_name+'"></div>';
      var path = parent_field.path+'/'+name;
      for(var i in items) {
        var item = items[i];
        var id;
        var array;
        if ($.isPlainObject(item)) {
          if (this.set_defaults(defaults, item)) continue;          
          
          if (item.page !== undefined) {
            item.page.key = this.options.key;
            this.append_sub_page(parent, regex, defaults.template, item.page);
            continue;
          }

          
          if (item.code === undefined) {
            for (var key in item) {
              if (!item.hasOwnProperty(key)) continue;
              id = key;
              break;
            };
            assert(item[id], "Invalid item " + JSON.stringify($.copy(item))); 
            item = item[id];
            if (item.type === 'page') {
              this.append_sub_page(parent, regex, defaults.template, item);
              continue;
            }            
            if (post_methods.indexOf(id) >= 0) {
              loading_data = true;
              this.load_data(parent, parent_field, name, types, defaults); 
              continue;
            }
          
            if (typeof item === 'string') {
              item = { code: id, name: item };
            }            
            if (!item.action && defaults.action) item.action = defaults.action;
            if (!item.attr && defaults.attr) item.attr = defaults.attr;
            if (!item.type && defaults.type) item = merge(defaults.type, item);
            item = merge(types[id], item);
          }
        }
        else if (typeof item === 'string') {
          if (types[item] !== undefined) {
            id = item;
            item = merge(types[item], defaults.type);
          }
          else {
            if (defaults.type === undefined) continue;  // todo take care of undefined types
            id = item;
            item = $.copy(defaults.type);
          }
          if (defaults.action) item.action = defaults.action;
          if (defaults.attr) item.attr = defaults.attr;
        }
        else if ($.isArray(item)) {
          array = item;
          id = item[0];
          item = defaults;
          item.array = array;
        }
        
        if (path)
          item.path = path + '/' + id;
        parent.replace(regex,new_item_html+'$1');
        var created = this.create(item, id, types, array);        
        item = created[0];
        var obj = created[1];
        var templated = this.get_template_html(item.template || defaults.template, item);
        if (templated === '$field') {
          templated = obj;
        }
        else {
          templated = $(templated);
          this.replace(templated, obj, id, 'field');
        }
        parent.find('#'+new_item_name).replaceWith(templated);
        this.init_events(obj, item);
        if (item.hide || item.show === false) {
          templated.hide();
        }
        templated.on('show_hide', function(event, invoker, condition) {
          $(this).is(':visible')? $(this).hide(): $(this).show();
        });
      }
      if (!loading_data)
        parent.replace(regex, '');
    },
    
    set_attr: function(obj, field)
    {
      var attr = field.attr;
      if (attr) {
        if (typeof attr === 'string') 
          obj.attr(attr,"");
        else $.each(attr, function(key, val) {
          if (field.array) {
            var numeric = getMatches(val, /\$(\d+)/g);
            if (numeric.length) val = field.array[numeric[0]];
          }
          obj.attr(key,val);
        });
      }
      $.each(obj[0].attributes, function(i, attr) {
        var matches = getMatches(attr.value, /\$(\w+)/g)
        for (var j in matches) {
          var value = field[matches[j]];
          if (value === undefined || typeof value !== 'string') continue;
          obj.attr(attr.name, attr.value.replace(/\$(\w+)/g, value));
        }
      });
    },

    set_style: function(obj, field)
    {
      var style = field.style;
      if (!style) return;
      for (var key in style) {
        obj.css(key, style[key]);
      }
    },
    
    replace: function(parent, child, id, field)
    {
      id = id || child.attr('id');
      field = field || id;
      var new_id = "__new__"+id;
      var new_html = "<div id="+new_id+"></div>";
      parent.replace("\\$"+field, new_html);
      parent.find('#'+new_id).replaceWith(child); 
      return child;
    },
    
    create: function(field, id, types)
    {      
      field.code = id;
      field.page_id = this.options.page_id;
      field = this.merge_type(field);
      field.name = field.name || toTitleCase(id.replace(/[_\/]/g, ' '));
      field.key = page.options.key;
      if (!field.array) this.expand_fields(id, field);
      if (!field.html) console.log("No html for ", field);
      assert(field.html, "Invalid HTML for "+id); 
      field.html.replace('$tag', field.tag);
      var obj = $(field.html);
      
      var reserved = ['code','create','css','script','name', 'desc', 'data'];
      this.set_attr(obj, field);
      this.set_style(obj, field);
      var values = $.extend({}, this.globals, types, field);
      var matches = getMatches(obj.html(), /\$(\w+)/g);
      for (var i = 0; i< matches.length; ++i) {
        var code = matches[i];
        var value;
        if (field.array) {
          value = field.array[i+1];
        } 
        else {
          value = values[code];
          if (value === undefined) continue;
          if (typeof value === 'string' && value.search(/\W/) < 0 && reserved.indexOf(code) < 0) {
            value = values[value] || value;
          }
        }
        
        if ($.isArray(value)) {
          if (types[code] !== undefined)
            value = $.merge($.merge([], types[code]), value);
            this.append_contents(obj, field, code, value, types);
            continue;
        }
        
        if (typeof value === 'string') {
          obj.replace("\\$"+code, value);
          this.globals[code] = value;
          continue;
        }

        value.path = field.path+'/'+code;
        var result = this.create(value, code, types);
        this.replace(obj, result[1], code);
      }
     
      page.init_links(obj, field, types);
      obj.on('loaded', function() {
        page.init_events(obj, field);
      });
      return [field, obj];
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
      data.fields.path = data.path;
      var result = page.create(data.fields, this.id, data.types);
      var object = result[1];
      page.object = object;
      data.fields = result[0];
      data.values = values;
      assert(object !== undefined, "Unable to create page "+this.id);
      object.addClass('page').appendTo(parent);   
      parent.trigger('read_'+this.id, [object, data.fields]);
      
      if (!page.loading)
        page.set_values(object, data);
      else parent.on('loaded', function() {
        page.set_values(object, data);
      });
      object.on('child_action', function(event,  obj, options) {
        event.stopImmediatePropagation();
        page.accept(event, obj, options);
      });
      
      object.on('read_values', function(event, result) {
        for (var i in result) {
          object.setChildren(result[i]);
        }
      });
      
      object.on('reload', function() {
        page.load_values(object, data);
      });
      
      
      object.on('create_child', function(event, field, parent) {
        if (parent === undefined) parent = event.trigger;
        var result = page.create(field, field.code, page.types);
        var child = result[1];
        child.appendTo(parent);
        child.value(field.value);
      });
      var children = object.find("*");
      children.on('show', function(e, invoker,show) {
        if (show === undefined) return false;
        $(this).toggle(show);
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
    },
    
    trigger: function(field, invoker)
    {
      if (!field.event) {
        console.log("WARNING: no event defined for field", field);
        return;
      }
      var sink;
      var event = field.event;
      var params;
      if ($.isPlainObject(event)) {
        sink = event.sink;
        this.expand_fields(field.code, event.parameters);
        params = event.parameters;
        event = event.name;
      }
      else {
        sink = field.sink;
        params = field.params;
      }
      
      if (sink)
        sink = $(sink.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+field.page_id+"$2"));
      else
        sink = invoker;

      sink.trigger(event, [invoker, params]);
    },
    
    load_data: function(object, field, name, types, defaults) 
    {
      
      page.loading++;
      object.on('loaded', function(event,result) {
        if (--page.loading === 0)
          page.parent.trigger('loaded', result);
        if (result === undefined || result === null) {
          console.log('No page data result for object: ', page.object, ' field ', id);
          return;
        }
        if (defaults.attr === undefined) defaults.attr = {};
        defaults.attr.loaded = '';
        
        if (object.find('[loaded]').exists()) {
          object.find('[loaded]').replaceWith('$'+name);
        }
        page.append_contents(object, field, name, result, types, defaults);
        if (page.loading === 0)
          page.parent.trigger('loaded', result);
      });
      if (field.autoload || field.autoload === undefined) {
        $.json('/', page.server_params('data', field.path+'/'+name), function(result) {
          page.respond(result, object);
          object.trigger('loaded', [result]);
        });
      }
      
      object.on('reload', function() {
        field.autoload = true;
        page.load_data(object, field, name, types, defaults);
        if (field.values)
          page.load_values(object, field);
      })
    },
    
    
    init_events: function(obj, field)
    {
      if (!field.action) return;
      field.page_id = options.page_id;
      obj.click(function(event) {
        page.accept(event, $(this), field);
      });      
    },
    
    set_values: function(parent, data)
    {
      var set = function(values) {
        for (var i in values) {
          var item = values[i];
          var array = $.isNumeric(i);
          if (array && !$.isPlainObject(item)) continue;
           var id = i, value= item;
          if (array) {
            for (var key in item) {
              if (!item.hasOwnProperty(key)) continue;
              id = key;
              value = item[key];
              break;
            };
          }
          var obj = parent.find('#'+id);
          if (obj.exists()) {
            obj.value(value);
            continue;
          }
          if (post_methods.indexOf(id) > 0) 
            page.load_values(parent, data);
        }
      };
      set(this.options.request);
      set(data.values);
    },
    
    load_values: function(parent, data)
    {
      $.json('/', this.server_params('values', data.path+'/values'), function(result) {
        parent.trigger('loaded_values', [result]);
        page.respond(result);
        if ($.isPlainObject(result))
          parent.setChildren(result);
        else for (var i in result) {
          parent.setChildren(result[i]);
        }
      });
    },
            
    accept: function(event, obj, field)
    {
      var action = field.action;
      field.page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
      switch(action) {
        case 'dialog': page.showDialog(field.url, {key: field.key}); return;
        case 'redirect':
          var url = field.url;
          if (!url) {
            url = '/?action=action';
            for (var key in field) {
              if (key === 'action') continue;
              url += '&'+key+'='+encodeURIComponent(field[key]);
            }
          }
          if (field.target)
            window.open(url, field.target);
          else
            document.location = url;
          break;
        case 'post':
          var params = this.server_params('action', field.path, {key: field.key});
          var selector = field.selector;
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+field.page_id+"$2");
            obj.jsonCheck(event, selector, '/', params, function(result) {
              if (result === null) result = undefined;
              obj.trigger('processed', [result]);
              page.respond(result, obj);
            });
            break;
          }
          $.json('/', params, function(result) {
            obj.trigger('processed', [result]);
            page.respond(result, obj);
          });
          break;
        case 'trigger':
          page.trigger(field, obj);
          break;
        default:
          if (field.url)
            document.location = field.url.replace('$key', field.key); 
      }
    },
    
    
    
    
    respond: function(result, invoker)
    {
      if (!result) return this;
      var responses = result._responses;
      if (!$.isPlainObject(responses)) return this;
      var parent = this.parent;
      if (invoker) {
         parent = invoker.parents('#'+this.options.page_id).eq(0);
        if (!parent.exists()) parent = this.parent;
      }
      else invoker = parent;
      
      var self = this;
      var handle = function(action, val)
      {
        console.log("response", action, val);
        switch(action) {
          case 'alert': alert(val); break;
          case 'show_dialog': self.showDialog(val, responses.options); break;
          case 'close_dialog': self.closeDialog(parent, val); break;
          case 'redirect': location.href = val; break;
          case 'update': 
            parent.setChildren(val); break;
          case 'trigger':
            var event = val.event;
            var sink = parent;
            if (val.sink) {
              sink = $(val.sink.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+self.id+"$2"));
            }
            var args = val.args || [];
            sink.trigger(event, [invoker, args[0], args[1], args[2]]);
            break;
        }
      }

      for (var key in responses) {
        var val = responses[key];
        if (!$.isArray(val))
          handle(key, val);
        else for (var i in val) 
          handle(key, val[i]);      
      }
      return this;
    },

    showDialog: function(path, field)
    {
      // expecting a value not an array, if array given take the last value
      // bug or inconsistent implentation of array_merge_recursive
      if ($.isArray(path)) path = path[path.length-1];
      
      if (path[0] === '/') path = path.substr(1);
      var params = { path: path };
      if (field) {
        params.values = field.values;
        params.key = field.key;
      }
      var tmp = $('body');
      var id = path.replace('/','_');
      tmp.on('read_'+id, function(event, object, options) { 
        object.attr('title', options.name);
        options = $.extend({modal:true, page_id: id, close: function() {
          $(this).dialog('destroy').remove();
        }}, options);
        object.dialog(options);
      });
      tmp.page(params);
    },
    
    closeDialog: function(dialog, message)
    {
      if (message) alert(message);
      if (!dialog.hasClass('ui-dialog-content')) 
        dialog = dialog.parents('.ui-dialog-content').eq(0);
      dialog.dialog('destroy').remove();
    }
        
  };
  options.fields && page.show(options) || page.load();
  return page.object;
}


