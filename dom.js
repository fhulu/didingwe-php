function getElementByName(name)
{
  var objs = document.getElementsByName(name);
  if (objs == null) return null;
  var obj = objs[0];
  if (obj == null) return null;
  if (obj.type != 'radio') return obj;
  for (var i=0; i<objs.length; ++i) {
    if (objs[i].checked) return objs[i];
  }
  return obj[0];
}

function selectRadioByValue(name, value)
{
  if (value=='' || value==null)
    return getElementByName(name);
  var objs = document.getElementsByName(name);
  if (objs == null) return null;
  var obj = objs[0];
  if (obj == null || obj.type != 'radio') return null;
  for (var i=0; i<objs.length; ++i) {
    obj = objs[i];
    if (obj.value == value) {
      obj.checked = true;
      return obj;
    }
  }
  return null;
}

function getElementByIdOrName(name)
{
  var obj = document.getElementById(name);
  return obj==null? getElementByName(name): obj;
}


function showHide(obj, show)
{
   obj.style.display = show?'':'none';
}

function showHideById(id, show)
{
  var x = document.getElementById(id);
  showHide(x, show);
}

function hide(obj)
{
  showHide(obj, false);
}

function show(obj)
{
  showHide(obj, true);
}

function hideById(id)
{
  showHideById(id, false);
}

function showById(id)
{
  showHideById(id, true);
}

function swapShow(obj1, obj2)
{
  toggle_show(obj1);
  toggle_show(obj2);
}

function swapShowById(id1, id2)
{
  swapShow(document.getElementById(id1), document.getElementById(id2));
}

function toggle_show(obj)
{
   obj.style.display = obj.style.display=='none'?'':'none';
}

function backOnReject(msg)
{
  if (!confirm(ms)) history.go(-1);
}

function make_nv_pair(name)
{
  var obj = getElementByIdOrName(name);
  if (obj != null) 
    return obj.name + "=" + obj.value;
  return name;

}

function params2pairs(params, pairer, separator)
{
  params = params.split(',');
  var pairs='';
  for (var i=0; i<params.length; ++i)  {
    var name = params[i];
    var range_spec = name.split(':');
    if (range_spec.length > 2) {
      name = range_spec[0];
      var first = range_spec[1];
      var last = range_spec[2];
      for (var j=first; j <= last; j++) {
        var obj = getElementByIdOrName(name + j);
        if (obj != null) 
          pairs += '&' + obj.name + "=" + obj.value;
      }
    }
    else {  
      pairs += '&' + make_nv_pair(name);
    }
  }
  return pairs.substr(1,pairs.length-1);
}
function params2url(params)
{ 
  return params2pairs(params, '=', '&');
}

function expand_url(url)
{ 
  var param_idx = url.indexOf('?')+1;
  if (param_idx > 0) 
    return url = url.substr(0, param_idx) + params2url(url.substr(param_idx));
  return url;
}

function params2json(params)
{
  return params2pairs(params, ':', ',');
}

function check_if(names, value)
{
  var result = false;
  names = names.split(',');
  var i;
  for (i=0; i<names.length; ++i) {
    var obj = getElementByName(names[i]);
    if (obj==null) {
      //alert(names[i]);
    }
    else if (obj.value == value) {
      result = true;
      break;
    }
  }
  return result;
}

function disable_if(result_id, names, value)
{
  var obj = getElementByIdOrName(result_id);
  obj.disabled = check_if(names, value);
}

function disable_unset(result_id, names)
{
  var obj = getElementByIdOrName(result_id);
  if (obj)
    obj.disabled = (check_if(names, 0) || check_if(names, ''));
}

function disable_if0(result_id, names)
{
  disable_if(result_id, names, 0);
}

function disable_if_empty(result_id, names)
{
  disable_if(result_id, names, '');
}


function byref()
{
  this.value;
  return this;
}

function url_params(url)
{
  var param_idx = url.indexOf('?')+1;
  if (param_idx > 0) 
    return url = url.substr(0, param_idx) + get_name_value(url.substr(param_idx));
  
  return url;
}

