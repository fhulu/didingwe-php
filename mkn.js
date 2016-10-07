var mkn = new function() {
  this.appendArray = function(arr,item)
  {
    if ($.isArray(arr))
      arr.push(item);
    else if (arr)
      arr = [arr, item];
    else
      arr = [item];

    return arr;
  };

  this.deleteKeys = function(obj, keys)
  {
    for (var i in keys) {
      delete obj[keys[i]];
    }
    return obj;
  }

  this.indexOfKey = function(arr, keyName, keyValue)
  {
    for (var i in arr) {
      var obj = arr[i];
      if (obj[keyName] === keyValue) return i;
    }
    return -1;
  }

  this.firstIndexOfKey = function(arr, keyName, keyValue)
  {
    return this.indexOfKey(arr, keyName, keyValue);
  }

  this.lastIndexOfKey = function(arr, keyName, keyValue)
  {
    var last = -1;
    for (var i in arr) {
      var obj = arr[i];
      if (obj[keyName] === keyValue) last = i;
    }
    return last;
  }

  this.merge = function(a1, a2)
  {
    if (a1 === undefined || a1 === null) return a2;
    if (a2 === undefined || a2 === null) return a1;
    var r = mkn.copy(a1);
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
        if (v2[0] == '_reset')
          r[i] = v2.slice(1);
        else
          r[i] = $.merge( $.merge([], v1), v2);
        //note: no deep copying arrays, only objects
        continue;
      }
      if ($.isPlainObject(v1))
        r[i] = this.merge(v1, v2);
      else
        r[i] = v2;
    }
    return r;
  }

  this.toObject = function(val)
  {
    var result = {};
    result[val] = {};
    return result;
  }

  this.setClass = function(obj, classes)
  {
    if (classes === undefined) return;
    if (typeof classes === 'string') classes = [classes];
    var del = [], add =[];
    for (var i in classes) {
      var cls = classes[i];
      cls[0]=='^'? del.push(cls.substr(1)): add.push(cls);
    }
    obj.addClass(add.join(' '));
    obj.removeClass(del.join(' '));
  }


  this.showDialog = function(path, field, callback)
  {
    if (path[0] === '/') path = path.substr(1);
    var params = { path: path };
    if (field instanceof Function)
      callback = field;
    else if ($.isPlainObject(field)) {
      params.values = field.values;
      params.key = field.key;
    }
    var modal = $('<div class="modal">').hide().appendTo('body');
    var content = $('<div class="modal-content">').appendTo(modal);
    var header = $('<div class="container header">').appendTo(content);
    content.draggable({handle: header });
    var close = $('<div class="closebtn pad-x">&times;</div>')
      .appendTo(header).zIndex(1)
      .click(function() { modal.remove(); });
    content.page(params);
    var id = path.replace('/','_');
    var adjustWidths = function(object, options, attr) {
      if (options[attr] === undefined) return;
      object.css(attr, 'auto');
      content.css(attr, options[attr]);
    }
    content.one('read_'+id, function(event, object, options) {
      adjustWidths(object, options, 'width');
      adjustWidths(object, options, 'max-width');
      $('<div>').text(options.name).zIndex(0).appendTo(header);
      mkn.setClass(header,options.header_class);
      mkn.setClass(content,options.class);
      object.attr('title', options.name);
      modal.show();
      if (callback) callback(object);
    });
  }

  this.closeDialog = function(dialog, message)
  {
    if (message) alert(message);
    var parent = dialog.parents('.modal').eq(0);
    if (parent.exists()) parent.remove();
  }

  this.firstElement = function(obj)
  {
    for (var key in obj) {
      if (!obj.hasOwnProperty(key)) continue;
      return [key, obj[key] ];
    };
    return undefined;
  }

  this.firstValue = function(obj)
  {
    for (var key in obj) {
      if (!obj.hasOwnProperty(key)) continue;
      return obj[key];
    };
  }

  this.firstKey = function(obj)
  {
    return this.firstElement(obj)[0];
  }

  this.copy = function(src)
  {
    if ($.isArray(src)) return [].concat(src);
    return $.extend(true, {}, src);
  }

  this.size = function(obj)
  {
      var size = 0, key;
      for (key in obj) {
          if (obj.hasOwnProperty(key)) size++;
      }
      return size;
  };

  this.plainValues = function(options)
  {
    var result = {}
    for (var key in options) {
      var val = options[key];
      if ($.isPlainObject(val) || $.isArray(val)) continue;
      result[key] = val;
    };
    return result;
  }

  this.selector = {
    id: function(v)
    {
      return '#'+v;
    },

    idName: function(v)
    {
      return "#?,[name='?']".replace('?',v);
    },

    name: function(v)
    {
      return "[name='?']".replace('?',v);
    },

    attr: function(name, value)
    {
      return "$name='$value']".replace('$name',name).replace('$value',value);
    }
  }

  this.replaceFields = function(str, fields, data)
  {
    if (typeof str != 'string') return str;
    $.each(fields, function(i, field) {
      var val = i < data.length? data[i]: "";
      str = str.replace('$'+field, val);
    });
    return str;
  }

  this.visible = function(f) {
    return !(f.hide || f.show === false);
  }

  this.walkTree = function(field, callback, level) {
    if (level === undefined) level = 0;
    $.each(field, function(k, value) {
      callback(k, value, field, level);
      if ($.isArray(value) || $.isPlainObject(value))
        mkn.walkTree(value, callback, level+1);
    });
  }

  this.hasFlag = function(flags, flag) {
    return flags.indexOf(flag) >= 0;
  }

  this.toIntValue = function(field, key) {
    if (!(key in field)) return false;
    var val = field[key];
    val = val == ''? 0: parseInt(val);
    if (isNaN(val)) return false;
    field[key] = val;
    return true;
  }

  this.unique = function(a) {
    var seen = {};
    var out = [];
    var len = a.length;
    var j = 0;
    for(var i = 0; i < len; i++) {
      var item = a[i];
      if(seen[item] !== 1) {
           seen[item] = 1;
           out[j++] = item;
      }
    }
    return out;
  }

  this.getCSS = function (href) {
    return mkn.loadLink(href, 'css');
  }

  this.loadLink = function(link, type) {
    return $.Deferred(function(defer) {
      var params = {
        css: { tag: 'link', type: 'text/css', selector: 'href', rel: 'stylesheet' },
        script: { tag: 'script', type: 'text/javascript', selector: 'src'}
      }
      var param = params[type];
      if (link.indexOf('?') < 0) {
        var prev = $(param.tag+'['+param.selector+'="'+link+'"]');
        if (prev.exists()) return defer.resolve(link);
      }
      var element = document.createElement(param.tag);
      delete param.tag;
      element[param.selector] = link;
      delete param.selector;
      $.extend(element, param);
      element[param.src] = link;
      element.type = param.type;
      if (type == 'css') element.rel = 'stylesheet';

      element.onreadystatechange = element.onload = function() { defer.resolve(link); }
      document.head.appendChild(element);
    }).promise();
  }
}

