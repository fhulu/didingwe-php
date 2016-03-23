$.fn.exists = function()
{
  return this.get(0) != undefined;
}

$.fn.hasAttr = function(name)
{
  return this.attr(name) !== undefined;
}

$.fn.updateEnableOnSet = function(controls)
{
  var self = this;
  var set = 0;
  var mandatory = $(controls).filter(':visible').filter(':not([optional])');
  var total = mandatory.length;
  mandatory.each(function() {
    if ($(this).attr('type') == 'radio') {
      if ($(this).is(':checked')) {
        var name = $(this).attr('name');
        total -= mandatory.filter('[name='+name+']').length - 1;
        ++set;
      }
    }
    else if ($(this).val() != '') ++set;
  });
  self.prop('disabled', set < total);
}

$.fn.enableOnSet = function(controls, events)
{
  this.updateEnableOnSet(controls);
  controls = $(controls).filter('input,select,textarea')
  if (events === undefined) events = '';
  var self = this;
  controls.bind('keyup input cut paste click change '+events, function() {
    self.updateEnableOnSet(controls);
  });
  return this;
}


$.fn.filterText = function(text) {
  return this.filter(function() {
    return $(this).text() == text;
  });
}

$.fn.setValue = function(val)
{
  if (this.is("a")) {
    var proto = this.attr('proto')==undefined? '': this.attr('proto');
    if (val == null)
      this.attr('href', '');
   else
      this.attr('href', proto+val);
    return this;
  }
  var type = this.attr('type');
  if (type === 'checkbox') {
    var values = this.attr('values');
    if (values != '') val = values.indexOf(val);
    return this.prop('checked', val);
  }

  if (this.is('select')) {
    this.val(val);
    if (this.val() == val) return this;
    var option = this.children('option').filterText(val);
    if (!option.exists()) return this;
    if (this.attr('server') == val) this.attr('server', option.val());
    this.val(option.val());
    return this;
  }

  if (this.hasAttr('value'))
    return this.val(val);
  return this.html(val);
}

$.fn.getValue = function()
{
  var type = this.attr('type');
  if (type === 'checkbox') {
    var val = this.is(':checked')?1:0;
    var values = this.attr('values');
    if (values == '') return val;
    return values.split(',')[val];
  }
  if (type === 'radio') return this.filter(':checked').val();
  return this.is('input,select,textarea')? this.val(): this.text();
}

$.fn.value = function(val)
{
  if (val === undefined) return this.getValue();
  return this.setValue(val);
}

$.fn.values = function()
{
  var data = {};
  var delta = [];
  this.filter('input,textarea,select').each(function() {
    var ctrl = $(this);
    var name = ctrl.hasAttr('name')? ctrl.attr('name'): ctrl.attr('id');
    if (name === undefined) return true;
    var val;
    if (ctrl.attr('type') !== 'radio')
      val = ctrl.value();
    else if (ctrl.is(':checked'))
      val = ctrl.attr('value');
    var server = ctrl.attr('server');
    if (server !== undefined && server != val)
      delta.push(name);
    data[name] = val;
  });
  data.delta = delta.join();
  return data;
}

$.send = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }

  if (typeof request_method === 'undefined') request_method = 'post';
  options = $.extend({
    progress: 'Processing...',
    method: request_method,
    async: true,
    showResult: false,
    invoker: undefined,
    eval: true,
    data: {_fakeDataToAvoidCache: new Date() },
    dataType: undefined,
    error: undefined,
    event: undefined
  }, options);
  //if (options.event !== undefined) options.async = false;
  var ret = this;
  if (options.invoker !== undefined)
    options.invoker.prop('disabled', true);
  var progress =  {};
  if (options.progress !== false) {
    progress.box = $('.processing');
    progress.box.click(function() {
      $(this).fadeOut('slow');
    });

    progress.timeout = setTimeout(function() {
      progress.box.html('<p>'+options.progress+'</p').show();
    }, 500);

    if (options.error ===undefined) {
      options.error = function(jqHXR, status, text)
      {
        progress.box.html('<p class=error>Status:'+status+'<br>Text:'+text+'</p').show();
        if (options.event !== undefined) {
          options.event.stopImmediatePropagation();
          ret = false;
        }
      };
    }
  }
  $.ajax({
    type: options.method,
    url: url,
    data: options.data,
    async: options.async,
    error: options.error,
    cache: false,
    dataType: options.dataType,
    success: function(data) {
      if (progress.timeout !== undefined) clearTimeout(progress.timeout);
      if (progress.box !== undefined) progress.box.hide();
      if (callback !== undefined) callback(data, options.event);
      if (options.invoker !== undefined) options.invoker.prop('disabled', false);
    }
  });
  return ret;
}


