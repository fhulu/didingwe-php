
function create_ajax()
{
  if (ajax != null) return ajax;
  if (window.XMLHttpRequest) // code for IE7+, Firefox, Chrome, Opera, Safari
    ajax = new XMLHttpRequest();
  else ajax = new ActiveXObject("Microsoft.XMLHTTP");
  return ajax;
}
var ajax = create_ajax();

function ajax_call(url, async, func)
{
  ajax.onreadystatechange=func;
  ajax.open("GET", url, async);
  ajax.send();
}

function ajax_inner_cb(obj)
{
  if (ajax.readyState==4 && ajax.status==200) {
    parseScript(ajax.responseText);
    obj.innerHTML = ajax.responseText;
  }
}

function ajax_value_cb(obj)
{
  if (ajax.readyState==4 && ajax.status==200) {
    obj.value=ajax.responseText;
  }
}

function ajax_query(source, url, result_id)
{
  ajax_inner(result_id, url+"?q="+source.value);
}
/*
  sets an object's inner html from the output of an ajax call. 
  It can optionally sets the inner html to a fixed string while the ajax call is in progress.
  parameters:
    result_id : id of the object to be set
    url: url of the ajax call
    progress_str: optional string to be set to the object while ajax call is in progress
    progress_id: id of the object to set the progress string to while ajax in in progress. 
      Note that if this parameter is given, the progress string above does not affect
      the result object.
*/
function ajax_inner(result_id, url, progress_str, progress_id, sync)
{
  if (progress_str === true)
    sync = true;   // progress_str can also act as sync parameter
  else if (!(progress_str === undefined)) {
    if (progress_id === undefined) progress_id = result_id;
    var obj = getElementByIdOrName(progress_id);
    obj.innerHTML = '<div class=progress>' + progress_str + '.</div>';
    if (sync === undefined) sync = true;
  }
  else
    sync = false;

  var obj = getElementByIdOrName(result_id);
  if (obj == null) alert('No object with id ' + result_id);
  ajax_call(expand_url(url), !sync, function() { ajax_inner_cb(obj); });
}

function ajax_value(result_id, url, progress_str, progress_id, sync)
{
  if (!(progress_str === undefined)) {
    if (progress_id === undefined) progress_id = result_id;
    var obj = getElementByIdOrName(progress_id);
    obj.innerHTML = '<div class=progress>' + progress_str + '.</div>';
    sync = true;
  }
  var obj = getElementByIdOrName(result_id);
  ajax_call(expand_url(url), true, function() { ajax_value_cb(obj); });
}

function ajax_confirm(url)
{
  var confirmation;
  ajax_call(expand_url(url), false, function() { 
    if (ajax.readyState==4 && ajax.status==200) 
      confirmation = ajax.responseText;
  });
  
  if (confirmation.charAt(0) == '!') {
    alert(confirmation.substr(1));
    return false;
  }
  if (confirmation != '')
    return confirm(confirmation);
  return true;
}

function jq_submit(url, params, method, async)
{
  if (async == undefined) async = false;
  if (method == undefined) method = 'get';
  var result;
  var data = params == undefined? undefined: params2values(params);
  $.ajax({
    type: method,
    url: url,
    data: data,
    async: async,
    success: function(data) {
      result = data;
    }
  });

  return result;
}

function jq_confirm(url, params, event, method)
{
  var func_idx = url.lastIndexOf('/') + 1;
  var funcs = url.substr(func_idx);
  url = url.substr(0, func_idx);
  funcs = funcs.split(',');
  for (var i=0; i<funcs.length; ++i) {
    var func = funcs[i];
    var result = jq_submit(url+func, params, method);  
    if (result != undefined && result.charAt(0) == '!') {
      alert(result.substr(1));
      if (event != undefined) event.stopImmediatePropagation();
      return false;
    }
    result = $.trim(result);
    if (result != undefined && result != '') {
      if (!confirm(result)) {
        if (event != undefined) event.stopImmediatePropagation();
        return false;
      }
      return true;
    }
  }
  return true;
}

function ajax_mconfirm(url,params)
{
  var func_idx = url.lastIndexOf('/') + 1;
  var funcs = url.substr(func_idx);
  url = url.substr(0, func_idx);
  funcs = funcs.split(',');
  for (var i=0; i<funcs.length; ++i) {
    var func = funcs[i];
    func += func.indexOf('?') < 0? '?': '&';
    if (!ajax_confirm(url + func + params))
      return false;
  }
  return true;
}

function ajax_confirm_inner(result_id, url)
{
  if (ajax_confirm(url + ',confirm=ask'))
    ajax_inner(result_id, url);
}

function ajax_inner_progress(result_id, progress_id, progress_str, url)
{
  var obj = getElementByIdOrName(progress_id);
  obj.innerHTML = '<div class=progress>' + progress_str + '.</div>';
  obj = getElementByIdOrName(result_id);
  ajax_call(expand_url(url), false, function() { ajax_inner_cb(obj); });
}

$.fn.load = function(url, data, callback)
{
  var self = this;
  $.getJSON(url, data, function(result) {
    self.val(result);
    callback(result);
  });
  return this;
}

function load_text(parent, url, data, callback) // deprecated
{
  parent.loadChildren(url, data, callback);
}

$.fn.loadChildren = function(url, data, callback)
{
  var self = this;
  $.getJSON(url, data, function(result) {
    $.each(result, function(key, val) {
      var filter = "[name='"+key+"']";
      self.find(filter).each(function() {
        if ($(this).is("a")) {
          var proto = $(this).attr('proto')==undefined? '': $(this).attr('proto');
          $(this).attr('href', proto+val);
        }
        else if ($(this).attr('value') === undefined)
          $(this).text(val);
        else
          $(this).val(val);
      });
    });
    if (callback != undefined) callback(result);
  });
  return this;
}
