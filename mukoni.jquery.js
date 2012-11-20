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


$.fn.values = function()
{
  var data = {};
  this.filter('input,textarea,select').each(function() {
    var ctrl = $(this);
    var name = ctrl.hasAttr('id')? ctrl.attr('id'): ctrl.attr('name');
    if (name === undefined) return true;
    if (ctrl.attr('type') == 'radio' && !ctrl.is(':checked')) return true;
    data[name] = ctrl.val();
  });
  return data;
}

$.send = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }

  options = $.extend({
    progress: 'Processing...',
    method: 'post',
    async: true,
    showResult: false,
    invoker: undefined,
    eval: true,
    data: {},
    error: undefined,
    event: undefined
  }, options);
  if (options.event !== undefined) options.async = false;
  var ret = this;
  if (options.invoker !== undefined) 
    options.invoker.prop('disabled', true);
  var progress =  {};
  if (options.progress !== false) {
    progress.box = $('.ajax_result');
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
    success: function(data) {
      var script = data.match(/<script[^>]+>(.+)<\/script>/);
      if (options.eval && script != null && script.length > 1) {
        eval(script[1]);
      }
      else {
        if ((options.showResult === true && data != '') || data[0] == '!') {
          if (progress.timeout !== undefined) clearTimeout(progress.timeout);
          
          var p = $('<p></p>');
          if(data[0] == '!') {
            p.html(data.substr(1));
            p.addClass('error');
            if (options.event !== undefined) {
              options.event.stopImmediatePropagation();
              ret = false;
            }
          }
          else 
            p.html(data);
          progress.box.html('')
            .append(p)
            .show();
          var timeout = setTimeout(function() {progress.box.fadeOut(2000);}, 8000);
                   
        }
        else if (progress.box !== undefined) 
          progress.box.hide();

      }
      if (callback !== undefined) callback(data, options.event);
      if (options.invoker !== undefined) options.invoker.prop('disabled', false);
      if (progress.timeout !== undefined) clearTimeout(progress.timeout);
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
  if (options !== undefined)
    options.data = $.extend($(this).values(), options.data);
  else
    options = {data : $(this).values()};
  return $.send(url, options, callback);  
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
  this.click(function(e) {
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
    if (callback != undefined)
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
  else if (result[0] == '!') {
    alert(result.substr(1));
    return false;
  }
  return this;
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

$.fn.setChildren = function(result)
{
  var self = this;
  $.each(result, function(key, val) {
    var filter = "#"+key+",[name='"+key+"']";
    self.find(filter).each(function() {
      if ($(this).is("a")) {
        var proto = $(this).attr('proto')==undefined? '': $(this).attr('proto');
        $(this).attr('href', proto+val);
      }
      else if ($(this).attr('value') === undefined)
        $(this).html(val);
      else
        $(this).val(val);
    });
  });
  return this;
}

$.fn.loadChildren = function(url, data, callback)
{
  var self = this;
  var result;
  $.getJSON(url, data, function(data) {
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