$.fn.send = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }
  var data = $(this).values();
  if (options !== undefined)
    data = $.extend({}, options.data, data);

  options = $.extend({}, options, {data: data});
  return $.send(url, options, callback);
}

$.fn.json = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }
  var data = $(this).values();
  if (options !== undefined)
    data = $.extend({}, options.data, data);

  options = $.extend({}, options, {data: data});
  $.json(url, options, function(result){
    callback(result);
  });
  return this;
}

$.fn.sendOnSet = function(controls, url, options, callback)
{
  var self = this;
  if (options instanceof Function) {
    callback = options;
    options = undefined;
    this.enableOnSet(controls);
  }
  else if (options !== undefined && options.optional !== undefined)
    this.enableOnSet(controls).filter(':not('+options.optional+')');
  else this.enableOnSet(controls);

  this.click(function(e) {
    return $(controls).send(url, $.extend({invoker: self, event: e}, options), callback);
  });
  return this;
}

$.fn.sendOnClick = function(controls, url, options, callback)
{
  var self = this;
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }
  $(this).click(function(e) {
    return $(controls).send(url, $.extend({invoker: self, event: e}, options), callback);
  });
  return this;
}

$.fn.confirm = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {};
  }
  else if (options === undefined) {
    options = {};
  }
  options.async = false;
  var result;
  this.send(url, options, function(data) {
    if (callback !== undefined)
      callback(data);
    result = data;
  });
  if (result === false) return false;
  if (result === undefined) return this;
  result = $.trim(result);
  if (result[0] == '?') {
    if (!confirm(result.substr(1))) {
      if (options.event !== undefined) options.event.stopImmediatePropagation();
      return false;
    }
    return true;
  }
}

$.fn.confirmOnSet = function(controls,url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {};
  }

  var self = this;
  this.enableOnSet(controls);

  this.click(function(event) {
    var params = $.extend({invoker: self, event: event}, options);
    var result = $(controls).confirm(url, params);
    if (callback !== undefined)
      callback(result);
  });
}

$.fn.loadHtml = function(url, options, callback)
{
  var self = this;
  $.send(url, options, function(result) {
    self.html(result);
    if (callback) callback(result);
  });
  return this;
}

$.fn.loadHtmlByValues = function(url, values, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {};
  }
  options = $.extend(options, {data: $(values).values()});
  var self = this;
  $.send(url, options, function(result) {
    self.html(result);
    if (callback) callback(result);
  });
  return this;
}

$.fn.load = function(url, options, callback)
{
  var self = this;
  $.send(url, options, function(result) {
    self.replaceWith(result);
    if (callback) callback(result);
  });
  return this;
}

$.fn.loadOptions = function(url, options, callback)
{
  this.html('<option>loading...</option>').loadHtml(url, options, callback);
  return this;
}

$.json = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {dataType: 'json'};
  }
  else options = $.extend(options, {dataType: 'json'});
  return $.send(url, options, callback);
}

$.fn.jsonLoadOptions = function(url, options, callback)
{
  if (url instanceof Function) {
    callback = url;
    url = undefined;
    options = {};
  }
  else if (options instanceof Function) {
    callback = options;
    options = {};
  }
  var self = $(this);
  self.html('<option>loading...</option>');
  var thisUrl = url === undefined? "/?a=json/ref/items&list="+self.attr('list'): url;
  $.json(thisUrl, options, function(result) {
    self.html('');
    $.each(result, function(key, row) {
      self.append('<option f=t value='+row.item_code+'>'+row.item_name+'</option>');
    });
    var def = self.attr('default');
    if (def !== undefined) {
      var selected = self.find("[value='"+def+"']");
      if (selected.length == 0)
        selected = $('<option>'+def+'</option>').prependTo(self);
      selected.prop('selected', true);
    }
    if (callback !== undefined) callback(result);
  });
  return this;
}

$.fn.setChildren = function(result, server)
{
  var self = this;
  if (result === null) return;
  $.each(result, function(key, val) {
    var obj = self.find("#"+key+",[name='"+key+"']");
    if (server) obj.attr('server', val);
    obj.setValue(val);
  });
  return this;
}

