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

$.submit = function(url, options, callback)
{
  options = $.extend({
    progress: 'Processing...',
    method: 'post',
    async: true,
    showResult: false,
    invoker: undefined,
    eval: true,
    data: {},
    error: function() {}
  }, options);
   
  if (options.invoker !== undefined) 
    options.invoker.prop('disabled', true);
  var progress_box = $('.ajax_result');
  if (options.progress !== false)
    progress_box.html('<p>'+options.progress+'</p').show();
   
  $.ajax({
    type: options.method,
    url: url,
    data: options.data,
    async: options.async,
    success: function(data) {
      var script = data.match(/<script[^>]+>(.+)<\/script>/);
      if (options.eval && script != null && script.length > 1) {
        eval(script[1]);
      }
      else {
        if (options.showResult === true && data != '' || data[0] == '!') {
          var p = $('<p></p>');
          if(data[0] == '!') {
             p.html(data.substr(1));
             p.css('border','1px solid red');
          }
          else 
            p.html(data);
          progress_box.html('')
            .append(p)
            .show(3000)
            .delay(3000)
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


$.fn.submit = function(url, options, callback)
{
  options.data = $.extend($(this).values(), options.data);
  return $.submit(url, options, callback);  
}

$.fn.submitOnSet = function(controls, url, options, callback)
{
  var self = this;
  this.enableOnSet(controls);
  this.click(function() {
    $(controls).submit(url, $.extend({invoker: self}, options), callback);
  });
}
$.fn.confirm = function(url, options, callback)
{
  options.async = false;
  this.submit(url, options, function(result) {
    var event = options.event;
    if (result !== undefined && result.charAt(0) == '!') {
      alert(result.substr(1));
      if (event !== undefined) event.stopImmediatePropagation();
      return false;
    }
    result = $.trim(result);
    if (result !== undefined && result != '') {
      if (!confirm(result)) {
        if (event !== undefined) event.stopImmediatePropagation();
        return false;
      }
      return true;
    }  
  });
}

