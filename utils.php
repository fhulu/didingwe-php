<?php

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


function null_merge($array1, $array2, $recurse = true) 
{
  if (!is_array($array2)) return $array1;

  
  if (is_array($array1)) 
    return $recurse? array_merge_recursive($array1, $array2): array_merge($array1, $array2);

  return $array2;
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

function replace_vars($str, $values=null)
{
  if (is_null($values)) $values = $_REQUEST;
  $matches = array();
  if (!preg_match_all('/\$(\w+)/', $str, $matches, PREG_SET_ORDER)) return  $str;

  foreach($matches as $match) {
    $key = $match[1];
    $value = at($values, $key);
    if (!is_null($value))
      $str = str_replace('$'.$key, $value, $str);
  }
  return $str;
}

function is_assoc($array) {
  return (bool)count(array_filter(array_keys($array), 'is_string'));
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
 

function merge_options($options1, $options2)
{
  if (!is_array($options1)|| $options1 == $options2) return $options2;
  if (!is_array($options2)) return $options1;
  if (!is_assoc($options1)) return array_merge($options1, $options2);

  $result = $options2;
  foreach($options1 as $key=>$value ) {
    if (!array_key_exists($key, $result)) {
      $result[$key] = $value;
      continue;
    }
    if (!is_array($value)) continue;
    $value2 = $result[$key];
    if (!is_array($value2)) continue;
    $result[$key] = merge_options($value, $value2);
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