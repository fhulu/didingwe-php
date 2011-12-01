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

function make_name_pair(name)
{
  var obj = getElementByIdOrName(name);
  if (obj != null) 
    return obj.name + "=" + obj.value;
  return name;

}

function get_name_value(params)
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
        //pairs += '&' + make_name_pair(name + j);
        var obj = getElementByIdOrName(name + j);
        if (obj != null) 
          pairs += '&' + obj.name + "=" + obj.value;
      }
    }
    else {  
      pairs += '&' + make_name_pair(name);
    }
  }
  return pairs.substr(1,pairs.length-1);
}


function check_if(names, value)
{
  var result = false;
  names = names.split(',');
  var i;
  for (i=0; i<names.length; ++i) {
    var obj = getElementByName(names[i]);
    if (obj==null) {
      alert(names[i]);
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
