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
  controls = $(controls).filter(':visible').filter('input,select,textarea');
  if (events === undefined) events = '';
  var self = this;
  controls.bind('keyup input cut paste click change '+events, function() {
    var set = 0;
    controls.each(function() {
      if ($(this).val() != '') ++set;
    });
    if (set < controls.length)
      self.attr('disabled', 'disabled');
    else
      self.removeAttr('disabled');
  });
}


$.fn.values = function()
{
  var data = {};
  $(this).filter('input,textarea,select').each(function() {
    var ctrl = $(this);
    var name = ctrl.hasAttr('id')? ctrl.attr('id'): ctrl.attr('name');
    if (name === undefined) return true;
    var type = ctrl.attr('type');
    if (type=='radio') ctrl = ctrl.filter(':checked');
    data[name] = ctrl.val();
  });
  return data;
}

$.fn.submit = function(url, options, callback)
{
  var data = $(this).values();
  var method = options.method === undefined? 'post': method;
  var async = options.async === undefined? true: false;
  var result;
  $.ajax({
    type: method,
    url: url,
    data: data,
    async: async,
    success: function(data) {
      var script = data.match(/<script[^>]+>(.+)<\/script>/);
      if (script != null && script.length > 1) {
        eval(script[1]);
        return;
      }
      result = data;
      if (callback !== undefined)
        callback(result);
    }
  });
  return result;
}

$.fn.confirm = function(url, options, callback)
{
  var event = options.event;
  options.async = false;
  $(this).submit(url, options, function(result) {
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

(function($){
	$.extend($.ui.datePicker.prototype, {
		//Custom Dialog Functions go in here
	});
})(jQuery);