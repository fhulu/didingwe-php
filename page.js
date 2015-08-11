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
      this.parent.on('server_response', function(event, result) {
        page.respond(result);
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
        source = '#'+source+' #'+parent_id+',[name='+source+'] #'+parent_id;
      return $(source).value();
    },

    expand_value: function(values,value,parent_id)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string' && value !== null) {
          subst = page.expand_function(subst, parent_id)
          value = value.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), subst+'$1');
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
        value = value.replace('$id', parent_id);
        data[field] = page.expand_value(data, value, parent_id);
      });
      return data;
    },

    expand_array: function(item)
    {
      $.each(item, function(key, value) {
        var matches = getMatches(value, /\$(\d+)/g);
        for (var i in matches) {
          var index = parseInt(matches[i]);
          value = value.replace(new RegExp("\\$"+index+"([^\d]?)", 'g'), item.array[index-1]+'$1');
        }
        item[key] = value;
      });
    },

    remove_subscripts: function(item)
    {
      var removed = [];
      $.each(item, function(key, value) {
        if (typeof value !== 'string') return;
        if (value.match(/^\$\d+$/)) removed.push(key);
      })
      $.deleteKeys(item, removed);
      return item;
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

    init_links: function(object, field, callback)
    {
      page.load_links('css', field);
      page.load_links('script', field, function() {
        if (field.create)
          object.customCreate($.extend({types: page.types}, page.options, field));
        if (callback !== undefined) callback();
      });
    },



    merge_type: function(field, type)
    {
      if (field === undefined || this.types === undefined) return field;
      if (type === undefined) type = field.type;
      if (type === undefined && field.html === undefined && field.tag) type = 'control';
      if (type === undefined) return field;
      if (typeof type === 'string') type = this.merge_type(this.types[type]);
      return merge(type, field);
    },

    merge_types: function()
    {
      var self = this;
      $.each(this.types, function(key, value) {
        self.types[key] = self.merge_type(value);
      })
    },

    expand_type: function(type, set_class)
    {
      if ($.isPlainObject(type)) return type;
      if (type.search(/\W/) >= 0) return {html: type};
      var field = {};
      if (set_class) field.class = [type.replace('_','-')];
      return this.merge_type(field, type);
    },

    get_template: function(template, item)
    {
      if (template === undefined || item.type === 'hidden' || item.template === "none" || template == '$field') return '$field';
      var field = $.copy(this.merge_type(item));
      if (typeof template === 'string') {
        template = {html: template};
      }
      else {
        template = this.merge_type(template);
        $.deleteKeys(field, ['type', 'attr', 'class', 'tag', 'html', 'style', 'create']);
      }
      template = merge(field, template);
      return this.create(template)[1];
    },

    create_sub_page: function(field)
    {
      var tmp = $('<div></div>');
      field.path = field.url? field.url: field.id;
      field.sub_page = undefined;
      tmp.page(field);
      tmp.on('read_'+field.id, function(e, obj) {
        if (field.style)
          page.set_style(obj, field);
        var parent = obj.parents().eq(0);
        parent.replaceWith(obj);
      });
      return tmp;
    },

    promote_attr: function(field)
    {
      attr = field.attr;
      if (attr === undefined || $.isPlainObject(attr)) return;
      var val = {};
      val[attr] = attr;
      field.attr = val;
    },

    set_defaults: function(defaults, item)
    {
      var names = [ 'type', 'template', 'mandatory', 'action', 'attr', 'wrap'];
      var set = false;
      for (var i in names) {
        var name = names[i];
        var value = item[name];
        if (value === undefined) continue;
        if (name === 'template' && value === 'none')
          defaults[name] = '$field';
        else if (name === 'wrap' && $.isPlainObject(value))
          defaults[name] = $.extend({}, {tag: 'div'}, value);
        else if (name === 'type' || name === 'template' || name === 'wrap')
          defaults[name] = this.expand_type(value, name==='template');
        else
          defaults[name] = item[name];
        set = true;
      }
      this.promote_attr(defaults);
      return set;
    },

    append_contents: function(parent, parent_field, name, items, defaults)
    {
      if (!defaults) defaults = { template: "$field" };
      var loading_data = false;
      var regex = new RegExp('(\\$'+name+')');
      var new_item_name = '_new_'+name;
      var new_item_html = '<div id="'+new_item_name+'"></div>';
      var path = parent_field.path+'/'+name;
      var parent_is_table = ['table','tr'].indexOf(parent_field.tag) >= 0;
      var pushed = [];
      var first;
      var last;
      var wrap;
      for(var i in items) {
        var item = items[i];
        var id;
        var array;
        var template;
        if (typeof item==='string') item = $.toObject(item);
        if ($.isPlainObject(item)) {
          if (this.set_defaults(defaults, item)) continue;
          if (item.id === undefined) {
            var a = $.firstElement(item);
            id = a[0];
            item = a[1];
          }
          if (item.merge) continue;
          if (id == 'query') {
            loading_data = true;
            this.load_data(parent, parent_field, name, defaults);
            continue;
          }
          if (typeof item === 'string') {
            item = { name: item };
          }
          if (!item.action && defaults.action) item.action = defaults.action;
          this.promote_attr(item);
          if (defaults.attr) item.attr = merge(item.attr,defaults.attr);
          template = item.template;
          var has_type = item.type !== undefined;
          item = merge(this.types[id], item);
          if (!has_type && defaults.type) item = merge(defaults.type, item);
        }
        else if ($.isArray(item)) {
          array = item;
          id = item[0];
          item = defaults;
          item.array = array;
        }
        item.id = id;
        if (path)
          item.path = path + '/' + id;
        if (!template) template = item.template = defaults.template;
        if (template && template.subject) item = merge(template.subject, item);
        parent.replace(regex,new_item_html+'$1');
        var created = this.create(item, parent_field);
        item = created[0];
        var obj = created[1];
        var templated = this.get_template(template, item);
        if (templated === '$field') {
          templated = obj;
        }
        else {
          templated.attr('for', id);
          this.replace(templated, obj, id, 'field');
        }
        if (item.push)
          pushed.push([item.push,templated]);
        else if (wrap)
          wrap.append(templated);
        else if (defaults.wrap) {
          defaults.wrap.id = name;
          wrap = this.create(defaults.wrap, parent_field)[1];
          wrap.html('');
          delete defaults.wrap;
          parent.find('#'+new_item_name).replaceWith(wrap);
          wrap.append(templated);
        }
        else
          parent.find('#'+new_item_name).replaceWith(templated);
        this.init_events(obj, item);
        if (item.hide || item.show === false) {
          templated.hide();
        }
        templated.on('show_hide', function(event, invoker, condition) {
          $(this).is(':visible')? $(this).hide(): $(this).show();
        });
        if (!first) first = templated;
        last = templated;
      }
      for (var i in pushed) {
        var pop = pushed[i];
        var pos = pop[0];
        var templated = pop[1];
        if (pos === 'first' && templated !== first)
          templated.insertBefore(first);
        else if (pos === 'last' && templated !== last)
          templated.insertAfter(last);
        else
          templated.insertBefore(parent.find('[for="'+pos+'"'));
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
          obj.attr(attr.name, attr.value.replace(/\$\w+([\b\W]|$)/g, value+'$1'));
        }
      });
      if (obj.attr('id') === '') obj.removeAttr('id');
    },

    set_class: function(obj, field)
    {
      var cls = field.class;
      if (cls === undefined) return;
      if (typeof cls === 'string') cls = [cls];
      for (var i in cls) {
        obj.addClass(cls[i]);
      }
    },


    set_style: function(obj, field)
    {
      var style = field.style;
      if (!style) return;
      obj.css(field.style);
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

    inherit_parent: function(parent, field)
    {
      if (!parent || !parent.inherit) return field;
      for (var i in parent.inherit) {
        var key = parent.inherit[i];
        var inherited = {};
        inherited[key] = parent[key];
        field = merge(inherited, field);
      }
      return field;
    },

    init_field: function(field, parent)
    {
      field.page_id = this.options.page_id;
      field = this.merge_type(field);
      field = this.inherit_parent(parent, field);

      var id = field.id;
      field.key = this.options.key;
      if (field.array)
        this.expand_array(field);
      else
        field = this.remove_subscripts(field);
      if (id && field.name === undefined)
        field.name = toTitleCase(id.replace(/[_\/]/g, ' '));
      this.expand_fields(id, field);
      return field;
    },

    create: function(field, parent)
    {
      field = this.init_field(field, parent);
      if (field.sub_page)
        return [field, page.create_sub_page(field)];

      var id = field.id;
      if (!field.html) console.log("No html for ", id, field);
      assert(field.html, "Invalid HTML for "+id);
      field.html = field.html.replace(/\$tag(\W)/, field.tag+'$1');
      var obj = $(field.html);
      var reserved = ['id', 'create', 'css', 'script', 'name', 'desc', 'data'];
      this.set_attr(obj, field);
      this.set_class(obj, field);
      this.set_style(obj, field);
      var values = $.extend({}, this.globals, this.types, field);
      var matches = getMatches(field.html, /\$(\w+)/g);
      for (var i = 0; i< matches.length; ++i) {
        var code = matches[i];
        var value;
        if (field.array) {
          if (!field.array.length) continue;
          value = field.array[i];
        }
        else {
          value = values[code];
          if (value === undefined) continue;
          if (typeof value === 'string' && value.search(/\W/) < 0 && reserved.indexOf(code) < 0) {
            value = values[value] || value;
          }
        }

        if ($.isArray(value)) {
          if (this.types[code] !== undefined)
            value = $.merge($.merge([], this.types[code]), value);
          this.append_contents(obj, field, code, value);
          continue;
        }

        if (typeof value === 'string') {
          obj.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), value+'$1');
          this.globals[code] = value;
          continue;
        }

        value.path = field.path+'/'+code;
        value.id = code;
        var result = this.create(value, field);
        this.replace(obj, result[1], code);
      }
      if (obj.attr('id') === '') obj.removeAttr('id');
      page.init_links(obj, field);
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
      if (data.fields.name === undefined)
        data.fields.name = toTitleCase(data.path.split('/').pop().replace('_',' '));
      data.fields.path = data.path;
      data.fields.sub_page = false;
      data.fields.id = this.id;
      var result = page.create(data.fields);
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
        var result = page.create(field);
        var child = result[1];
        child.appendTo(parent);
        child.value(field.value);
      });
      var children = object.find("*");
      children.on('show', function(e, invoker,show) {
        if (show === undefined) return false;
        $(this).toggle(parseInt(show) === 1 || show === true);
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
        this.expand_fields(field.id, event.parameters);
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

    load_data: function(object, field, name, defaults)
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
        page.append_contents(object, field, name, result, defaults);
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
        page.load_data(object, field, name, defaults);
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
      var query_values;
      for (var i in data.values) {
        var item = data.values[i];
        var array = $.isNumeric(i);
        if (array && !$.isPlainObject(item)) continue;
         var id = i, value= item;
        if (array) {
          var el = $.firstElement(item);
          id = el[0];
          item = el[1];
        }
        var obj = parent.find('#'+id+',[name="',+id+'"');
        if (obj.exists()) {
          obj.value(value);
          continue;
        }
        if (id === "query") query_values = true;
      }
      if (query_values)
        page.load_values(parent, data);
    },

    load_values: function(parent, data)
    {
      $.json('/', this.server_params('values', data.path), function(result) {
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
      var confirmed = function() {
        var action = field.action;
        field.page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
        switch(action) {
          case 'dialog': page.showDialog(field.url, {key: field.key}); return;
          case 'redirect':
            var url = field.url;
            if (url === undefined && field.query) {
              url = '/?action=action';
              var exclude = ['action','query', 'id', 'page_id', 'name','desc', 'tag','type','html','text','user_full_name']
              for (var key in field) {
                if (exclude.indexOf(key) === 0) continue;
                url += '&'+key+'='+encodeURIComponent(field[key]);
              }
            }
            if (url) {
              if (field.target === '_blank')
                window.open(url, field.target);
              else if (field.target) {
                var target = $(field.target);
                var parent = target.parent();
                if (url[0] === '/') url = url.substr(1);
                parent.page({path: url});
                var id = url.replace('/','_');
                parent.on('read_'+id, function() {
                  target.remove();
                });
              }
              else
                document.location = url;
            }
            break;
          case 'post':
            var params = page.server_params('action', field.path, {key: field.key});
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
              obj.trigger('straight processed', [result]);
              page.respond(result, obj);
            });
            break;
          case 'trigger':
            page.trigger(field, obj);
            break;
          default:
            if (field.url)
              document.location = field.url.replace(/\$key(\b|\W|$)?/, field.key+"$1");
        }
      }
      if (field.confirmation) {
        page.showDialog('/confirm_dialog', {}, function() {
          $('#confirm_dialog #synopsis').text(field.confirmation);
          $('#confirm_dialog .action').click(function() {
            if ($(this).attr('id') === 'yes') confirmed();
            $('#confirm_dialog').dialog('close');
          })
        });
      }
      else confirmed();
    },




    respond: function(result, invoker)
    {
      if (!result) return this;
      var responses = result._responses;
      if (!$.isPlainObject(responses)) return this;
      var parent = page.object;
      if (invoker) {
         parent = invoker.parents('#'+this.options.page_id).eq(0);
        if (!parent.exists()) parent = page.object;
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

    showDialog: function(path, field, callback)
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
      tmp.page(params);

      var id = path.replace('/','_');
      tmp.on('read_'+id, function(event, object, options) {
        object.attr('title', options.name);
        options = $.extend({modal:true, page_id: id, close: function() {
          $(this).dialog('destroy').remove();
        }}, options);
        object.dialog(options);
        if (callback) callback();
      });
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
