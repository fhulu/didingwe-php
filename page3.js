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
    load: function() 
    {
      $.json('/?a=page3/run', { data: options}, this.show);
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
    
    expand_fields: function(parent_id, data, known)
    {
      known = known === undefined? data: page.inherit(known, data);
      $.each(data, function(field, value) {
        if (typeof value === 'string' && value.indexOf('$') >= 0 && field !== 'template' && field != 'attr') {
          value = value.replace('$code', parent_id);
          data[field] = page.expand_value(known, value);
        }
        else if ($.isPlainObject(value)) {
          data[field] = page.expand_fields(field, value, known);
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
    
    inherit: function(parent, child)
    {
      var reserved = ['html','code','template','create','css','script','load', 'data'];
      var result = $.extend({}, child);
      $.each(parent, function(key, value) {
        if (typeof value !== "string" || reserved.indexOf(key)>=0) return;
        if (result[key] !== undefined) return;
        result[key] = value;
      });
      return result;
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
          var create_opts = $.extend({key: page.options.key}, options);
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
    
    create_list: function(parent, items, types)
    {
      
    },
    
    create_object: function(parent, id, fields, types)
    {
      var type = page.get_type(id, fields, types);
      var template = page.get_template(type, fields, types);
      var html = type.html===undefined?'<div></div>': type.html;
      
      if (template !== undefined) {
        
      }
      $.each(fields, function(code, value) {
        
      })
    },
    
    merge: function(a1, a2)
    {
      var is_array=$.isArray(a1);
      var r = is_array? []: {};
      for (var i in a1) {
        if (a1.hasOwnProperty(i)) r[i] = a1[i];
      }
      for (var i in a2) {
        if (!a2.hasOwnProperty(i)) continue;
        var v2 = a2[i];
        if (!a1.hasOwnProperty(i)) {
          r[i] = v2;
          continue;
        }
        var v1 = a1[i];
        if (typeof v1 !== typeof v2 
                || $.isArray(v1) && !$.isArray(v2) 
                || $.isPlainObject(v1) && !$.isPlainObject(v2)) {
          r[i] = v2;
          continue;
        }
          
        if ($.isArray(v1)) {
          r[i] = $.merge( $.merge([], v1), v2);
          //note: no deep copying arrays, only objects
          continue;
        }
        if ($.isPlainObject(v1)) 
          r[i] = page.merge(v1, v2);
        else 
          r[i] = v2;
      }
      return r;
    },
    
    merge_type: function(field, types)
    {
      var type = field.type;
      if (type === undefined) return field;
      var super_type = this.merge_type(types[type], types);
      return this.merge(super_type, field);
    },
   
    get_template: function(type, fields, types)
    {
      var type = page.get_type(id, fields, types);
      if (type.template !== undefined) 
      return type.template.html===undefined? html: type.template.html;
    },
    
    get_list: function()
    {
      
    },
    
    get_html: function(id, field, types)
    {
      field = page.merge_type(field, types);
      var html = field.html;
      console.log("before", id, html);
      if (html === undefined) return undefined;
      var reserved = ['code','template','create','css','script','load', 'data'];
      var values = $.extend({}, types, field);
      var regex = /\$(\w+)/g;
      for (var match = regex.exec(html); match !== null; match = regex.exec(html)) {
        var code = match[1];
        var value = code === 'code'? id: values[code];
        console.log("processing", code, value);
        if (parseInt(value) === NaN && code !== 'code') {
          var val = values[value];
          if (val === undefined) value = val;
        }

        if ($.isArray(value)) {
          value = this.get_list(value, types);
        }
        else if ($.isPlainObject(value)) {
          value = this.get_html(code, value, values);
        }
          
        html = html.replace("$"+code, value);
      }
      console.log("processed", code, html);
      return html;
    },
    
    show: function(data)
    {
      var id = options.object;
      page.get_html(id, data.fields, data.types);
      return;
      var key = options.key;
      data = page.expand_fields(id,data);
      page.expand_children(data);
      var parent = page.parent;
      var object = $(data.html).addClass('page').appendTo(parent);
      object.on('loaded', function() {
        page.set_options(parent, id, data,function(){
          var options = {};
          options[id] = data;
          page.init_children(parent, options, key);
          parent.trigger('read_'+id, [object,data]);
        });
      });
      object.on('child_action', function(event,  obj, options) {
        page.accept(event, obj, options);
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
        var params = {_page: id, _field:field_id, key: self.options.key};
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

    init_children: function(parent, data, key)
    {
      $.each(data, function(field, options) {
        if (!$.isPlainObject(options)) return;
        var obj = parent.find('#'+field);
        if (options.key !== undefined) key = options.key;
        if (!obj.exists()) {
          page.init_children(parent, options, key);
          return;
        }
        if (!obj.hasAttr('_bound')) { 
          obj.attr('_bound','');
          obj.click(function(event) {
            page.accept(event, $(this),  
              {key:key, code:field, action: options.action, selector: options.selector});
          });
        }
        if (options.load !== undefined) {
          obj.loadChildren('/?a=page/load', {data: {page: field, key: key } } );
        }
        page.init_children(obj, options, key);
      });
    },
    
    accept: function(event, obj, options)
    {
      var action = options.action;
      if (action === undefined) return;
      var key = options.key;
      var selector = options.selector;
      if (action.indexOf('dialog:') === 0) {
        page.showDialog(action.substr(7), {key: key});
      }
      else if (action.indexOf('url:') === 0) {
        document.location = action.substr(4);
      }
      else if (action === '') {
        var page_id = obj.parents('[id]').eq(0).attr('id');
        var data = $.extend({ _page: page_id, _field: options.code }, options);
        if (selector !== undefined) {
          selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+page_id+"$2");
          obj.jsonCheck(event, selector, '/?a=page/action', { data: data }, function(result) {
            if (result === null) result = undefined;
            obj.trigger('processed', [result]);
            if (result !== undefined) page.respond(result._responses, obj);
          });
        } 
        else  $.json('/?a=page/action', {data: data}, function(result) {
          obj.trigger('processed', [result]);
          if (result !== undefined) page.respond(result._responses, obj);
        });
      }
    },
    
    respond: function(responses, invoker)
    {
      if (!$.isPlainObject(responses)) return;
      var parent = invoker.parents('.ui-dialog-content').eq(0);
      if (!parent.exists()) parent = page.parent;
      var self = this;
      $.each(responses, function(key, val) {
        switch(key) {
          case 'alert': alert(val); break;
          case 'show_dialog': self.showDialog(val, responses.options); break;
          case 'close_dialog': self.closeDialog(parent); break;
          case 'redirect': location.href = val; break;
          case 'update': parent.setChildren(val); break;
        }
      });      
    },

    showDialog: function(dialog, options)
    {
      var params = { page: dialog, key: options.key };
      var tmp = $('body').page(params);
      tmp.on('read_'+dialog, function(event, object, options) {  
        options = $.extend({modal:true,  close: function() {
          $(this).dialog('destroy').remove();
        }}, options);
        object.dialog(options);
      });
    },
    
    closeDialog: function(dialog)
    {
      if (dialog.hasClass('ui-dialog-content'))
        dialog.dialog('destroy').remove();
    }
        
  };
  page.load();
  return this;
}


