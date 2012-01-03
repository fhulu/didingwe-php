
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
    //eval(ajax.responseText);
    obj.innerHTML=ajax.responseText;
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
  ajax_call(url_params(url), !sync, function() { ajax_inner_cb(obj); });
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
  ajax_call(url_params(url), true, function() { ajax_value_cb(obj); });
}

function ajax_confirm(url)
{
  var confirmation;
  ajax_call(url_params(url), false, function() { 
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
  ajax_call(url_params(url), false, function() { ajax_inner_cb(obj); });
}
