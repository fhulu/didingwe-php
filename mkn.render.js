mkn.links = {};
mkn.model = {};

mkn.render = function(options)
{
  var searchModelRegex = /^~(.+)$|`([^`]+)`/g
  var evalModelRegex = /(^~.+|`[^`]+`)/g
  var me = this
  me.invoker = options.invoker;
  var types = me.types = options.types;
  me.id = options.id;
  me.options = options;
  me.sink = undefined;
  me.parent = options.parent;
  me.known = {};
  me.root = {};

  var array_defaults = [ 'type', 'types', 'template', 'action', 'attr', 'wrap', 'default'];
  var geometry = ['left','right','width','top','bottom','height', 'line-height','max-height', 'max-width'];

  var mutable = function(field) {
    return field.mutable || field.mutable === undefined || field.mutable !== false;
  }

  var mergeTypeArray = function(array)
  {
    var result = {};
    for (var i in array) {
      var type = array[i];
      var merged = me.mergeType(types[type], undefined, type);
      result = mkn.merge(result, merged);
    }
    return result;
  }

  this.mergeType = function(field, type, id)
  {
    if (field === undefined || types === undefined) return field;
    if (type === undefined) type = field.type;
    if (type === undefined && field.html === undefined) {
      if (!id) id = field.id;
      var cls;
      if (id) cls = id.replace(/_/g, '-');
      if (field.classes) {
        type = 'control';
        if (types[field.classes])
          type = field.classes;
        else field.tag = field.classes;
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
    else if ($.isArray(type))
      type = mergeTypeArray(type);
    else
      type = me.mergeType(type);

    var result = mkn.merge(type, field);
    delete result.type;
    return result;
  };

  this.expandType = function(type)
  {
    if ($.isPlainObject(type)) return me.mergeType(type);
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

  var mergeImmutables = function(item, base, type) {
    var immutables = type.immutable;
    for (var i in immutables) {
      var key = immutables[i];
      if (base[key] !== undefined && item[key] === undefined) item[key] = base[key];
    }
  }

  var mergeDefaultType = function(base, item, type) {
    type = mkn.copy(type);
    mergeImmutables(item, base, type);
    base = mkn.copy(base);
    mkn.deleteKeys(base, ['type', 'styles', 'style'])
    mkn.deleteKeys(base, geometry)
    return mkn.merge(mkn.merge(type,base), item);
  }

  var mergeDefaults = function(item, defaults, base) {
    if (!item.action && defaults.action) item.action = defaults.action;
    if (defaults.attr) item.attr = mkn.merge(item.attr,defaults.attr);
    if (!item.template) item.template = defaults.template;
    if (defaults.default) item = mkn.merge(defaults.default, item);
    if (defaults.types)
      return mergeDefaultType(base, item, defaults.types.shift());
    else if (!item.type && defaults.type)
      return mergeDefaultType(base, item, defaults.type);
    return mkn.merge(base, item);
  }


  var removePopped = function(items, popped) {
    var i = items.length;
    while (i--) {
      var item = items[i];
      if (popped.indexOf(item.id) >= 0)
        items.splice(i,1);
    }
  }

  var insertPushed = function(items, pushed) {
    for (var i in pushed) {
      var item = pushed[i];
      var push = item.push;
      delete item.push;
      var pos = items.indexOf(item);
      item = mkn.copy(item);
      if (push === 'first') {
        items.splice(pos, 1);
        items.unshift(item);
      }
      else if (push === 'last') {
        items.splice(pos, 1);
        items.push(item);
      }
      else if (push == 'merge') {
       items.splice(pos, 1 );
       pos = mkn.firstIndexOfKey(items, 'id', item.id);
       items[pos] = mkn.merge(items[pos], item);
      }
      else
        items.splice(mkn.firstIndexOfKey(items, 'id', item.push), 0, item);
    }
  }

  var pushPop = function(items) {
    var pushed = [];
    var popped = [];
    for(var i in items) {
      var item = items[i];
      if ($.isArray(item)) continue;
      if (item.id == 'pop')
        popped.push(item.name);
      else if (item.push)
        pushed.push(item);
      items[i] = item;
    }
    removePopped(items, popped);
    insertPushed(items, pushed);
  }

  var objectify = function(items) {
    for (var i in items) {
      var item = items[i];
      if (typeof item==='string') item = mkn.toObject(item);
      if (!$.isPlainObject(item)) continue;
      var id = item.id;
      if (id === undefined) {
        var a = mkn.firstElement(item);
        id = a[0];
        if (array_defaults.indexOf(id) >=0 ) continue;
        item = a[1];
        if (typeof item == 'string') item = { name: item}
        item = $.extend({}, item);
      }
      if (typeof item === 'string') item = { name: item }
      item.id = id;
      items[i] = item;
    }
  }

  var mergeSubItems = function(parent,items) {
    for(var i=0; i < items.length; ++i) {
      var item = items[i];
      if (!$.isPlainObject(item) || item.id !== 'merge') continue;
      var array = parent[item.name];
      items.splice(i,1);
      objectify(array);
      array.forEach(function(value, j) {
        items.splice(i+j,0,value);
      });
    }
  }

  this.expandFields = function(parent_field, name, items, defaults)
  {
    if (!defaults) defaults = { template: "$field" };
    var path = parent_field.path? parent_field.path + '/' + name: name;
    objectify(items);
    pushPop(items);
    mergeSubItems(parent_field,items);
    var wrap;
    var sow = parent_field.sow;
    var removed = [];
    var index = 0;
    for(var i in items) {
      var item = items[i];
      var id;
      var array;
      var template;
      removed.push(i);
      if ($.isPlainObject(item)) {
        if (setDefaults(defaults, item, parent_field)) continue;
        id = item.id;
        if (id == 'query') {
          item.defaults = mkn.copy(defaults);
        }

        if (id[0] == '$') {
          id = id.substr(1);
          item = mkn.merge(parent_field[id], item);
        }
        id = me.expandValue(parent_field, id);
        if (sow && sow.indexOf(id) >=0)
          item = mkn.merge(parent_field[id], item);
        promoteAttr(item);
        var base = mkn.copy(me.types[id]);
        var merged = mkn.merge(base, item);
        item.id = id;
        if (mutable(merged))
          item = mergeDefaults(item, defaults, base);
        else
          item = merged;
      }
      else if ($.isArray(item)) {
        array = item;
        id = item[0];
        item = mkn.copy(defaults);
        if (array.length>1 && item.name === undefined) item.name = array[1]
        item.array = array;
      }
      else {
        console.log("Unhandled item", item, parent_field);
      }
      item.id = id;

      if (path && !item.path)
        item.path = path + '/' + id;
      var template = item.template;
      if (template == 'none' || template  == '$field')
        delete item.template;
      else if (typeof template == 'string') {
        var def = {};
        setDefaults(def, {template: template}, parent_field);
        item.template = def.template;
      }
      if (defaults.wrap) {
        item.wrap = defaults.wrap;
        item.wrap.id = name;
        item.wrap = this.initField(item.wrap, parent_field);
        delete defaults.wrap;
      }

      item = $.extend({ index: index, number: index+1},item);
      ++index;
      item = this.initField(item, parent_field);

      if (item.template) initTemplate(item)
      items[i] = item;
      removed.pop();
    }

    for (var i in removed) {
      items.splice(removed[i]-i,1);
    }
  }

  var isTemplate = function(t)
  {
    return t !== undefined && t !== "none" && t !== '$field';
  }

  var initTemplate = function(item)
  {
    template = item.template;
    var field = mkn.copy(me.mergeType(item));
    if (typeof template === 'string') {
      template = {html: template};
    }
    else {
      template = me.mergeType(mkn.copy(template));
      mkn.deleteKeys(field, ['type', 'attr', 'action', 'class', 'tag', 'html',
       'style', 'styles', 'create','classes','template', 'templates', 'text', 'templated']);
       mkn.deleteKeys(field, geometry);
       if (!('attr' in template)) template.attr = {};
       template.attr['for'] = item.id;
    }
    item.template = me.initField(mkn.merge(template, field));
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

  this.expandValue = function(values,value)
  {
    $.each(values, function(code, subst) {
      if ($.isNumeric(code) || typeof subst !== 'string' || typeof value !== 'string') return;
      value = value.replace(new RegExp('\\$'+code+"([^\w]|\b|$)", 'g'), subst+'$1');
      values[code] = subst;
      if (value.indexOf('$') < 0) return;
    });
    return value;
  }

  this.expandValues = function(data, parent_id, exclusions)
  {
    if (!data) return data;
    if (parent_id === undefined) parent_id = data.id;
    var expanded;
    var count = 0;
    if (!exclusions) exclusions = [];
    var constants = $.isArray(data.constants)? data.constants: [];
    exclusions = exclusions.concat(constants);
    do {
      expanded = false;
      for (var field in data) {
        var value = data[field];
        if ($.isNumeric(value)) continue;
        if (typeof value !== 'string' || value.indexOf('$') < 0 || exclusions.indexOf(field) >=0) continue;
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
        value = value.replace(new RegExp("\\$"+index+"([^\d]|\b|$)", 'g'), item.array[index-1]+'$1');
      }
      item[key] = value;
    });
  }


  this.parentSow =  function(parent, field)
  {
    if (!parent || !parent.sow) return field;
    for (var i in parent.sow) {
      var key = parent.sow[i];
      var sowed = {};
      sowed[key] = parent[key];
      field = mkn.merge(sowed, field);
    }
    return field;
  }

  var deriveParent = function(parent, field)
  {
    if (!parent || !field.derive) return;
    for (var i in field.derive) {
      var key = field.derive[i];
      var value = field[key];
      if (value === undefined)
        field[key] = parent[key];
      else if ($.isPlainObject(value) || $.isArray(value))
        field[key] = mkn.merge(parent[key], value);
      else if (value[0] == '$')
        field[key] = parent[value.substr(1)];
    }
  }

  this.initField = function(field, parent)
  {
    field.page_id = this.page_id;
    if (field.template && field.template.subject) {
      field = mkn.merge(field.template.subject, field);
      if (!field.template.tag) delete field.template;
    }
    deriveParent(parent, field);
    field = this.parentSow(parent, field);
    field = this.mergeType(field);

    var id = field.id;
    if (id && field.name === undefined)
      field.name = toTitleCase(id.replace(/[_\/]/g, ' '));
    if (field.array)
      this.expandArray(field);
    else
      field = removeSubscripts(field);

    var exclusions = ['template','attr', 'text', 'html'];
    this.expandValues(field, field.id, exclusions );
    if (field.template && field.template.subject) {
      deriveParent(field.template, field);
      this.expandValues(field, field.id, exclusions);
    }
    return field;
  }


  var isTableTag = function(tag)
  {
    return ['table','thead','th','tbody','tr','td'].indexOf(tag) >= 0;
  }

  var runJquery = function (obj, item) {
    if (!item.jquery) return;
    expandVars(item,item.params);
    obj.call(item.jquery, item.params);
  }

  var setVisible = function(obj, field) {
    if (field.template) return;
    mkn.toIntValue(field, 'show');
    mkn.toIntValue(field, 'hide');
    if (field.hide || field.show === false || field.show === 0)
      obj.hide();
    else if (field.show)
      obj.show();
  }

  var setDisabled = function(obj, field) {
    if (mkn.toIntValue(field, 'disabled') && field);
      obj.prop('disabled', field.disabled);
  }

  this.render = function(parent, key) {
    me.root = parent[key] = me.initField(parent[key], parent);
    var obj = me.create(parent, key);
    obj.trigger('load');
    if (initModel()) me.updateWatchers();
    return obj;
  }

  this.create =  function(parent, key, init)
  {
    var field = key===undefined? parent: parent[key];
    if (!field) field = types[key];
    if (init) field = this.initField(field, parent);
    if (field.sub_page) {
      var tmp = $('<div>creating...</div>');
      this.createSubPage(parent, key).done(function(created) {
        tmp.replaceWith(created);
      });
      return tmp;
    }
    var id = field.id;
    if (field.html === undefined) return null;
    field.text = this.expandValue(field, field.text);
    field.html = this.expandValue(field, field.html);
    field.html = field.html.trim().replace(/\$tag(\W)/, field.tag+'$1');
    var table_tag = isTableTag(field.tag)
    var obj = table_tag? $('<'+field.tag+'>'): $(field.html);
    if (this.sink === undefined) this.sink = obj;
    var reserved = ['id', 'create', 'css', 'script', 'name', 'desc', 'data'];
    setAttr(obj, field);
    setClass(obj, field);
    setStyle(obj, field);
    if (field.key === undefined) field.key = options.key;
    var values = $.extend({}, this.types, field);
    var matches = getMatches(field.html, /\$(\w+)/g);
    var subitem_count = 0;
    for (var i = 0; i< matches.length; ++i) {
      var code = matches[i];
      var value = values[code];
      if (value === undefined) continue;
      if (typeof value === 'string' && value.search(/\W/) < 0 && reserved.indexOf(code) < 0) {
        value = values[value] || value;
      }

      if ($.isArray(value)) {
        if (this.types[code] !== undefined)
          value = $.merge($.merge([], this.types[code]), value);
        this.expandFields(field, code, value);
        subitem_count += this.createItems(obj, field, code, value);
        continue;
      }

      if (!$.isPlainObject(value)) {
        obj.replace(new RegExp('\\$'+code+"([^\w]|\b|$)?", 'g'), value+'$1');
        this.known[code] = value;
        continue;
      }

      value.path = field.path+'/'+code;
      value.id = code;
      var child = this.create(field, code, true);
      if (table_tag)
        obj.append(child)
      if (field.html == '$'+code)
        obj.html('').append(child);
      else
        this.replace(obj, child, code);
    }
    if (obj.attr('id') === '') obj.removeAttr('id');

    setVisible(obj, field);
    setDisabled(obj, field);

    runJquery(obj, field);
    field['mkn-object'] = obj;
    initLinks(obj, field).then(function() {
      if (subitem_count) setValues(obj, field);
      initEvents(obj, field);
    });

    if (key !== undefined) parent[key] = field;
    return obj;
  }

  this.createSubPage = function(parent, keyOrTarget)
  {
    var tmp = $('<span>').addClass('loading');
    field = parent;
    var isTarget = keyOrTarget instanceof jQuery;
    if (isTarget)
      keyOrTarget.replaceWith(tmp);
    else if (keyOrTarget !== undefined)
      field = parent[keyOrTarget];
    delete field.sub_page;
    delete field.appendChild;
    field.path = field.url? field.url: field.id;
    return tmp.page($.extend({request: options.request}, field)).done(function(obj) {
      setStyle(obj, field);
      setClass(obj, field);
      tmp.replaceWith(obj);
      if (!isTarget) return;
      var target_classes = keyOrTarget.selector.regexCapture(/(\.\w[\w\.]*)$/g);
      if (target_classes.length) obj.addClass(target_classes[0].replace('.', ' '));
    });
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
      var templated;
      var obj = this.create(items, i);
      if (item.template && (templated = this.create(item, 'template'))) {
        if (isTableTag(item.template.tag))
          templated.append(obj);
        else
          this.replace(templated, obj, id, 'field');
      }
      else {
        templated = obj;
        delete item.template;
      }
      if (wrap)
        wrap.append(templated);
      else if (item.wrap) {
        wrap = this.create(item, 'wrap');
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
    object.on('loaded', function(event, field, result) {
      if (--me.loading === 0)
        me.parent.trigger('loaded', result);
      if (result === undefined || result === null) {
        console.log('No page data result for object: ', me.object, ' field ', field.id);
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
        me.parent.trigger('loaded', [field,result]);
    });
    if (field.autoload || field.autoload === undefined) {
      $.json('/', serverParams('data', field.path+'/'+name, field.params), function(result) {
        respond(result, object);
        object.trigger('loaded', [field, result.data]);
      });
    }

    object.on('reload', function(event, data) {
       field.autoload = true;
       field.params = data;
       me.loadData(object, field, name, defaults);
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

  var initTooltip = function(obj) {

  }

  var isWatchValue = function(value) {
    return /\$@\w+/.test(value);
  }

  var initOnEvents = function(obj, field) {
    var events = {};
    if (field.action)
      events['click'] = [field];
    $.each(field, function(key, value) {
      if (key.indexOf('on_') != 0) return;
      var event = key.substr(3);
      if (!(event in events)) events[event] = [];
      events[event].push(value);
    });
    $.each(events, function(key, values) {
      $.each(values, function(i, value) {
        if (!value.path) value.path = field.path + '/on_' + key;
        obj.on(key, function(e) {
          // trap trapped events
          if ($.isArray(field.trap) && field.trap.indexOf(key) >=0 )
            e.stopImmediatePropagation();

          // ignore default action of an anchor
          if (key == 'click' && obj.prop("tagName").toLowerCase() == 'a') {
            e.preventDefault();
            if (field.url === undefined) field.url = obj.attr('href');
          }
          accept(e, obj, value);
        });
      });
    });
  }

  var initEvents = function(obj, field) {
    if ('attr' in field && field.attr.for == field.id) return;
    initOnEvents(obj, field);
    if (typeof field.enter == 'string') {
      obj.keypress(function(event) {
        if (event.keyCode === 13)
          obj.find(field.enter).click();
      })
    }
    field.page_id = me.page_id;
    obj.on('reload', function() {
      loadValues(obj, field);
    })
    .on('server_response', function(event, result) {
      respond(result);
    })
    initTooltip(obj);

    var tag = obj.prop("tagName").toLowerCase();
    if (['input','select','textarea'].indexOf(tag) < 0) return;

    var id = field.id;
    obj.on('keyup input cut paste change', function() {
      if (!(id in mkn.model)) return;
      mkn.model[id] = obj.value();
      me.updateWatchers();
    });
  };

  var initLinks = function(object, field)
  {
    return $.when(loadLinks('css', field),loadLinks('script', field)).then(function(x,y){
      if (field.create)
        object.customCreate($.extend({render: me}, field));
    });
  };

  var setAttr = function(obj, field)
  {
    expandVars(field, field.attr, { sourceFirst: true, recurse: true})
    var attr = field.attr;
    if (obj.attr('id') === '') obj.removeAttr('id');
    if (!attr) return;
    if (typeof attr === 'string') {
      obj.attr(attr,"");
    }
    else $.each(attr, function(key, val) {
      if (field.array) {
        var numeric = getMatches(val, /\$(\d+)/g);
        if (numeric.length) val = field.array[numeric[0]];
      }
      var matches = getMatches(val, /\$(\w+)/g)
      for (var j in matches) {
        var match = matches[j];
        var value = field[match];
        if (value === undefined || typeof value !== 'string') continue;
        val = val.replace('$'+match, value);
      }
      obj.attr(key,val);
    });
    if (obj.attr('id') === '') obj.removeAttr('id');
  }

  var setClass = function(obj, field)
  {
    var cls = field.class;
    if (cls === undefined) return;
    if (typeof cls === 'string') cls = [cls];
    expandVars(field, cls, { sourceFirst: true, recurse: true})
    mkn.setClass(obj, cls);
  }


  var setStyle = function(obj, field)
  {
    var immutable = field.immutable;
    var mergeStyles = function() {
      if (typeof styles == 'string')
        styles = me.mergeType({}, styles.split(/[\s,]+/));
      for (var i in styles) {
        var type = me.mergeType({}, styles[i]);
        mkn.deleteKeys(type, immutable);
        $.extend(style, type);
      }
    }

    var setGeometry = function() {
      for (var i in geometry) {
        var key = geometry[i];
        if (immutable && immutable.indexOf(key) >= 0) continue;
        var val = field[key];
        if (val !== undefined && val[0] !== '$')
          style[key] = val;
      }
    }

    var style = field.style;
    if (!style) style = {};
    styles = field.styles;
    if (styles) mergeStyles();
    expandVars(field, style, { sourceFirst: true, recurse: true})
    expandVars(style, style, { sourceFirst: true, recurse: true})
    setGeometry();
    obj.css(style);
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

  var expandVars = function(source, dest, flags)
  {
    if (!flags) flags = {};
    var args = arguments;
    var replaced = false;
    var doit = function() {
      for (var key in dest) {
        if ($.isArray(dest.constants) && dest.constants.indexOf(key) >= 0) continue;
        var val = dest[key];
        if ($.isPlainObject(val) && flags.recurse) {
          expandVars($.extend({}, source, dest), val, flags);
          continue;
        }
        if (typeof val !== 'string') continue;
        var matches = getMatches(val, /\$(\w+)/g);
        for (var i in matches) {
          var match = matches[i];
          var index = flags.sourceFirst? 0: 1;
          var replacement = args[index++][match];
          if (replacement === undefined) replacement = args[index % 2][match];
          if (replacement === undefined) continue;
          var old = val;
          dest[key] = val = val.replace(new RegExp('\\$'+match+"([^\w]|\b|$)", 'g'), replacement+'$1');
          if (!replaced)
            replaced = val != old;
        }
      }
    };

    do {
      replaced = false;
      doit();
    } while (replaced && flags.recurse)
  }

  var expandSubject = function(template)
  {
    var subject = template.subject
    for (var key in subject) {

    }
  }

  var mergePrevious = function(defaults, name, value)
  {
    var prev = defaults[name];
    if ($.isPlainObject(prev))
      value = mkn.merge(prev, value);
    else if (typeof value == 'string')
      value.type = prev;
    return value;
  }

  var setDefaults = function(defaults, item, parent)
  {
    if (mkn.size(item) != 1) return false;
    var sow = parent.sow;
    var set = false;
    for (var i in array_defaults) {
      var name = array_defaults[i];
      var value = item[name];
      if (value === undefined) continue;
      if (value[0] == '$')
        value = parent[value.substring(1)];
      if (value === undefined) continue;
      if (sow && sow.indexOf(value) >=0 )
        value = mkn.merge(parent[value], defaults[name]);
      if (name === 'template' && value === 'none')
        value = '$field';
      else if (name === 'wrap' && $.isPlainObject(value))
        value = $.extend({}, {tag: 'div'}, value);
      else if (name === 'type' || name === 'template' || name === 'wrap') {
        if (value === undefined) { console.log("undefined value", name, item)}
        value = me.expandType(value);
      }
      else if (name === 'types') {
        var types = [];
        for (var i in value) {
          types.push(me.expandType(value[i]));
        }
        value = types;
      }
      if (name == 'template' && $.isPlainObject(item[name]) && item[name].type === undefined) {
        value = mergePrevious(defaults, name, me.initField(value));
        expandVars(value, value.subject, { recurse: true});
      }

      defaults[name] = value;
      set = true;
    }

    promoteAttr(defaults);
    return set;
  }

  var loadLinks = function(type, field)
  {
    var links = field[type];
    if (typeof links === 'string')
      links = links.split(',');
    if (links === undefined || links === null || links.length==0) return $.when();
    return $.when.apply($, $.map(links, function(link) {
      return mkn.loadLink(link, type);
    }));
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
    if ((!url || field.query) && field.target === '_blank') {
      url = '/?action=action';
      field = $.extend({key: options.key}, field);
      var exclude = ['action', 'desc', 'html', 'id', 'name', 'page_id', 'query', 'selector','tag', 'target','text', 'type', 'template']
      for (var key in field) {
        var val = field[key];
        if ($.isPlainObject(val) || $.isArray(val) || exclude.indexOf(key) >= 0) continue;
        url += '&'+key+'='+encodeURIComponent(field[key]);
      }
    }
    if (!url) return;
    if (field.target === '_blank')
      window.open(url, field.target);
    else if (field.target)
      me.createSubPage({url: url}, $(field.target));
    else
      document.location = url;
  }


  var accept = function(event, obj, field, action)
  {
    var dispatch = function()
    {
      if (action === undefined) action = field.action;
      field.page_id = field.page_id || obj.parents(".page").eq(0).attr('id');
      switch(action) {
        case 'dialog': mkn.showDialog(field.url, {key: field.key}); return;
        case 'close_dialog': mkn.closeDialog(obj.parents(".page").eq(0));
        case 'redirect': redirect(field); break;
        case 'post':
          var url = field.url? field.url: field.path
          var params = serverParams('action', url, {key: field.key});
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
          if (isWatchValue(action)) {
            evaluateModelValue(action);
            me.updateWatchers();
          }
          else if (field.url)
            document.location = field.url.replace(/\$key(\b|\W|$)?/, field.key+"$1");
      }
    }
    if (!field.confirmation || action)
      dispatch();
    else mkn.showDialog('/confirm_dialog', function(dialog) {
      $('#confirm_dialog #synopsis').text(field.confirmation);
      $('#confirm_dialog .action').click(function() {
        if ($(this).attr('id') === 'yes') dispatch();
        mkn.closeDialog(dialog);
      })
    });
  }

  var reportError = function(field, error)
  {
    if (field == "alert") {
      alert(error);
      return;
    }
    var subject = me.sink.find('#'+field+",[name='"+field+"']");
    var parents = subject.parents("[for='"+field+"'],.error-sink");
    var parent = parents.exists()? parents.eq(0): subject;

    var box = $("<div class=error>"+error+"</div>");
    parent.after(box);
    box.zIndex(parent.zIndex()+1);
    box.fadeIn('slow').click(function() { $(this).fadeOut('slow') });
  }

  var reportErrors = function(errors, event)
  {
    for (var key in errors) {
      reportError(key, errors[key]);
    }
    if (event) event.stopImmediatePropagation();
  }

  var respond = function(result, invoker, event)
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
      console.log("response", me.id, action, val);
      switch(action) {
        case 'alert': alert(val); break;
        case 'show_dialog': mkn.showDialog(val, responses.options); break;
        case 'close_dialog': mkn.closeDialog(parent, val); break;
        case 'redirect': redirect(val); break;
        case 'update': parent.setChildren(val, true); break;
        case 'trigger': trigger(val, parent); break;
        case 'error': reportError(val); break;
        case 'errors': reportErrors(val); break;
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
    $.json('/', serverParams('values', data.path, {key: data.key}), function(result) {
      if (!result) return;
      parent.trigger('loaded_values', [result]);
      if ($.isPlainObject(result))
        parent.setChildren(result, true);
      else for (var i in result) {
        parent.setChildren(result[i], true);
      }
      respond(result);
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

    if (sink) {
      var selector = sink;
      sink = $(sink.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+me.id+"$2"));
      if (!sink.exists())
        sink = window.parent.$(selector);
    }
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

  var initModel = function(expr) {

    var inject = function(expr) {
      var captured = expr.regexCapture(/(?:^|[^.a-z_'"])([a-z_][\w]*)(?:[^'"]|$)/gi);
      var vars = [];
      $.each(captured, function(i, v) {
        if (!(v in mkn.model)) mkn.model[v] = '';
        if (!(v in vars)) vars.push(v);
      });
      var source = vars.map(function(v) {
        return "var " + v + " = mkn.model['"+v+"']";
      })
      .join(";") + "; return " + expr;
      return new Function(source);
    }


    var search = function(key, value, parent) {
      if (typeof key !== 'string' || key.indexOf('mkn-original-') == 0 || typeof value !== 'string') return false;
      var exprs = value.regexCapture(searchModelRegex);
      var injections = [];
      $.each(exprs, function(i, e) {
        if (!e) return;
        var injection = inject(e);
        if (injection) injections.push(injection);
      });
      if (!injections.length) return false;
      parent['mkn-injections-'+key] = injections;
      parent['mkn-original-'+key] = value;
      return true;
    }


    if (me.root.dd_init) $.each(me.root.dd_init, function(key, val) {
      mkn.model[key] = val;
    });

    var watching = false;
    mkn.walkTree(me.root, function(key, value, parent) {
      watching |= search(key, value, parent);
    });
    return watching;
  }


  me.updateWatchers = function() {

    var evaluate = function(field, key) {
      if (typeof key != 'string' || key.indexOf('mkn-original-') < 0 ) return false;
      var orig = key;
      key = key.substr(13);
      if (key == 'html') return false;
      value = field[orig];
      var exprs = value.regexCapture(evalModelRegex);
      if (!exprs.length) return false;
      var injections = field['mkn-injections-'+key];
      $.each(exprs, function(i, e) {
        if (e) value = value.replace(e, injections[i]());
      });
      field[key] = value;
      if (key == 'text') field['mkn-object'].text(value);
      return true;
    }

    var complete = function(field, watching) {
      var obj = field['mkn-object'];
      if (!watching || !obj) return watching;
      setAttr(obj, field);
      setClass(obj, field);
      setStyle(obj, field);
      setVisible(obj, field);
      setDisabled(obj, field);
      return false;
    }

    var loop = function(parent) {
      var watching;
      $.each(parent, function(key, value) {
        if ($.isPlainObject(value) || $.isArray(value))
          watching |= loop(value);
        else if (evaluate(parent, key))
          watching = true;
      });
      return complete(parent, watching);
    }

    loop(me.root);
  }
}