$.fn.loadChildren = function(url, data, callback)
{
  var self = this;
  var result;
  $.json(url, data, function(data) {
    self.setChildren(data);
    result = data;
    if (callback != undefined) callback(result);
  });
  return this;
}

$(function(){
    $.each(["show","hide", "toggleClass", "addClass", "removeClass"], function(){
        var _oldFn = $.fn[this];
        $.fn[this] = function(){
            var hidden = this.find(":hidden").add(this.filter(":hidden"));
            var visible = this.find(":visible").add(this.filter(":visible"));
            var result = _oldFn.apply(this, arguments);
            hidden.filter(":visible").each(function(){
                $(this).triggerHandler("show");
            });
            visible.filter(":hidden").each(function(){
                $(this).triggerHandler("hide");
            });
            return result;
        }
    });
});

$.urlParam = function(name){
    var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
    return results[1] || 0;
}

$.fn.insertAtCursor = function(myValue)
{
  var pos = this.getCursorPosition();
  var val = this.val();
  this.val(val.substr(0, pos)+ ' ' +myValue+ ' '+val.substr(pos));
}

$.fn.getCursorPosition = function() {
    var el = $(this).get(0);
    var pos = 0;
    if('selectionStart' in el) {
        pos = el.selectionStart;
    } else if('selection' in document) {
        el.focus();
        var Sel = document.selection.createRange();
        var SelLength = document.selection.createRange().text.length;
        Sel.moveStart('character', -el.value.length);
        pos = Sel.text.length - SelLength;
    }
    return pos;
}

$.fn.bookmarkOnClick = function() {
  // Mozilla Firefox Bookmark
  this.click(function() {
    if ('sidebar' in window && 'addPanel' in window.sidebar) {
        window.sidebar.addPanel(location.href,document.title,"");
    } else if( /*@cc_on!@*/false) { // IE Favorite
        window.external.AddFavorite(location.href,document.title);
    } else { // webkit - safari/chrome
        alert('Press ' + (navigator.userAgent.toLowerCase().indexOf('mac') != - 1 ? 'Command/Cmd' : 'CTRL') + ' + D to bookmark this page.');
    }
  });
}

$.fn.customCreate = function(options)
{
  var create = options.create;
  if (create === undefined) return;
  this.attr('customCreate',create);
  this.run(create, options);
}

$.fn.run = function()
{
  var args = Array.prototype.slice.call(arguments);;
  var f = args.shift();
  this[f].apply(this, args);
}

$.fn.call = function(method, args) {
  this[method].apply(this, args);
}

/**
* @param scope Object :  The scope in which to execute the delegated function.
* @param func Function : The function to execute
* @param data Object or Array : The data to pass to the function. If the function is also passed arguments, the data is appended to the arguments list. If the data is an Array, each item is appended as a new argument.
* @param isTimeout Boolean : Indicates if the delegate is being executed as part of timeout/interval method or not. This is required for Mozilla/Gecko based browsers when you are passing in extra arguments. This is not needed if you are not passing extra data in.
*/
function delegate(scope, func, data, isTimeout)
{
    return function()
    {
        var args = Array.prototype.slice.apply(arguments).concat(data);
        //Mozilla/Gecko passes a extra arg to indicate the "lateness" of the interval
        //this needs to be removed otherwise your handler receives more arguments than you expected.
                //NOTE : This uses jQuery for browser detection, you can add whatever browser detection you like and replace the below.
        if (isTimeout && $.browser.mozilla)
            args.shift();

        func.apply(scope, args);
    }
}



function sleep(milliseconds) {
  var start = new Date().getTime();
  for (var i = 0; i < 1e7; i++) {
    if ((new Date().getTime() - start) > milliseconds){
      break;
    }
  }
}


function getQueryParams(qs) {
    qs = qs.split("+").join(" ");

    var params = {}, tokens,
        re = /[?&]?([^=]+)=([^&]*)/g;

    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])]
            = decodeURIComponent(tokens[2]);
    }

    return params;
}

$.jsonSize = function(object)
{
  var i=0;
  $.each(object, function() {++i});
  return i;
}

$.valid = function(object)
{
  return object !== null && object !== undefined;
}




function isNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}


