<?php
$config = load_yaml(".config.yml", true);

function at($array, $index)
{
  return isset($array[$index])? $array[$index]: null;
}

function GET($item) { return at($_GET, $item); }
function POST($item) { return at($_POST, $item); }
function REQUEST($item) { return at($_REQUEST, $item); }
function SESSION($item) { return at($_SESSION, $item); }
function null_at($array, $index) { return is_null(at($array,$index)); }
function valid_at($array, $index) { return !is_null(at($array,$index)); }

function set_valid(&$dest, $source, $index = null)
{
  if (is_null($source)) return $dest;
  if (is_null($index)) return $dest = $source;
  $val = $source[$index];
  if (!is_null($val)) $dest[$index] = $val;
}

function last($array)
{
  if (is_null($array)) return null;
  $length = is_array($array)?sizeof($array): strlen($array);
  return at($array, $length-1);
}


function null_merge($array1, $array2)
{
  if (is_null($array2)) return $array1;
  if (!is_array($array2)) return $array2;
  if ($array2[0] == '_reset')  return array_slice($array2, 1);
  return is_array($array1)? array_merge($array1, $array2): $array2;
}

function merge_to(&$array1, $array2)
{
  return $array1 = null_merge($array1, $array2);
}

function remove_nulls(&$array)
{
  if (!is_array($array)) return $array;
  foreach($array as $key=>$value) {
    if (is_null($value)) unset ($array[$key]);
  }
  return $array;
}

function replace_vars($str, $values, $callback=null)
{
  $matches = [];
  if (preg_match('/^\$([a-z_]\w*)$/i', $str, $matches)) {
    $key = $matches[1];
    if (!isset($values[$key])) return $str;
    $value = $values[$key];
    if ($callback && $callback($value, $key) === false) return $str;
    return $value;
  }
  if (!preg_match_all('/\$([a-z_]\w*)/mi', $str, $matches, PREG_SET_ORDER)) return  $str;

  foreach($matches as $match) {
    $key = $match[1];
    if (!isset($values[$key])) continue;
    $value = $values[$key];
    if ($callback && $callback($value, $key) === false) continue;
    $value = str_replace('$', '{%}',$value);
    $str = preg_replace('/\$'.$key.'(\W?)/',$value.'$1', $str);
    $str = str_replace('{%}', '$',$str);
  }
  return $str;
}

function replace_vars_except($str, $values, $exceptions)
{
  return replace_vars($str, $values, function($v, $key) use ($exceptions) {
    return !in_array($key, $exceptions, true);
  });
}

function is_assoc($array)
{
  if (is_null($array) || !is_array($array) || sizeof($array) == 0) return false;
  return !(bool)count(array_filter(array_keys($array), 'is_int'));
}

function compress_array($array)
{
  $compressed = array();
  foreach($array as $key=>$value) {
    if ($value != '') return $array;
    $compressed[] = $key;
  }
  return $compressed;
}

function caught_error($errNo, $errStr, $errFile, $errLine) {
  $msg = "$errStr in $errFile on line $errLine";
  if ($errNo == E_NOTICE) return;
  throw new ErrorException($msg, $errNo);
//  echo $msg;
}

function caught_fatal(){

    $error = error_get_last();

    if($error && ($error['type'] & E_FATAL)){
      caught_error($error['type'], $error['message'], $error['file'], $error['line']);
    }

}
set_error_handler('caught_error');

register_shutdown_function('caught_fatal');


function merge_options()
{
  $merge = function($options1, $options2) use(&$merge) {
    if (!is_array($options1)|| $options1 == $options2) return $options2;
    if (!is_array($options2)) return $options1;
    if (!is_assoc($options1) && !is_assoc($options2)) {
      if ($options2[0] != '_reset') return array_merge($options1, $options2);
      array_shift($options2);
      return $options2;
    }
    $result = $options2;
    foreach($options1 as $key=>$value ) {
      if (!array_key_exists($key, $result)) {
        $result[$key] = $value;
        continue;
      }
      if (!is_array($value)) continue;
      $value2 = $result[$key];
      if (!is_array($value2)) continue;
      $result[$key] = $merge($value, $value2);
    }
    return $result;
  };

  $args = func_get_args();

  $result = array_shift($args);
  while(sizeof($args) > 0) {
    $next = array_shift($args);
    $result = $merge($result, $next);
  }
  return $result;
}

