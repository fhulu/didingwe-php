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
    
    merge: function(a1, a2, stack)
    {
      if (stack === undefined)
        stack = 0;
      else if (stack == 10) {
        console.log("overflow merge ", a1, at)
        return field;
      }
      if (a1 === undefined || a1 === null) return a2;
      if (a2 === undefined || a2 === null) return a1;
      var r = $.copy(a1);
      for (var i in a2) {
        if (!a2.hasOwnProperty(i)) continue;
        var v2 = a2[i];
        if (!a1.hasOwnProperty(i)) {
          r[i] = v2;
          continue;
        }
        var v1 = r[i];
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
          r[i] = page.merge(v1, v2, ++stack);
        else 
          r[i] = v2;
      }
      return r;
    },
    
    merge_type: function(field, types, type)
    {
      if (type === undefined) type = field.type;
      if (type === undefined) return field;
      var super_type = this.merge_type(types[type], types, undefined);
      return this.merge(super_type, field);
    },
   
    get_type_html: function(type, types)
    {
      if (type.search(/\W/) >= 0) return {html: type};
      return this.merge_type(types[type], types);
    },
    
    replace_template: function(text, code, values)
    {
    },
    
    get_template_html: function(template, item, html, id)
    {
      if (template === undefined || template.html === undefined) return html;
      var result = template.html;
      if ($.isPlainObject(item)) {
       item = $.extend({}, template, item);
        var matches = getMatches(template.html, /\$(\w+)/g);
        for (var i in matches) {
          var code = matches[i];
          if (code === 'field') continue;
          var value = item[code];
          if (code === 'name' && value === undefined)
            value = toTitleCase(id);
          
          if (value === undefined) continue;
            result = result.replace('$'+code, value);
        }
      }
      else 
        html = item;
      if (html != null)
        result = result.replace('$field', html);
      if (id != null)
        result = result.replace('$code', id);
      return result===template.html? undefined: result;
    },
    
    get_list_html: function(items, types)
    {
      var type;
      var template;
      var html;
      var mandatory;
      for(var i in items) {
        var item = items[i];
        if (item === undefined || items === null) continue;
        var item_html;
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
          
          $.each(item, function(key) {
            id = key;
          })
          item = this.merge(types[id], item[id]);
          if (item.type === undefined && type !== undefined) 
            item = this.merge(type, item);

          item_html = this.get_html(id, item, types);
        }
        else if ($.isArray(item)) {
          item_html = this.get_list_html(item, types);
        }
        else if (types[item] !== undefined) {
          id = item;
          item = types[item];
          if (type !== undefined) {
            item = item || $.copy(type);
            if (item.type === undefined && item.html === undefined) 
              item = this.merge(type, item);
          }
          item_html = this.get_html(id, item, types);
        }
        else if (typeof item === 'string') {
          id = item;
          if (type !== undefined) {
            item = $.copy(type);
            item_html = this.get_html(id, item, types);
          }
        }
       
        var result = this.get_template_html(template, item, item_html, id);
        if (result === undefined) continue;
        html = html===undefined? result: html + result;
      };
      return html;
    },
    
    get_attr_html: function(field, html)
    {
      var attr = field.attr;
      if (html == '' || !attr) return html;
      
      var tmp = $(html);
      if (typeof attr === 'string') 
        tmp.attr(attr,"");
      else $.each(attr, function(key, val) {
        tmp.attr(key,val);
      });
      return $('<div>').append(tmp).html();
    },
    
    get_html: function(id, field, types)
    {
      field = page.merge_type(field, types);
      if (field.name === undefined)
        field.name = toTitleCase(id.replace(/_/g, ' '));
      var html = field.html;
      if (html === undefined) return undefined;
      var reserved = ['code','create','css','script','name', 'desc', 'data'];
      var values = $.extend({}, types, field);
      var matches = getMatches(html, /\$(\w+)/g);
      for (var i in matches) {
        var code = matches[i];
        var value = code === 'code'? id: values[code];
        if (typeof value === 'string' && value.search(/\W/) < 0 && reserved.indexOf(code) < 0) {
          var val = values[value];
          if (val !== undefined) value = val;
        }
        
        if ($.isArray(value)) {
          if (types[code] !== undefined)
            value = $.merge($.merge([], types[code]), value);
          value = this.get_list_html(value, values);
        }
        else if ($.isPlainObject(value)) {
          value = this.get_html(code, value, values);
        }
          
        if (value !== undefined)
          html = html.replace("$"+code, value);
      }
      return this.get_attr_html(field, html);
    },
    
    show: function(data)
    {
      var id = options.object;
      var html = page.get_html(id, data.page, data.types);
      var parent = page.parent;
      var object = $(html).addClass('page').appendTo(parent);
      return;
      var data = this.merge(data.types, data.page)
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


