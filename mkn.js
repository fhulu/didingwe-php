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
  }

  this.closeDialog = function(dialog, message)
  {
    if (message) alert(message);
    if (!dialog.hasClass('ui-dialog-content'))
      dialog = dialog.parents('.ui-dialog-content').eq(0);
    dialog.dialog('destroy').remove();
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

}
