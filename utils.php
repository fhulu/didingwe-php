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

function null_merge($array1, $array2) 
{
  if (!is_array($array2)) return $array1;
  
  if (is_array($array1)) 
    return is_array($array2)? array_merge_recursive($array1, $array2): $array1;
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
  if (!preg_match_all('/\[^$]$(\w+)/', $str, $matches, PREG_SET_ORDER)) return  $str;

  foreach($matches as $match) {
    $key = $match[1];
    $value = $values[$key];
    if (isset($value))
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
  
