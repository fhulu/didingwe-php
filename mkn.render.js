mkn.links = {};

mkn.render = function(options)
{
  var me = this
  me.invoker = options.invoker;
  var types = me.types = options.types;
  me.id = options.id;
  me.options = options;
  me.sink = undefined;
  me.parent = options.parent;
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
    return me.mergeType({type: type} );
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
          var a = mkn.firstElement(item);
          id = a[0];
          item = a[1];
        }
        if (item.merge) continue;
        if (typeof item === 'string') {
          item = { name: item };
        }
        if (id == 'query') {
          item.defaults = mkn.copy(defaults);
        }
        if (inherit && inherit.indexOf(id) >=0 )
          item = mkn.merge(parent_field[id], item);
        if (!item.action && defaults.action) item.action = defaults.action;
        promoteAttr(item);
        if (defaults.attr) item.attr = mkn.merge(item.attr,defaults.attr);
        template = item.template;
        var base = mkn.copy(this.types[id]);
        if (!item.type && defaults.type) {
          item = mkn.merge(defaults.type, item);
          delete base.type;
        }
        item = mkn.merge(base, item);
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
      if (template && template.subject) item = mkn.merge(template.subject, item);
      if (defaults.wrap) {
        item.wrap = defaults.wrap;
        item.wrap.id = name;
        item.wrap = this.initField(item.wrap, parent_field);
        delete defaults.wrap;
      }
      item = this.initField(item, parent_field);
      if (item.push)
        pushed.push(item);

      items[i] = item;
      last_pos++;
      removed.pop();
    }

    for (var i in removed) {
      items.splice(removed[i]-i,1);
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
      item = mkn.copy(item);
      items.splice(pos, 1);
      items.splice(new_pos, 0, item);
    }

  }

  var isTemplate = function(t)
  {
    return t !== undefined && t !== "none" && t !== '$field';
  }

  this.createTemplate = function(template, item)
  {
    if (!isTemplate(template)) return undefined;
    var field = mkn.copy(this.mergeType(item));
    if (typeof template === 'string') {
      template = {html: template};
    }
    else {
      template = this.mergeType(template);
      mkn.deleteKeys(field, ['type', 'attr', 'action', 'class', 'tag', 'html', 'style', 'create','classes','template', 'templates', 'text']);
    }
    template = this.initField(mkn.merge(field, template));
    return this.create(template);
  };

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
      if ($.isNumeric(code)) return;
      if (typeof subst === 'string' && value !== null) {
        subst = me.expandFunction(subst, parent_id)
        value = value.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), subst+'$1');
        values[code] = subst;
        if (value.indexOf('$') < 0) return;
      }
    });
    return value;
  }

  this.expandValues = function(data, parent_id)
  {
    if (!data) return data;
    if (parent_id === undefined) parent_id = data.id;
    var expanded;
    var count = 0;
    do {
      expanded = false;
      for (var field in data) {
        if ($.isNumeric(field)) continue;
        var value = data[field];
        if (typeof value !== 'string' || value.indexOf('$') < 0 || field === 'template' && field === 'attr') continue;
        var old_value = value = value.replace('$id', parent_id);
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
    var ignore = field.non_inherit;
    for (var i in parent.inherit) {
      var key = parent.inherit[i];
      if (ignore && ignore.indexOf(key) >= 0) continue;
      var inherited = {};
      inherited[key] = parent[key];
      field = mkn.merge(inherited, field);
    }
    return field;
  }

  this.initField = function(field, parent)
  {
    field.page_id = this.page_id;
    field = this.mergeType(field);
    field = this.inheritParent(parent, field);

    var id = field.id;
    if (field.array)
      this.expandArray(field);
    else
      field = removeSubscripts(field);
    if (id && field.name === undefined)
      field.name = toTitleCase(id.replace(/[_\/]/g, ' '));
    this.expandValues(field);
    return field;
  }

  var isTableTag = function(tag)
  {
    return ['table','thead','th','tbody','tr','td'].indexOf(tag) >= 0;
  }
  this.create =  function(field, templated)
  {
    if (field.sub_page)
      return this.createSubPage(field);

    var id = field.id;
    if (!field.html) console.log("No html for ", id, field);
    assert(field.html, "Invalid HTML for "+id);
    field.html = field.html.trim().replace(/\$tag(\W)/, field.tag+'$1');
    var table_tag = isTableTag(field.tag);
    var obj = table_tag? $('<'+field.tag+'>'): $(field.html);
    if (this.sink === undefined) this.sink = obj;
    var reserved = ['id', 'create', 'css', 'script', 'name', 'desc', 'data'];
    setAttr(obj, field);
    setClass(obj, field);
    setStyle(obj, field);
    var values = $.extend({}, this.known, this.types, field);
    var matches = getMatches(field.html, /\$(\w+)/g);
    var subitem_count = 0;
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
        this.expandFields(field, code, value);
        subitem_count += this.createItems(obj, field, code, value);
        continue;
      }

      if (typeof value === 'string') {
        obj.replace(new RegExp('\\$'+code+"([\b\W]|$)?", 'g'), value+'$1');
        this.known[code] = value;
        continue;
      }

      value.path = field.path+'/'+code;
      value.id = code;
      value = this.initField(value, field);
      var child = this.create(value);
      if (table_tag)
        obj.append(child)
      if (field.html == '$'+code)
        obj.html('').append(child);
      else
        this.replace(obj, child, code);
      ++subitem_count;
    }
    if (obj.attr('id') === '') obj.removeAttr('id');
    if (!templated && (field.hide || field.show === false))
      obj.hide();

    initLinks(obj, field, function() {
      //if (field.value !== undefined) obj.value(field.value)
      if (subitem_count) setValues(obj, field);
      initEvents(obj, field);
    });
    return obj;
  }

  this.createSubPage = function(field)
  {
    var tmp = $('<span></span>');
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

  this.createItems = function(parent, parent_field, name, items, defaults)
  {
    var loading_data = false;
    var regex;
    if (name !== undefined && parent_field.html.trim() !=='$'+name && !isTableTag(parent_field.tag))
      regex = new RegExp('(\\$'+name+')');
    var new_item_name = '_new_'+name;
    var new_item_html = '<div id="'+new_item_name+'"></div>';
    var wrap;
    var count = 0;
    for(var i in items) {
      var item = items[i];
      var id = item.id;
      if (id == 'query') {
        loading_data = true;
        this.loadData(parent, parent_field, name, item.defaults);
        continue;
      }
      ++count;
      if (regex)
        parent.replace(regex,new_item_html+'$1');
      var template = item.template;
      var hasTemplate = isTemplate(template);
      var obj = this.create(item, hasTemplate);
      var templated = obj;
      if (hasTemplate) {
        templated = this.createTemplate(template, item);
        if (isTableTag(template.tag))
          templated.append(obj);
        else
          this.replace(templated, obj, id, 'field');
      }
      if (wrap)
        wrap.append(templated);
      else if (item.wrap) {
        wrap = this.create(item.wrap);
        wrap.html('');
        parent.find('#'+new_item_name).replaceWith(wrap);
        wrap.append(templated);
        delete item.wrap;
      }
      else if (regex)
        parent.find('#'+new_item_name).replaceWith(templated);
      else
        parent.append(templated);
    }
    if (!loading_data && regex)
      parent.replace(regex, '');
    return count;
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
      me.expandFields(field, name, result, defaults)
      me.createItems(object, field, name, result, defaults);
      if (me.loading === 0)
        me.parent.trigger('loaded', result);
    });
    if (field.autoload || field.autoload === undefined) {
      $.json('/', serverParams('data', field.path+'/'+name), function(result) {
        respond(result, object);
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
    if (!path) path = me.path;
    return { data: $.extend({}, options.request, {key: options.key}, params,
      {action: action, path: path })};
  }

  var initEvents = function(obj, field)
  {
    if (!field.action) return;
    field.page_id = me.page_id;
    obj.click(function(event) {
      accept(event, $(this), field);
    })
    .on('reload', function() {
      loadValues(obj, field);
    })
    .on('server_response', function(event, result) {
      respond(result);
    })
  };

  var initLinks = function(object, field, callback)
  {
    loadLinks('css', field, function() {
      loadLinks('script', field, function() {
        if (field.create)
          object.customCreate($.extend({types: me.types}, me.options, field));
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
    if (obj[0]) $.each(obj[0].attributes, function(i, attr) {
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
    for (var key in style) {
      var val = style[key];
      var matches = getMatches(val, /\$(\w+)/g);
      for (var i in matches) {
        var match = matches[i];
        style[key] = val.replace(new RegExp('\\$'+match+"([\b\W]|$)?", 'g'), field[match]+'$1');
      }
    }
    field[style] = style;
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
    if (mkn.size(item) != 1) return false;
    var names = [ 'type', 'template', 'action', 'attr', 'wrap'];
    var inherit = parent.inherit;
    var set = false;
    for (var i in array_defaults) {
      var name = array_defaults[i];
      var value = item[name];
      if (value === undefined) continue;
      if (value[0] == '$')
        value = parent[value.substring(1)];
      if (value === undefined) continue;
      if (inherit && inherit.indexOf(value) >=0 )
        value = mkn.merge(parent[value], defaults[name]);
      if (name === 'template' && value === 'none')
        value = '$field';
      else if (name === 'wrap' && $.isPlainObject(value))
        value = $.extend({}, {tag: 'div'}, value);
      else if (name === 'type' || name === 'template' || name === 'wrap') {
        if (value === undefined) { console.log("undefiend value", name, item)}
        value = me.expandType(value);
      }
      if (name == 'template' && $.isPlainObject(value))
        value = me.initField(value);

      defaults[name] = value;
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
    var attr = field.attr;
    if (attr === undefined || $.isPlainObject(attr)) return;
    var val = {};
    val[attr] = attr;
    field.attr = val;
    return field;
  }

  var redirect = function(field)
  {
    if (!$.isPlainObject(field)) field = { url: field };
    var url = field.url;
    if (url === undefined && field.query) {
      url = '/?action=action';
      var exclude = ['action', 'desc', 'html', 'id', 'name', 'page_id', 'query', 'tag', 'text', 'type', 'template']
      for (var key in field) {
        var val = field[key];
        if ($.isPlainObject(val) || $.isArray(val) || exclude.indexOf(key) >= 0) continue;
        url += '&'+key+'='+encodeURIComponent(field[key]);
      }
    }
    if (!url) return;
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

  var accept = function(event, obj, field)
  {
    var confirmed = function() {
      var action = field.action;
      field.page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
      switch(action) {
        case 'dialog': mkn.showDialog(field.url, {key: field.key}); return;
        case 'redirect': redirect(field); break;
        case 'post':
            var params = serverParams('action', field.path, {key: field.key});
          var selector = field.selector;
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+field.page_id+"$2");
            params = $.extend(params, {invoker: obj, event: event, async: true });
            me.sink.find(".error").remove();
            $(selector).json('/', params, function(result) {
              obj.trigger('processed', [result]);
              respond(result, obj, event);
            });
            break;
          }
          $.json('/', params, function(result) {
            obj.trigger('straight processed', [result]);
            respond(result, obj);
          });
          break;
        case 'trigger':
          trigger(field, obj);
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

  var reportError = function(field, error)
  {
    var subject = me.sink.find('#'+field+",[name='"+field+"']");
    var sibling = subject.parent();
    if (sibling.length == 0)
      sibling = subject;
    if (sibling.length == 0) {
      if (field == "alert") alert(error);
      return;
    }
    var box = $("<div class=error>"+error+"</div>");
    sibling.after(box);
    box.fadeIn('slow').click(function() { $(this).fadeOut('slow') });
  }

  var reportAllErrors = function(result, event)
  {
    $.each(result, function(key, row) {
      if (key == 'errors') {
        console.log("has errors", row);
        $.each(row, function(field, error) {
          reportError(field, error);
        });
        if (event) event.stopImmediatePropagation();
      }
    });
  }


  var respond = function(result, invoker, event)
  {
    if (!result) return;
    reportAllErrors(result, event);
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
      console.log("response", me.id, action, val);
      switch(action) {
        case 'alert': alert(val); break;
        case 'show_dialog': mkn.showDialog(val, responses.options); break;
        case 'close_dialog': mkn.closeDialog(parent, val); break;
        case 'redirect': redirect(val); break;
        case 'update': parent.setChildren(val); break;
        case 'trigger': trigger(val, parent); break;
      }
    }

    for (var key in responses) {
      var val = responses[key];
      if (!$.isArray(val))
        handle(key, val);
      else for (var i in val)
        handle(key, val[i]);
    }
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
        var el = mkn.firstElement(item);
        id = el[0];
        item = el[1];
      }
      var obj = parent.find(mkn.selector.idName(id));
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
      me.expandValues(event.parameters, field.id);
      params = event.parameters;
      event = event.name;
    }
    else {
      sink = field.sink;
      params = field.params;
      if (params !== undefined && !$.isArray(params))
        params = [params];
      if (event === 'show' || event  == 'show_hide')
        event = '.toggle';
      if (event == 'toggle' || event == '.toggle' && params !== undefined)
        params = [parseInt(params[0]) === 1 || params[0] === true];
    }

    if (sink)
      sink = $(sink.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+me.id+"$2"));
    else
      sink = invoker;
    if (event[0] === '.') {
      sink[event.substring(1)].apply(sink,params);
      return;
    }
    event = $.Event(event);
    event.target = invoker[0];
    sink.trigger(event, params);
  }

}
