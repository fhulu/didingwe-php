mkn.render = function(options)
{
  var me = this
  me.invoker = options.invoker;
  var types = me.types = options.types;
  me.id = options.id;
  me.options = options;
  me.sink = undefined;
  me.known = {};

  var array_defaults = [ 'type', 'template', 'action', 'attr', 'wrap'];

  this.mergeType = function(field, type, id)
  {
    if (field === undefined || types === undefined) return field;
    if (type === undefined) type = field.type;
    if (type === undefined && field.html === undefined) {
      if (!id) id = field.id;
      var cls;
      if (id) cls = id.replace('_', '-');
      if (field.classes) {
        type = 'control';
        field.tag = field.classes;
        if (cls) field.class = mkn.appendArray(field.class, cls);
      }
      else if (field.templates) {
        type = 'template';
        field.tag = field.templates;
        if (cls) field.class = mkn.appendArray(field.class, cls);
      }
      else if (field.tag)
        type = 'control';
    }
    if (type === undefined) return field;
    if (typeof type === 'string')
      type = me.mergeType(types[type], undefined, type);
    else
      type = me.mergeType(type);

    return mkn.merge(type, field);
  };

  this.mergeTypes =  function()
  {
    $.each(me.types, function(key, value) {
      me.types[key] = me.mergeType(value);
    })
  };

  this.expandType = function(type)
  {
    if ($.isPlainObject(type)) return type;
    if (type.search(/\W/) >= 0) return {html: type};
    var field = {};
    return me.mergeType(field, type);
  };


  var isDefault = function(item)
  {
    for (var i in array_defaults) {
      var name = array_defaults[i];
      if (item[name] !== undefined) return true;
    }
    return false;
  }

  this.expandFields = function(parent_field, name, items, defaults)
  {
    if (!defaults) defaults = { template: "$field" };
    var path = parent_field.path+'/'+name;
    var parent_is_table = ['table','tr'].indexOf(parent_field.tag) >= 0;
    var pushed = [];
    var first;
    var last;
    var wrap;
    var inherit = parent_field.inherit;
    var last_pos = -1;
    var removed = [];
    for(var i in items) {
      var item = items[i];
      var id;
      var array;
      var template;
      removed.push(i);
      if (typeof item==='string') item = mkn.toObject(item);
      if ($.isPlainObject(item)) {
        if (setDefaults(defaults, item, parent_field)) continue;
        if (item.id === undefined) {
          var a = $.firstElement(item);
          id = a[0];
          item = a[1];
        }
        if (item.merge || id == 'query') continue;
        if (typeof item === 'string') {
          item = { name: item };
        }
        if (inherit && inherit.indexOf(id) >=0 )
          item = merge(parent_field[id], item);
        if (!item.action && defaults.action) item.action = defaults.action;
        promoteAttr(item);
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
      else {
        console.log("Unhandled item", item, parent_field);
      }
      item.id = id;

      if (path)
        item.path = path + '/' + id;
      if (!template) template = item.template = defaults.template;
      if (template && template.subject) item = merge(template.subject, item);
      if (defaults.wrap) {
        item.wrap = defaults.wrap;
        item.wrap.id = name;
        delete defaults.wrap;
      }
      if (item.push)
        pushed.push(item);
      items[i] = item;
      last_pos++;
      delete removed[removed.length-1];
    }
    for (var i in pushed) {
      var item = pushed[i];
      var push = item.push;
      var pos = mkn.indexOfKey(items, 'id', item.id);
      if (push === 'first') {
        if (pos === 0) continue;
      }
      else if (push == 'last') {
        if (pos === last_pos) continue;
      }
      var new_pos = mkn.indexOfKey(items, 'id', item.push);
      items.splice(new_pos, 0, item);
      if (new_pos < pos) ++pos;
      items.splice(pos, 1);
    }

    for (var i in removed) {
      delete items[removed[i]];
    }
  }

  this.createTemplate = function(template, item)
  {
    if (template === undefined || item.template === "none" || template == '$field') return undefined;
    var field = $.copy(this.mergeType(item));
    if (typeof template === 'string') {
      template = {html: template};
    }
    else {
      template = this.mergeType(template);
      mkn.deleteKeys(field, ['type', 'attr', 'class', 'tag', 'html', 'style', 'create','classes','template', 'templates', 'text']);
    }
    template = mkn.merge(field, template);
    return this.create(template)[1];
  };

  this.key =  function(val)
  {
    if (val === undefined) return this._key;
    this._key = val;
    return this;
  }

  this.expandFunction = function(value, parent_id)
  {
    var matches = /(copy|css)\s*\((.+)\)/.exec(value);
    if (!matches || matches.length < 1) return value;
    var source = matches[2];
    if (matches[1] === 'copy')
      source = '#'+source+' #'+parent_id+',[name='+source+'] #'+parent_id;
    return $(source).value();
  }

  this.expandValue = function(values,value,parent_id)
  {
    $.each(values, function(code, subst) {
      if (typeof subst === 'string' && value !== null) {
        subst = me.expandFunction(subst, parent_id)
        value = value.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), subst+'$1');
        values[code] = subst;
        if (value.indexOf('$') < 0) return;
      }
    });
    return value;
  }

  this.expandValues = function(parent_id, data)
  {
    if (!data) return data;
    var expanded;
    var count = 0;
    do {
      expanded = false;
      for (var field in data) {
        var value = data[field];
        if (typeof value !== 'string' || value.indexOf('$') < 0 || field === 'template' && field === 'attr') continue;
        old_value = value = value.replace('$id', parent_id);
        data[field] = value = me.expandValue(data, value, parent_id);
        expanded = old_value !== value;
      }
    } while (expanded);
    return data;
  }

  this.expandArray = function(item)
  {
    $.each(item, function(key, value) {
      var matches = getMatches(value, /\$(\d+)/g);
      for (var i in matches) {
        var index = parseInt(matches[i]);
        value = value.replace(new RegExp("\\$"+index+"([^\d]?)", 'g'), item.array[index-1]+'$1');
      }
      item[key] = value;
    });
  }


  this.inheritParent =  function(parent, field)
  {
    if (!parent || !parent.inherit) return field;
    for (var i in parent.inherit) {
      var key = parent.inherit[i];
      var inherited = {};
      inherited[key] = parent[key];
      field = mkn.merge(inherited, field);
    }
    return field;
  },

  this.initField = function(field, parent)
  {
    field.page_id = this.page_id;
    field = this.mergeType(field);
    field = this.inheritParent(parent, field);

    var id = field.id;
    field.key = this._key;
    if (field.array)
      this.expandArray(field);
    else
      field = removeSubscripts(field);
    if (id && field.name === undefined)
      field.name = toTitleCase(id.replace(/[_\/]/g, ' '));
    this.expandValues(id, field);
    return field;
  }

  this.create =  function(field, parent, has_template)
  {
    field = this.initField(field, parent);
    if (field.sub_page)
      return [field, this.createSubPage(field)];

    var id = field.id;
    if (!field.html) console.log("No html for ", id, field);
    assert(field.html, "Invalid HTML for "+id);
    field.html = field.html.replace(/\$tag(\W)/, field.tag+'$1');
    var obj = $(field.html);
    if (this.sink === undefined) this.sink = obj;
    var reserved = ['id', 'create', 'css', 'script', 'name', 'desc', 'data'];
    setAttr(obj, field);
    setClass(obj, field);
    setStyle(obj, field);
    var values = $.extend({}, this.known, this.types, field);
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
        this.createItems(obj, field, code, value);
        continue;
      }

      if (typeof value === 'string') {
        obj.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), value+'$1');
        this.known[code] = value;
        continue;
      }

      value.path = field.path+'/'+code;
      value.id = code;
      var result = this.create(value, field);
      this.replace(obj, result[1], code);
    }
    if (!has_template || field.template === 'none') initShow(field, obj);
    if (obj.attr('id') === '') obj.removeAttr('id');

    initLinks(obj, field, function() {
      //if (field.value !== undefined) obj.value(field.value)
      setValues(obj, field);
      initEvents(obj, field);
    });
    console.log("me.sink", this.sink);
    return [field, obj];
  }

  this.createSubPage = function(field)
  {
    var tmp = $('<div></div>');
    field.path = field.url? field.url: field.id;
    field.sub_page = undefined;
    tmp.page(field);
    tmp.on('read_'+field.id, function(e, obj) {
      if (field.style)
        setStyle(obj, field);
      var parent = obj.parents().eq(0);
      parent.replaceWith(obj);
    });
    return tmp;
  }

  this.createItems =  function(parent, parent_field, name, items, defaults)
  {
    this.expandFields(parent_field, name, items, defaults);
    var loading_data = false;
    var regex = new RegExp('(\\$'+name+')');
    var new_item_name = '_new_'+name;
    var new_item_html = '<div id="'+new_item_name+'"></div>';
    var wrap;
    for(var i in items) {
      var item = items[i];
      var id = item.id;
      if (id == 'query') {
        loading_data = true;
        this.loadData(parent, parent_field, name, defaults);
        continue;
      }
      parent.replace(regex,new_item_html+'$1');
      var template = item.template;
      var created = this.create(item, parent_field, template !== undefined && template !== '$field');
      item = created[0];
      var obj = created[1];
      var templated = this.createTemplate(template, item);
      var sink = obj;
      if (!templated)
        templated = obj;
      else {
        this.replace(templated, obj, id, 'field');
        sink = templated;
      }

      if (wrap)
        wrap.append(templated);
      else if (item.wrap) {
        wrap = this.create(item.wrap, parent_field)[1];
        wrap.html('');
        parent.find('#'+new_item_name).replaceWith(wrap);
        wrap.append(templated);
        delete item.wrap;
      }
      else
        parent.find('#'+new_item_name).replaceWith(templated);
      initShow(item, sink);
    }
    if (!loading_data)
      parent.replace(regex, '');
  }

  this.replace = function(parent, child, id, field)
  {
    id = id || child.attr('id');
    field = field || id;
    var new_id = "__new__"+id;
    var new_html = "<div id="+new_id+"></div>";
    parent.replace("\\$"+field, new_html);
    parent.find('#'+new_id).replaceWith(child);
    return child;
  }

  this.loadData = function(object, field, name, defaults)
  {
    this.loading++;
    object.on('loaded', function(event,result) {
      if (--me.loading === 0)
        me.parent.trigger('loaded', result);
      if (result === undefined || result === null) {
        console.log('No page data result for object: ', me.object, ' field ', id);
        return;
      }
      if (defaults.attr === undefined) defaults.attr = {};
      defaults.attr.loaded = '';

      if (object.find('[loaded]').exists()) {
        object.find('[loaded]').replaceWith('$'+name);
      }
      me.append_contents(object, field, name, result, defaults);
      if (me.loading === 0)
        me.parent.trigger('loaded', result);
    });
    if (field.autoload || field.autoload === undefined) {
      $.json('/', serverParams('data', field.path+'/'+name), function(result) {
        me.respond(result, object);
        object.trigger('loaded', [result]);
      });
    }

    object.on('reload', function() {
      field.autoload = true;
      me.load_data(object, field, name, defaults);
      if (field.values)
        me.loadValues(object, field);
    })
  };

  var serverParams = function(action, path, params)
  {
    if (!path) path = this.path;
    return { data: $.extend({}, options.request, {key: options.key}, params,
      {action: action, path: path })};
  }

  var initShow = function(field, sink)
  {
    if (field.hide || field.show === false)
      sink.hide();

    sink.on('show_hide', function(event, invoker, condition) {
      $(this).is(':visible')? $(this).hide(): $(this).show();
    });
  };

  var initEvents = function(obj, field)
  {
    if (!field.action) return;
    field.page_id = me.page_id;
    obj.click(function(event) {
      accept(event, $(this), field);
    });

    obj.on('reload', function() {
      loadValues(obj, field);
    });

    obj.on('server_response', function(event, result) {
      respond(result);
    });
  };

  var initLinks = function(object, field, callback)
  {
    loadLinks('css', field, function() {
      loadLinks('script', field, function() {
        if (field.create)
          object.customCreate($.extend({types: this.types}, this.options, field));
        if (callback !== undefined) callback();
      });
    });
  };

  var setAttr = function(obj, field)
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
  }

  var setClass = function(obj, field)
  {
    var cls = field.class;
    if (cls === undefined) return;
    if (typeof cls === 'string') cls = [cls];
    for (var i in cls) {
      obj.addClass(cls[i]);
    }
  }


  var setStyle = function(obj, field)
  {
    var style = field.style;
    if (!style) return;
    obj.css(field.style);
  }

  var removeSubscripts = function(item)
  {
    var removed = [];
    $.each(item, function(key, value) {
      if (typeof value !== 'string') return;
      if (value.match(/^\$\d+$/)) removed.push(key);
    })
    mkn.deleteKeys(item, removed);
    return item;
  }

  var setDefaults = function(defaults, item, parent)
  {
    var names = [ 'type', 'template', 'action', 'attr', 'wrap'];
    var inherit = parent.inherit;
    var set = false;
    for (var i in array_defaults) {
      var name = array_defaults[i];
      var value = item[name];
      if (value === undefined) continue;
      if (name === 'template' && value === 'none')
        defaults[name] = '$field';
      else if (name === 'wrap' && $.isPlainObject(value))
        defaults[name] = $.extend({}, {tag: 'div'}, value);
      else if (name === 'type' || name === 'template' || name === 'wrap')
        defaults[name] = me.expandType(value);
      else
        defaults[name] = item[name];
      if (inherit && inherit.indexOf(value) >=0 )
        defaults[name] = merge(parent[value], defaults[value]);
      set = true;
    }

    promoteAttr(defaults);
    return set;
  }

  var loadLink = function(link,type, callback)
  {
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
    assert(element !== undefined, "Error loading "+link);
    var loaded = false;
    if (callback !== undefined) element.onreadystatechange = element.onload = function() {
      if (!loaded) callback();
      loaded = true;
    }
    var head = document.getElementsByTagName('head')[0];
    head.appendChild(element);
  }

  var loadLinks = function(type, field, callback)
  {
    var links = field[type];
    if (links === undefined || links === null) {
      if (callback !== undefined) callback();
      return;
    }
    if (typeof links === 'string')
      links = links.split(',');
    var loaded = 0;
    $.each(links, function(i, link) {
      loadLink(link, type, function() {
        if (callback !== undefined && ++loaded == links.length) {
          callback();
        }
      });
    });
  }

  var promoteAttr = function(field)
  {
    attr = field.attr;
    if (attr === undefined || $.isPlainObject(attr)) return;
    var val = {};
    val[attr] = attr;
    field.attr = val;
    return field;
  }

  var accept = function(event, obj, field)
  {
    var confirmed = function() {
      var action = field.action;
      field.page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
      switch(action) {
        case 'dialog': mkn.showDialog(field.url, {key: field.key}); return;
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
            var params = serverParams('action', field.path, {key: field.key});
          var selector = field.selector;
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+field.page_id+"$2");
            obj.jsonCheck(event, selector, '/', params, function(result) {
              if (result === null) result = undefined;
              obj.trigger('processed', [result]);
              respond(result, obj);
            });
            break;
          }
          $.json('/', params, function(result) {
            obj.trigger('straight processed', [result]);
            respond(result, obj);
          });
          break;
        case 'trigger':
          me.trigger(field, obj);
          break;
        default:
          if (field.url)
            document.location = field.url.replace(/\$key(\b|\W|$)?/, field.key+"$1");
      }
    }
    if (field.confirmation) {
      mkn.showDialog('/confirm_dialog', {}, function() {
        $('#confirm_dialog #synopsis').text(field.confirmation);
        $('#confirm_dialog .action').click(function() {
          if ($(this).attr('id') === 'yes') confirmed();
          $('#confirm_dialog').dialog('close');
        })
      });
    }
    else confirmed();
  }

  var respond = function(result, invoker)
  {
    if (!result) return;
    var responses = result._responses;
    if (!$.isPlainObject(responses)) return this;
    var parent = me.sink;
    if (invoker) {
       parent = invoker.parents('#'+me.page_id).eq(0);
      if (!parent.exists()) parent = me.sink;
    }
    else invoker = parent;

    var handle = function(action, val)
    {
      console.log("response", me.id, action, val, parent, me.sink, invoker);
      switch(action) {
        case 'alert': alert(val); break;
        case 'show_dialog': mkn.showDialog(val, responses.options); break;
        case 'close_dialog': mkn.closeDialog(parent, val); break;
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
  }

  var setValues = function(parent, data)
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
      loadValues(parent, data);
  }

  var loadValues =  function(parent, data)
  {
    $.json('/', serverParams('values', data.path), function(result) {
      parent.trigger('loaded_values', [result]);
      respond(result);
      if ($.isPlainObject(result))
        parent.setChildren(result);
      else for (var i in result) {
        parent.setChildren(result[i]);
      }
    });
  }

  var trigger = function(field, invoker)
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
      me.expandValues(field.id, event.parameters);
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
  }

}
