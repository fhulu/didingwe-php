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

/**
* array_merge_recursive does indeed merge arrays, but it converts values with duplicate
* keys to arrays rather than overwriting the value in the first array with the duplicate
* value in the second array, as array_merge does. I.e., with array_merge_recursive,
* this happens (documented behavior):
*
* array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
*     => array('key' => array('org value', 'new value'));
*
* array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
* Matching keys' values in the second array overwrite those in the first array, as is the
* case with array_merge, i.e.:
*
* array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
*     => array('key' => 'new value');
*
* Parameters are passed by reference, though only for performance reasons. They're not
* altered by this function.
*
* @param array $array1
* @param mixed $array2
* @author daniel@danielsmedegaardbuus.dk
* @return array
*/
function &array_merge_recursive_distinct(&$array1, &$array2 = null)
{
  if (!is_array($array1)) return $array2;
  if (!is_array($array2)) return $array1;
  
  $merged = $array1;
  
  if (is_array($array2))
    foreach ($array2 as $key => $val)
      if (is_array($array2[$key]))
        $merged[$key] = is_array($merged[$key]) ? array_merge_recursive_distinct($merged[$key], $array2[$key]) : $array2[$key];
      else
        $merged[$key] = $val;
  
  return $merged;
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
 
