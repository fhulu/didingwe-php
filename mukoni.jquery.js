$.fn.exists = function()
{
  return this.get(0) != undefined;
}

$.fn.hasAttr = function(name) 
{  
  return this.attr(name) !== undefined;
}

$.fn.enableOnSet = function(controls, events)
{
  this.attr('disabled','disabled');
  controls = $(controls)
    .filter(':visible')
    .filter('input,select,textarea')
  if (events === undefined) events = '';
  var self = this;
  controls.bind('keyup input cut paste click change '+events, function() {
    var set = 0;
    var total = controls.length;
    controls.each(function() {
      if ($(this).attr('type') == 'radio') {
        if ($(this).is(':checked')) {
          var name = $(this).attr('name');
          total -= controls.filter('[name='+name+']').length - 1;
          ++set;
        }
      }
      else if ($(this).val() != '') ++set;
    });
    self.prop('disabled', set < total);
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
  var progress_box = $('.ajax_result');
  var done = false;
  if (options.progress !== false) {
    setTimeout(function() {
      if (!done)
        progress_box.html('<p>'+options.progress+'</p').show();
    }, 1000);
    if (options.error ===undefined) {
      options.error = function(jqHXR, status, text) 
      {
        progress_box.html('<p class=error>Status:'+status+'<br>Text:'+text+'</p').show();        
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
      done = true;
      var script = data.match(/<script[^>]+>(.+)<\/script>/);
      if (options.eval && script != null && script.length > 1) {
        eval(script[1]);
      }
      else {
        if ((options.showResult === true && data != '') || data[0] == '!') {
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
          progress_box.html('')
            .append(p)
            .show()
            .delay(Math.min(8000,Math.max(4000,p.text().length*50)))
            .fadeOut(2000);
        }
        else if (options.progress !== false)
          progress_box.hide();
      }
      if (callback !== undefined) callback(data);
      if (options.invoker !== undefined) options.invoker.prop('disabled', false);
    }
  });
  return ret;
}


$.fn.send = function(url, options, callback)
{
  if (options !== undefined)
    options.data = $.extend($(this).values(), options.data);
  return $.send(url, options, callback);  
}

$.fn.sendOnSet = function(controls, url, options, callback)
{
  var self = this;
  if (options !== undefined && options.optional !== undefined)
    this.enableOnSet($(controls).filter(':not('+options.optional+')'));
  else this.enableOnSet(controls);
  
  this.click(function(e) {
    return $(controls).send(url, $.extend({invoker: self, event: e}, options), callback);
  });
  return this;
}

$.fn.confirm = function(url, options, callback)
{
  options.async = false;
  var result;
  this.send(url, options, function(data) { result = data; });
  if (callback != undefined)
    callback(result);
  if (result === false) return false;
  if (result === undefined) return this;
  result = $.trim(result);
  if (result[0] == '?') {
    if (!confirm(result)) {
      if (options.event !== undefined) options.event.stopImmediatePropagation();
      return false;
    }
  }
  return this;
}

$.fn.confirmOnSet = function(controls,url, options, callback)
{
  var self = this;
  this.enableOnSet(controls);
  return this.click(function(event) {
    var params = $.extend({invoker: self, event: event}, options);
    $(controls).confirm(url, params, callback);
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