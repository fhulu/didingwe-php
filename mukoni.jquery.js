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
  }, options);
   
  if (options.invoker !== undefined) 
    options.invoker.prop('disabled', true);
  var progress_box = $('.ajax_result');
  var done = false;
  if (options.progress !== false) {
    setTimeout(function() {
      if (!done)
        progress_box.html('<p>'+options.progress+'</p').show();
    }, 500);
    if (options.error ===undefined) {
      options.error = function(jqHXR, status, text) 
      {
        progress_box.html('<p class=error>'+text+'</p').show();        
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
        if (options.showResult === true && data != '' || data[0] == '!') {
          var p = $('<p></p>');
          if(data[0] == '!') {
             p.html(data.substr(1));
             p.addClass('error');
          }
          else 
            p.html(data);
          progress_box.html('')
            .append(p)
            .show()
            .delay(4000)
            .fadeOut(2000);
        }
        else if (options.progress !== false)
          progress_box.hide();
      }
      if (callback !== undefined) callback(data);
      if (options.invoker !== undefined) options.invoker.prop('disabled', false);
    }
  });
  return this;
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
  this.enableOnSet(controls);
  this.click(function() {
    $(controls).send(url, $.extend({invoker: self}, options), callback);
  });
}

$.fn.confirm = function(url, options, callback)
{
  options.async = false;
  this.send(url, options, function(result) {
    if (result === undefined) return true;
    var event = options.event;
    if (result[0] == '!') {
      if (event !== undefined) event.stopImmediatePropagation();
      return false;
    }
    result = $.trim(result);
    if (result[0] == '?') {
      if (!confirm(result)) {
        if (event !== undefined) event.stopImmediatePropagation();
        return false;
      }
      return true;
    }  
  });
}

$.fn.confirmOnSet = function(controls,url, options, callback)
{
  var self = this;
  this.enableOnSet(controls);
  this.click(function(event) {
    var params = $.extend({invoker: self, event: event}, options);
    $(controls).confirm(url, params, callback);
  });
}
