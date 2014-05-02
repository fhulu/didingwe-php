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
    var name = ctrl.hasAttr('name')? ctrl.attr('name'): ctrl.attr('id');
    if (name === undefined) return true;
    var type = ctrl.attr('type');
    var val = ctrl.val();
    if ((type === 'radio' || type === 'checkbox') && !ctrl.is(':checked')) return true;
    if (type === 'checkbox') {
      if (data[name] !== undefined) 
        data[name] = data[name] + ',' + val;
      else
        data[name] = val;
    }
    else if (val === undefined)
      data[name] = ctrl.text();
    else
      data[name] = val;
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
    data: {_fakeDataToAvoidCache: new Date() },
    dataType: undefined,
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
    cache: false,
    dataType: options.dataType,
    success: function(data) {
      if (data != null) {
        if (data.script !== undefined && options.eval) {
          eval(data.script);
        }
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

$.fn.json = function(url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = undefined;
  }
  if (options !== undefined)
    options.data = $.extend($(this).values(), options.data);
  else
    options = {data : $(this).values()};
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

$.reportError = function(field, error)
{
  var sibling = $('#'+field+",[name='"+field+"']").parent('a');
  if (sibling.length == 0)
    sibling = $('#'+field+",[name='"+field+"']");
  if (sibling.length == 0) {
    if (field == "alert") alert(error);
    return;
  }
  var box = $("<div class=error>"+error+"</div>");
  sibling.after(box);
  box.fadeIn('slow');
}

$.reportFirstError = function(event, result)
{
  $.each(result, function(key, row) {
    if (key == 'errors') {
      $.each(row, function(field, error) {
        $.reportError(field, error);
        event.stopImmediatePropagation();
        return false;
      });
      return false;
    }
  });
}

$.reportAllErrors = function(event, result)
{
  $('.error').remove();
  $.each(result, function(key, row) {
    if (key == 'errors') {
      $.each(row, function(field, error) {
        $.reportError(field, error);
      });
      event.stopImmediatePropagation();
    }
  });
}


$.fn.checkOnClick = function(controls,url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {};
  }

  var self = this;
  
  this.click(function(event) {
    var params = $.extend({invoker: self, event: event, async: false }, options);
    $(controls).siblings(".error").remove();
    $(controls).json(url, params, function(result) {
      if (result != null)
        $.reportAllErrors(event, result);
      if (callback !== undefined)
        callback(result);      
    });
  });
  return this;
}

$.fn.asyncCheckOnClick = function(controls,url, options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {};
  } 
  else if (options=== undefined)
    options = {};
  options.async = true;
  return this.checkOnClick(controls, url, options, callback);
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
  return this.each(function() {
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
  });
}

$.fn.setChildren = function(result)
{
  var self = this;
  $.each(result, function(key, val) {
    var filter = "#"+key+",[name='"+key+"']";
    self.find(filter).each(function() {
      if ($(this).is("a")) {
        var proto = $(this).attr('proto')==undefined? '': $(this).attr('proto');
        if (val == null)
          $(this).attr('href', '');
       else
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

  $.fn.loadForm = function(callback)
{
  var self = $(this);
  var code = self.attr('code');
  var selector;
  if (code === undefined) {
    code = self.attr('id');
    selector = '#'+code+' *';
  }
  else
    selector = '[code="'+code+'"] *';
  $.json('/?a=form/load&code='+code, function(data) {
    var attr = data.attributes;
    var title = attr.title;
    document.title = attr.program + ' - ' + title;
    self.append($('<p class=title></p>').text(title));
    self.addClass(attr.class);
    self.addClass(attr.label_position);
    self.append($('<p class=desc></p>').text(attr.desc));
    self.append($('<span class=ajax_result></span>'));
    
    var fields = $('<div></div>');
    fields.addClass(attr.fields_class);
    $.each(data.fields, function(field, prop) {
      var label = $('<p></p>');
      if (attr.label_position !='inplace') label.text(prop.name);
      if (prop.optional == 0) label.text('* ' + label.text());
      fields.append(label);
      
      var anchor = $('<a></a>');
      var input;
      if (prop.input == "text" || prop.input == "password") {
        input = $('<input type='+prop.input+'></input>');
        if (attr.label_position == 'inplace')
          input.attr('placeholder', prop.name);
      }
      else if (prop.input == 'dropdown') {
        input = $('<select></select>');
        input.attr('list', prop.reference);
        input.attr('default', '--Select '+prop.name+'--');
      }
      else if (prop.input == 'paragraph') {
        input = $('<textarea></textarea');
        input.height(prop.size);
      }
      else {
        input = $("<label></label>");
      }
      input.attr(attr.method === 'post'?'name':'id', field);
      anchor.append(input);
      anchor.append($('<span></span>').text(prop.desc));
      if (prop.visible == 0) {
        label.hide();
        anchor.hide();
      }
      fields.append(anchor);
    });
    
    var actions = $('<div class=actions></div>');
    var input;
    $.each(data.actions, function(action, prop) {
      if (prop.input == "button") {
        input = $('<button></button>'); 
        if (prop.method == "check")
          input.checkOnClick(selector, prop.reference);
        else if (prop.method = 'link')
          input.click(function() { location.href = prop.reference; })
      }
      else if (prop.input == 'link')
        input = $("<a href='"+prop.reference+"'></a>");
      input.attr('title',prop.desc);
      input.attr('id', action);
      input.text(prop.name);
      if (prop.visible == 0) input.hide();
      actions.append(input);
    });
    if (input != undefined) {
      fields.append($('<p></p>'));
      fields.append(actions);
    }
    self.append(fields);
    var lists = fields.find('select[list]');
    var lists_loaded = 0;
    var index = location.href.indexOf("&");
    var key = index >= 0? location.href.substr(index+1): '';
    var url = attr.data_url != null? attr.data_url+key: null;
    lists.jsonLoadOptions(function() {
      if (++lists_loaded == lists.length) {
        if (url != null) self.loadChildren(url);
        if (callback !== undefined) callback();
      }
    });
    if (lists.length == 0) {
      if (url != null) self.loadChildren(url);
      if (callback !== undefined) callback();
    }
      
  });
}

$.fn.loadWizard = function()
{
  var self = $(this);
  var id = self.attr('id');
  var selector = '#'+id;
  $.json('/?a=form/load_wizard&code='+id, function(data) {
    var index = 0;
    var done = 0;
    $.each(data.forms, function(id, form) {
      var div = $('<div></div>');
      div.attr('caption', form.title);
      div.attr('id', id);
      if (index > 1 && form.show_back == 1) div.attr('back','');
      if (++index < data.size && form.show_next == 1) div.attr('next','');
      self.append(div); //todo: order may be broken if an earlier form takes longer than a later one
      div.loadForm(function() {
        if (++done != data.size) return;
        
        self.pageWizard({title: data.program});
        $.each(data.forms, function(id, form) {
          if (form.next_action != null) 
            $('.'+id+'_next').checkOnClick(selector+' *', form.next_action);
        });
        $(selector+ ' .title').hide();
      });
    });
    
  });
}