function checkAll(name, checked)
{
  var objs = document.getElementsByName(name);

  for (var i=0; i<objs.length; ++i) {
    objs[i].checked = checked;
  }
}

function getChecked(name, separator)
{
  var objs = document.getElementsByName(name);

  var checked = '';
  for (var i=0; i<objs.length; ++i) {
    if (objs[i].checked)
      checked += separator + objs[i].value;
  }
  return checked.substring(separator.length);
}


function swapShow(obj1, obj2)
{
  toggle_show(obj1);
  toggle_show(obj2);
}

function swapShowById(id1, id2)
{
  swapShow(document.getElementById(id1), document.getElementById(id2));
}

function set_inner(id, inner)
{
  var obj = getElementByIdOrName(id);
  obj.innerHTML = inner;
}

function set_value(id, value)
{
  var obj = getElementByIdOrName(id);
  obj.value = value;
}

function load_js(filename)
{
  var file=document.createElement('script');
  file.setAttribute("type","text/javascript");
  file.setAttribute("src", filename);
  document.getElementsByTagName('head').item(0).appendChild(file);
}

function parseScript(_source) 
{
  var source = _source;
  var scripts = new Array();
  
  // Strip out tags
  while(source.indexOf("<script") > -1 || source.indexOf("</script") > -1) {
    var s = source.indexOf("<script");
    var s_e = source.indexOf(">", s);
    var e = source.indexOf("</script", s);
    var e_e = source.indexOf(">", e);
    
    // Add to scripts array
    scripts.push(source.substring(s_e+1, e));
    // Strip from source
    source = source.substring(0, s) + source.substring(e_e+1);
  }
  
  // Loop through every script collected and eval it
  for(var i=0; i<scripts.length; i++) {
    try {
      eval(scripts[i]);
    }
    catch(ex) {
      // do what you want here when a script fails
    }
  }
  
  // Return the cleaned source
  return source;
}


function popup(URL, width, height) {
    var popup_width = width==undefined?500:width;
    var popup_height = height==undefined?600:height;
    day = new Date();
    id = day.getTime();
    eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=1,width='+popup_width+',height='+popup_height+'');");
}

function toggle_enable_bycheck(checkbox, id1, id2)
{
  var control1 = getElementByIdOrName(id1);
  var control2 = getElementByIdOrName(id2);
  control1.disabled = checkbox.checked==false;
  control2.disabled = checkbox.checked==true; 
}

function values_match(v1, v2, msg)
{
  if (getElementByIdOrName(v1).value == getElementByIdOrName(v2).value) return true;
  
  if (msg !== undefined) alert(msg);
  
  return false;
}

function OnSelect(dropdown, callback)
{
  var selectedIndex  = dropdown.selectedIndex;
  callback(dropdown.options[selectedIndex].value, dropdown.options[selectedIndex].text);
}

// set another control's value on select
function SetInnerOnSelect(dropdown, dest_id)
{ 
  var dest = document.getElementById(dest_id);
  OnSelect(dropdown, function(value, text) {
    dest.innerHTML = text;
  });
}

function SetValueOnSelect(dropdown, dest_id)
{ 
  var dest = document.getElementById(dest_id);
  OnSelect(dropdown, function(value, text) {
    dest.value = text;
  });
}

function insertAtCursor(myField, myValue) {

	//IE support

	if (document.selection) {

		myField.focus();

		sel = document.selection.createRange();

		sel.text = myValue;

	}

	//MOZILLA/NETSCAPE support

	else if (myField.selectionStart || myField.selectionStart == '0') {

		var startPos = myField.selectionStart;

		var endPos = myField.selectionEnd;

		myField.value = myField.value.substring(0, startPos)

		+ '' + myValue + ' '

		+ myField.value.substring(endPos, myField.value.length);
    
    + myField.focus();

	} else {

		myField.value += myValue;
	}

}
    
function preg_quote(str)
{
  return (str+'').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, "\\$1");
}

function is_valid_msisdn(msisdn)
{
  var control = getElementByIdOrName(msisdn);
  if (control.value.search(/^0[1-8][0-9]+$/) == -1) 
  {
    alert("Invalid Cell Number");
    return false;
  }
  return true;
}
 
