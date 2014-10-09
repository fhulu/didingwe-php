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
      options.path = "read/"+options.path;
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
    
    expand_fields: function(parent_id, data)
    {
      $.each(data, function(field, value) {
        if (typeof value !== 'string' || value.indexOf('$') < 0 || field === 'template' && field === 'attr') return;
        value = value.replace('$code', parent_id);
        data[field] = page.expand_value(data, value);
      });
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
    
    init_links: function(object, field, callback)
    {      
      page.load_links('css', field);
      page.load_links('script', field, function() {
        if (field.create)
          object.customCreate(field);
        if (callback !== undefined) callback();
      });      
    },
    
    
    merge: function(a1, a2)
    {
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
          r[i] = page.merge(v1, v2);
        else 
          r[i] = v2;
      }
      return r;
    },
    
    merge_type: function(field, types, type)
    {
      if (field == undefined) return field;
      if (type === undefined) type = field.type;
      if (type === undefined) return field;
      var super_type = this.merge_type(types[type], types);
      return this.merge(super_type, field);
    },
   
    get_type_html: function(type, types)
    {
      if ($.isPlainObject(type)) return type;
      if (type.search(/\W/) >= 0) return {html: type};
      return this.merge_type(types[type], types);
    },
    
    get_template_html: function(template, item)
    {
      if (item.type === 'hidden') return '$field';
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
            
            if (id === 'sql' || id === 'call') {
              loading_data = true;
              this.load_data(parent, parent_id, name, [{type:type},{template:template}], types, path); 
              continue;
            }
            if (item.type !== undefined && type)
              item = this.merge(type, item);
            item = this.merge(types[id], item[id]);
          }
        }
        else if (typeof item === 'string') {
          if (types[item] !== undefined) {
            id = item;
            item = this.merge(type, types[item]);
          }
          else {
            if (type === undefined) continue;  // todo take care of undefined types
            id = item;
            item = $.copy(type);
          }
        }
        
        if (path)
          item.path = path + '/' + id;
        var created = this.create(item, id, types, type);        
        var templated = this.get_template_html(template, created[0]);
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
        field = this.merge(type, field);
      
      field.code = id;
      field = page.merge_type(field, types);
      field.name = field.name || toTitleCase(id.replace(/[_\/]/g, ' '));
      field.key = page.options.key;
      if (!array) this.expand_fields(id, field);
      var obj = $(field.html);
      assert(obj.exists(), "Invalid HTML for "+id); 
      var reserved = ['code','create','css','script','name', 'desc', 'data'];
      this.set_attr(obj, field, id);
      this.set_style(obj, field);
      var values = $.extend({}, types, field);
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
           this.append_contents(obj, id, code, value, values, field.path+'/'+code);
           continue;
        }
        
        if (typeof value === 'string') {
          obj.replace("\\$"+code, value);
          continue;
        }

        value.path = field.path+'/'+code;
        var result = this.create(value, code, values);
        this.replace(obj, result[1], code);
      }
     
      page.init_links(obj, field);
      obj.on('loaded', function() {
        page.init_events(obj, field);
      });
      return [field, obj];
    },
    
    show: function(data)
    {
      var parent = page.parent;
      this.id = data.path.replace('/','_');
      data.fields.path = data.path;
      var result = page.create(data.fields, this.id, data.types);
      var object = result[1];
      data.fields = result[0];
      assert(object !== undefined, "Unable to create page "+this.id);
      object.addClass('page').appendTo(parent);      
      parent.trigger('read_'+this.id, [object, data.fields]);
      if (!page.loading)
        page.set_values(object, data);
      else parent.on('loaded', function() {
        page.set_values(object, data);
      });
      object.on('child_action', function(event,  obj, options) {
        page.accept(event, obj, options);
      });
      this.object = object;
      return this;
    },
    
    load_data: function(object, id, name, items, types, path) 
    {
      var params = {path: 'data/'+path, key: page.options.key};
      page.loading++;
      $.json('/?a=page3/run', {data:params}, function(result) {
        if (result === undefined || result === null) {
          console.log('No page data result for object: ', page.object, ' field ', id);
          return;
        }
        result = $.merge(items, result);
        page.append_contents(object, id, name, result, types, path);
        object.trigger('loaded', result);
        --page.loading;
        if (page.loading === 0)
          page.parent.trigger('loaded', result);
      });
    },
    
    
    init_events: function(obj, field)
    {
       if (!field.post && !field.dialog && !field.redirect && !field.validate && !field.call && !field.url)
        return;
      obj.click(function(event) {
        page.accept(event, $(this), field);
      });
    },

    set_values: function(parent, data)
    {
      var values = data.fields.values;
      if (!values) return;
      for (var i in values) {
        var item = values[i];
        if (!$.isPlainObject(item)) continue;
        var id, value;
        for (var key in item) {
          if (!item.hasOwnProperty(key)) continue;
          id = key;
          value = item[key];
          break;
        };
        var obj = parent.find('#'+id);
        if (obj.exists()) {
          obj.val(value);
          continue;
        }
        if (id !== 'sql' && id !== 'call') continue;
        var data = { path: 'values/'+data.path+'/values', key: data.fields.key } 
        $.json('/?a=page3/run', {data: data }, function(result) { 
          for (var i in result) {
            parent.setChildren(result[i]);
          }
        });
      }
    },
    
    accept: function(event, obj, field)
    {
      if (field.dialog) {
        page.showDialog(field.dialog, {key: field.key});
        return;
      }
      if (field.redirect || field.url) {
        console.log(field);
        document.location = field.redirect || field.url;
        return;
      }
      
      var data = {path: 'action/'+field.path, key: field.key };
      if (field.post) {
        var selector = field.post.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+this.id+"$2");
        obj.jsonCheck(event, selector, '/?a=page3/run', { data: data }, function(result) {
          if (result === null) result = undefined;
          obj.trigger('processed', [result]);
          if (result !== undefined) page.respond(result._responses, obj);
        });
        return;
      }
      $.json('/?a=page3/run', {data: data}, function(result) {
        obj.trigger('processed', [result]);
        if (result !== undefined) page.respond(result._responses, obj);
      });
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

    showDialog: function(path, field)
    {
      var params = { path: path, key: field.key };
      var tmp = $('body').page(params);
      tmp.on('read_'+path, function(event, object, options) { 
        object.attr('title', options.name);
        options = $.extend({modal:true, close: function() {
          $(this).attr('title', options.name).dialog('destroy').remove();
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
  options.fields && page.show(options) || page.load();
  return page.object;
}