if (!String.prototype.trim) {
  (function() {
    // Make sure we trim BOM and NBSP
    var rtrim = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g;
    String.prototype.trim = function() {
        return this.replace(rtrim, '');
    };
  })();
}

if (!String.prototype.regexCapture) {
  String.prototype.regexCapture = function(regex) {
    var matches = [];
    var match;
    while ((match = regex.exec(this)) !== null) {
      matches = matches.concat(match.slice(1))
    }
    return matches;
  }
}


if (!RegExp.quote) {
  RegExp.quote = function(str) {
    return (str+'').replace(/[.?*+^$[\]\\(){}|-]/g, "\\$&");
  };
}

// Production steps of ECMA-262, Edition 5, 15.4.4.18
// Reference: http://es5.github.io/#x15.4.4.18
if (!Array.prototype.forEach) {

  Array.prototype.forEach = function(callback, thisArg) {

    var T, k;

    if (this === null) {
      throw new TypeError(' this is null or not defined');
    }

    // 1. Let O be the result of calling toObject() passing the
    // |this| value as the argument.
    var O = Object(this);

    // 2. Let lenValue be the result of calling the Get() internal
    // method of O with the argument "length".
    // 3. Let len be toUint32(lenValue).
    var len = O.length >>> 0;

    // 4. If isCallable(callback) is false, throw a TypeError exception.
    // See: http://es5.github.com/#x9.11
    if (typeof callback !== "function") {
      throw new TypeError(callback + ' is not a function');
    }

    // 5. If thisArg was supplied, let T be thisArg; else let
    // T be undefined.
    if (arguments.length > 1) {
      T = thisArg;
    }

    // 6. Let k be 0
    k = 0;

    // 7. Repeat, while k < len
    while (k < len) {

      var kValue;

      // a. Let Pk be ToString(k).
      //    This is implicit for LHS operands of the in operator
      // b. Let kPresent be the result of calling the HasProperty
      //    internal method of O with argument Pk.
      //    This step can be combined with c
      // c. If kPresent is true, then
      if (k in O) {

        // i. Let kValue be the result of calling the Get internal
        // method of O with argument Pk.
        kValue = O[k];

        // ii. Call the Call internal method of callback with T as
        // the this value and argument list containing kValue, k, and O.
        callback.call(T, kValue, k, O);
      }
      // d. Increase k by 1.
      k++;
    }
    // 8. return undefined
  };
}