function toTitleCase(str)
{
  str = str.replace(/[_\/]/g, ' ');
  return str.replace(/\w\S*/g,  function (txt) {
    return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
  });
}


// from stackoverflow: Mathias Bynens
function getMatches(string, regex, index) {
    index || (index = 1); // default to the first capturing group
    var matches = [];
    var match;
    while (match = regex.exec(string)) {
        matches.push(match[index]);
    }
    return matches;
}

// Gary Haran => gary@talkerapp.com
// This code is released under MIT licence
(function($) {
  var replacer = function(finder, replacement, element, blackList) {
    if (!finder || typeof replacement === 'undefined') {
      return
    }
    var regex = (typeof finder == 'string') ? new RegExp(finder, 'g') : finder;
    var childNodes = element.childNodes;
    var len = childNodes.length;
    var list = typeof blackList == 'undefined' ? 'html,head,style,title,link,meta,script,object,iframe,pre,a,' : blackList;
    while (len--) {
      var node = childNodes[len];
      if (node.nodeType === 1 && true || (list.indexOf(node.nodeName.toLowerCase()) === -1)) {
        replacer(finder, replacement, node, list);
      }
      if (node.nodeType !== 3 || !regex.test(node.data)) {
        continue;
      }
      var frag = (function() {
        var html = node.data.replace(regex, replacement);
        var wrap = document.createElement('span');
        var frag = document.createDocumentFragment();
        wrap.innerHTML = html;
        while (wrap.firstChild) {
          frag.appendChild(wrap.firstChild);
        }
        return frag;
      })();
      var parent = node.parentNode;
      parent.insertBefore(frag, node);
      parent.removeChild(node);
    }
  }
  $.fn.replace = function(finder, replacement, blackList) {
    return this.each(function() {
      replacer(finder, replacement, $(this).get(0), blackList);
    });
  }
})(jQuery);

function assert(condition, message) {
  if (!condition) {
    message = message || "Assertion failed";
    console.log(message);
    if (typeof Error !== "undefined") {
      throw new Error(message);
    }
    throw message; // Fallback
  }
}

function rgbToHex(color) {
    if (color.substr(0, 1) === "#") {
        return color;
    }
    var nums = /(.*?)rgb\((\d+),\s*(\d+),\s*(\d+)\)/i.exec(color);
    if (!nums) return color;
    var r = parseInt(nums[2], 10).toString(16);
    var g = parseInt(nums[3], 10).toString(16);
    var b = parseInt(nums[4], 10).toString(16);
    return "#"+ (
        (r.length == 1 ? "0"+ r : r) +
        (g.length == 1 ? "0"+ g : g) +
        (b.length == 1 ? "0"+ b : b)
    );
}

function darken( hexColor, factor ) {
   if ( factor < 0 ) factor = 0;

   var c = hexColor;
   if( c.substr(0,1) == "#" ){
     c = c.substring(1);
   }

   if( c.length == 3 || c.length == 6 ){
     var i = c.length / 3;
     var f;  // the relative distance from white

     var r = parseInt( c.substr(0, i ), 16 );
     f = ( factor * r / (256-r) );
     r = Math.floor((256 * f) / (f+1));

     r = r.toString(16);
     if ( r.length == 1 ) r = "0" + r;

     var g = parseInt(c.substr(i, i), 16);
     f = (factor * g / (256 - g));
     g = Math.floor((256 * f) / (f + 1));
     g = g.toString(16);
     if (g.length == 1)
     g = "0" + g;

     var b = parseInt(c.substr(2 * i, i), 16);
     f = (factor * b / (256 - b));
     b = Math.floor((256 * f) / (f + 1));
     b = b.toString(16);
     if (b.length == 1)
     b = "0" + b;
     c = r + g + b;
   }
  return "#" + c;
 }


$.fn.valueFromCurrency = function()
{
  return this.value().replace(/[^\d.]/g,'');
}
// from stackoverflow: Anurag
$.fn.bindFirst = function(name, fn)
{
  // bind as you normally would
  // don't want to miss out on any jQuery magic
  this.on(name, fn);

  // Thanks to a comment by @Martin, adding support for
  // namespaced events too.
  this.each(function() {
      var handlers = $._data(this, 'events')[name.split('.')[0]];
      // take out the handler we just inserted from the end
      var handler = handlers.pop();
      // move it at the beginning
      handlers.splice(0, 0, handler);
  });
};