function choose_value(&$array)
{
  $args = func_get_args();
  array_shift($args);
  foreach ($args as $arg) {
    if ($array[$arg] != '') return $array[$arg];
  }
  return null;
}

function walk_recursive(&$array, $callback, $done_callback = null)
{
  foreach($array as $key=>&$value) {
    if (is_array($value))
      walk_recursive ($value, $callback, $done_callback);
    $callback($value, $key, $array);
  }
  if ($done_callback)
    $done_callback($array);
}

function walk_recursive_down(&$array, $callback, $done_callback = null)
{
  foreach($array as $key=>&$value) {
    $result = $callback($value, $key, $array);
    if ($result !== false && is_array($value))
      walk_recursive_down ($value, $callback, $done_callback);
  }
  if ($done_callback)
    $done_callback($array);
}

function walk_leaves(&$array, $callback)
{
  foreach($array as $key=>&$value) {
    if (is_array($value))
      walk_leaves($value, $callback);
    else
      $callback($value, $key, $array);
  }
}

function assoc_element($element)
{
  if (!is_array($element)) return array($element);
  foreach($element as $key=>$value) {};
  return array($key, $value);
}

function replace_fields(&$options, $context, $callback=null)
{
  if (!is_array($options)) {
    $options = replace_vars($options, $context, $callback);
    return;
  }
  array_walk_recursive($options, function(&$value) use(&$context, $callback) {
    $value = replace_vars($value, $context, $callback);
  });
}

function replace_indices($str, $values)
{
  if (is_null($values)) $values = $_REQUEST;
  $i = 1;
  foreach($values as $value) {
    $str = str_replace('$'.$i, $value, $str);
    ++$i;
  }
  return $str;
}

function replace_field_indices(&$options, $values)
{
  array_walk_recursive($options, function(&$value) use(&$values) {
    $value = replace_indices($value, $values);
  });
}

function replace_keys($array, $key1, $key2)
{
    $keys = array_keys($array,null, true);
    $index = array_search($key1, $keys);

    if ($index !== false) {
        $keys[$index] = $key2;
        $array = array_combine($keys, $array);
    }

    return $array;
}

function find_assoc_element($array, $key)
{
  $index = 0;
  foreach($array as $element) {
    list($k, $value) = assoc_element($element);
    if ($k == $key) return array($value, $index);
    ++$index;
  }
  return null;
}

function expand_function($func)
{
  $matches = array();
  if (!preg_match('/^([\w:]+)(?:\((.*)\))?$/sm', trim($func), $matches))
    throw new Exception("Invalid function specification --$func--");

  array_shift($matches);
  return $matches;
}

function array_find(&$array, $callback)
{
  foreach ($array as $key=>&$value) {
    if ($callback($value, $key)) return $key;
  }
  return false;
}

function array_compact(&$array)
{
  if (!is_assoc($array))
    $array = array_values($array);
}

function load_yaml($file, $must_exist=false)
{
  log::debug("YAML LOAD $file");
  if (!file_exists($file)) {
    if (!$must_exist) return null;
    throw new Exception ("File $file does not exist");
  }

  $data = yaml_parse_file($file);
  if (is_null($data))
    throw new Exception ("Unable to parse file $file");
  return $data;
}

function array_remove_value($array, $value)
{
  $key = array_search($value, $value);
  if ($key === false) return $array;
  unset($array[$key]);
  return array_values($array);
}

function to_array($obj)
{
  $keys = array_slice(func_get_args(),1);
  $array = array();
  foreach($keys as $key) {
    $array[] = $obj[$key];
  }
  return $aray;
}


function email_to_array($str) {
  $matches = [];
  if (!preg_match('/([\w.\s-]+)<(\w+@\w+(?:\.\w+)+)>|([\w+.-]*@[\w-]+(?:\.[\w-]+)+)/', $str, $matches)) 
    return [];
  return sizeof($matches) == 3? [$matches[2], $matches[1] ]: [$matches[3] ] ;
}