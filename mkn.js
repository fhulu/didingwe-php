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

  this.showDialog = function(path, field, callback)
  {
    if (path[0] === '/') path = path.substr(1);
    var params = { path: path };
    if (field) {
      params.values = field.values;
      params.key = field.key;
    }
    var modal = $('<div class="w3-modal">').hide().appendTo('body');
    var content = $('<div class="w3-modal-content">').appendTo(modal);
    var header = $('<div class="w3-container">').appendTo(content);
    var close = $('<div class="w3-closebtn">&times;</div>')
      .appendTo(header).zIndex(1)
      .click(function() { modal.remove(); });
    content.page(params);
    var id = path.replace('/','_');
    content.one('read_'+id, function(event, object, options) {
      if (options.width != undefined) content.css('width', options.width);
      if (options.max_width != undefined) content.css('max-width', options.max_width);
      $('<div>').text(options.name).zIndex(0).appendTo(header);
      if (options.header_class) header.addClass(options.header_class.join(' '));
      if (options.class) content.addClass(options.class.join(' '));
      object.attr('title', options.name);
      modal.show();
      if (callback) callback();
    });
  }

  this.closeDialog = function(dialog, message)
  {
    if (message) alert(message);
    var parent = dialog.parents('.w3-modal').eq(0);
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
    while (match = regex.exec(this)) {
      matches.push(match[1]);
    }
    return matches;
  }
}


if (!RegExp.quote) {
  RegExp.quote = function(str) {
    return (str+'').replace(/[.?*+^$[\]\\(){}|-]/g, "\\$&");
  };
}
