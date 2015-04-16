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
      if (options.path[0] === '/') options.path=options.path.substr(1);
      options.action = 'read';      
      $.json('/', { data: options}, function(result) {
        if (result._responses) page.respond(result._responses);
        result.values = $.extend({}, options.values, result.values ); 
        if (result.path) page.show(result);
      });
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
        if (typeof value !== 'string' || value.indexOf('$') < 0 || field === 'template' && field === 'attr') return;
        value = value.replace('$code', parent_id);
        data[field] = page.expand_value(data, value);
      });
      data['html'] = data['html'].replace(/\$(value|desc)/, '');
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
          object.customCreate($.extend({types: types},field));
        if (callback !== undefined) callback();
      });      
    },
    
    
    
    merge_type: function(field, type)
    {
      if (field == undefined || this.types === undefined) return field;
      if (type === undefined) type = field.type;
      if (type === undefined) return field;
      var super_type = this.merge_type(this.types[type]);
      return merge(super_type, field);
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
      var object = tmp.page(sub_page);
      var templated = this.get_template_html(template, sub_page.fields);
      parent.replace(regex, templated+'$1'); 
      this.replace(parent, object, object.attr('id'), 'field');
    },
    
    append_contents: function(parent, parent_id, name, items, types, path)
    {
      var type;
      var template = "$field";
      var mandatory;
      var action;
      var loading_data = false;
      var regex = new RegExp('(\\$'+name+')');
      for(var i in items) {
        var item = items[i];
        var id;
        if ($.isPlainObject(item)) {
          if (item.type !== undefined) {
            type = this.get_type_html(item.type, types);
            continue;
          }
          if (item.template !== undefined) {
            template = this.get_type_html(item.template, types);
            continue;
          }
          if (item.mandatory !== undefined) {
            mandatory = item;
            continue;
          } 
          if (item.action !== undefined) {
            action = item.action;
            continue;
          } 
          
          if (item.page !== undefined) {
            item.page.key = this.options.key;
            this.append_sub_page(parent, regex, template, item.page);
            continue;
          }
          
          if (item.code === undefined) {
            for (var key in item) {
              if (!item.hasOwnProperty(key)) continue;
              id = key;
              break;
            };
            item = item[id];
            if (id === 'sql' || id === 'call') {
              loading_data = true;
              this.load_data(parent, parent_id, name, [{type:type},{template:template}], types, path); 
              continue;
            }
          
            if (typeof item === 'string') {
              item = { code: id, name: item };
            }            
            if (!item.type && type)
              item = merge(type, item);
            item = merge(types[id], item);
          }
        }
        else if (typeof item === 'string') {
          if (types[item] !== undefined) {
            id = item;
            item = merge(types[item], type);
          }
          else {
            if (type === undefined) continue;  // todo take care of undefined types
            id = item;
            item = $.copy(type);
          }
        }
        
        if (action) item.action = action;
        if (path)
          item.path = path + '/' + id;
        var created = this.create(item, id, types, type);        
        item = created[0];
        var templated = this.get_template_html(item.template || template, item);
        parent.replace(regex, templated+'$1'); 
        var obj = created[1];
        this.replace(parent, obj, id, 'field');
        this.init_events(obj, item);
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
    },
    
    create: function(field, id, types, type)
    {
      var array;
      if ($.isArray(field)) {
        array = field;
        field = type;
        id = array[0];
      }
      else if (type && field.type === undefined) 
        field = merge(type, field);
      
      field.code = id;
      field.page_id = this.options.page_id;
      field = this.merge_type(field);
      field.name = field.name || toTitleCase(id.replace(/[_\/]/g, ' '));
      field.key = page.options.key;
      if (!array) this.expand_fields(id, field);
      var obj = $(field.html);
      assert(obj.exists(), "Invalid HTML for "+id+": "+field.html); 
      var reserved = ['code','create','css','script','name', 'desc', 'data'];
      this.set_attr(obj, field, id);
      this.set_style(obj, field);
      var values = $.extend({}, this.globals, types, field);
      var matches = getMatches(obj.html(), /\$(\w+)/g);
      for (var i = 0; i< matches.length; ++i) {
        var code = matches[i];
        var value;
        if (array) {
          value = array[i+1];
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
           this.append_contents(obj, id, code, value, types, field.path+'/'+code);
           continue;
        }
        
        if (typeof value === 'string') {
          console.log("code-value", code,value);
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
      this.object = object;
      return this;
    },
    
    load_data: function(object, id, name, items, types, path) 
    {
      var params = {action: 'data', path: path, key: page.options.key};
      page.loading++;
      object.on('loaded', function(event,result) {
        result = $.merge(items, result);
        if (--page.loading === 0)
          page.parent.trigger('loaded', result);
        if (result === undefined || result === null) {
          console.log('No page data result for object: ', page.object, ' field ', id);
          return;
        }
        page.append_contents(object, id, name, result, types, path);
        if (page.loading === 0)
          page.parent.trigger('loaded', result);
      });
      $.json('/', {data:params}, function(result) {
        object.trigger('loaded', [result]);
      });

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
      var values = $.extend({}, data.fields.values, data.values);
      if (!values) return;
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
          obj.val(value);
          continue;
        }
        if (array && id !== 'sql' && id !== 'call') continue;
        var params = { action: 'values', path: data.path+'/values', key: data.fields.key } 
        $.json('/', {data: params }, function(result) { 
          for (var i in result) {
            parent.setChildren(result[i]);
          }
        });
      }
    },
    
    accept: function(event, obj, field)
    {
      var action = field.action;
      var data = {action: 'action', key: field.key, path: field.path};
      console.log("accept", field)
      switch(action) {
        case 'dialog': page.showDialog(field.url, {key: field.key}); return;
        case 'target':
          var url = '/?action=action';
          for (var key in field) {
            if (key === 'action') continue;
            url += '&'+key+'='+encodeURIComponent(field[key]);
          }        
          document.location = url;
          break;
        case 'post':
          if (field.post === undefined) {
            console.log("No 'post' values set for 'post' action")
            break;
          }
          var selector = field.post.select || field.post.selector;
          if (selector !== undefined) {
            var page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+page_id+"$2");
            obj.jsonCheck(event, selector, '/', { data: data }, function(result) {
              if (result === null) result = undefined;
              obj.trigger('processed', [result]);
              if (result) page.respond(result._responses, obj);
            });
            break;
          }
          $.json('/', {data: data}, function(result) {
            obj.trigger('processed', [result]);
            if (result !== undefined) page.respond(result._responses, obj);
          });
          break;
        case 'trigger':
          var event = field.event.split('<');
          obj.trigger(event[0], [obj, event[1]]);
          break;
        default:
          if (field.url)
            document.location = field.url.replace('$key', field.key); 
      }
    },
    
    respond: function(responses, invoker)
    {
      if (!$.isPlainObject(responses)) return this;
      var parent = this.parent;
      if (invoker) {
         parent = invoker.parents('.ui-dialog-content').eq(0);
        if (!parent.exists()) parent = this.parent;
      }
      var self = this;
      $.each(responses, function(key, val) {
        console.log("response", key, val);
        switch(key) {
          case 'alert': alert(val); break;
          case 'show_dialog': self.showDialog(val, responses.options); break;
          case 'close_dialog': self.closeDialog(parent); break;
          case 'redirect': location.href = val; break;
          case 'update': 
            parent.setChildren(val); break;
        }
      });      
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
    
    closeDialog: function(dialog)
    {
      if (dialog.hasClass('ui-dialog-content'))
        dialog.dialog('destroy').remove();
    }
        
  };
  options.fields && page.show(options) || page.load();
  return page.object;
}


