<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of form
 *
 * @author fhulu
 */

require_once 'db.php';
require_once 'validator.php';
require_once 'ref.php';

class page
{
    
  var $fields;
  var $request;
  var $validator;
  
  function __construct($request=null)
  {
    if (is_null($request)) $request = $_REQUEST;
    $this->fields = array();
    $this->request = $request;
    //todo: fix coupling validator with reporting to front end
    $this->validator = new validator($request);
  }
  
  static function read($request, $echo=true)
  {
    $page = new page($request);
    $field = array('program' => config::$program_name);
    $page->read_request('page', $echo, $field);
    if (!$echo) return $field;
    page::empty_fields($field);
    echo json_encode($field);
  }
  
  function read_request($code, $expand_html=false, &$field=null)
  {
    return $this->read_field($this->request[$code], $expand_html, $field);
  }

  function read_field($code, $expand_html=false, &$field=null)
  {
    if (!array_key_exists($code, $this->fields)) {
      global $db;
      $data =  $db->read_one(
        "select f.name, f.description 'desc', ifnull(f.html,ft.html) html, ft.options __type_options, f.options __field_options"
        . " from field f left join field_type ft on f.type = ft.code"
        . " where f.code = '$code'"
        . " union "
        . " select name, description 'desc', html, null __type_options, options __field_options"
        . " from field_type where code = '$code'"
        , MYSQLI_ASSOC);

      $this->fields[$code] = remove_nulls($data);      
    }
    
    if (!is_array($field) && is_null($this->fields[$code])) return $field;
    
    $field = null_merge($this->fields[$code], $field);
    
    if (is_array($field)) 
      $this->decode($field, $expand_html);
    if ($expand_html)
      $this->expand_html($field);
    return $field;
  }
  
  function decode(&$field, $expand_html)
  {
    if (!is_assoc($field)) return;
    $quoted = array();
    page::decode_options($field, '__type_options', $quoted);
    page::decode_options($field, '__field_options', $quoted);
    
    // channge $key outside loop: php does not support key element as reference
    $replaced = array();
    foreach($field as $key=>$value) {
      $replaced[$key] = $value;
      if ($key[0] != '$') continue;
      $new_key = at($this->request, substr($key,1));
      unset($replaced[$key]);
      $replaced[$new_key] = $value;
    }    
    
    $field = $replaced;
    $known_keys = array('name','desc','html','src', 'href', 'url', 
      'action','data','load', 'valid', 'program', 'sort');
    
    foreach($field as $key=>&$value) { 
      if (in_array($key, $known_keys, true)) continue;
      $quotes = at($quoted, $key);
      if ($quotes[0] != '"')// load unquoted field
        $this->read_field($key, $expand_html, $value);

      if (is_array($value)) continue;

      $value = replace_vars($value);      
      if ($quotes[1] == '"' || !preg_match('/^\w+$/',$value)) continue;
      
      $sub_values = $this->read_field($value, $expand_html);
      if (!is_null($sub_values))
        $value = $sub_values;
    }
    
    $template = at($field, 'template');
    if (is_array($template))
     $field['template'] = at($template,'html');
    
  }
  
  
  
  function decode_options(&$field, $options_name, &$quoted)
  {
    $options = at($field,$options_name);
    if (is_null($options)) return;
    
    page::match_quoted($options, $quoted);   
    $decoded = page::decode_json($options);
    //log::debug("FIELD ". json_encode($field));
    //log::debug("DECODED ".  json_encode($decoded));
    merge_to($field, $decoded);
    unset($field[$options_name]);
    //log::debug("MERGED ". json_encode($field));
  }
  
 
  static function match_quoted($str, &$quoted)
  {
    $result = $matches = array();
    if (!preg_match_all('/(")?([^"]+)\1?(?:\s*:\s*(")?([^{\[^"]+)\3?)?/', $str, $matches, PREG_SET_ORDER)) return;
    foreach($matches as $match) {
      $key_quote = $match[1];
      $value_quote = $match[3];
      if (is_null($key_quote) && is_null($value_quote)) continue;
      $key = $match[2];
      $quoted[$key] = array($key_quote,$value_quote);
    }
    merge_to($quoted, $result);
  }
  
  static function decode_json($str)
  {
    if (is_null($str)) return null;
    $orig = $str;
    $str = str_replace('~', ',', $str);
    
    // match name:value
    $str = preg_replace('/(?:(\$?\w+)|("[^"]*"))\s*:\s*(?:"([^"]*)"|([^\[\]{},]+))/', '"$1$2":"$3$4"', $str);
    // match name:{} or name:[]
    $str = preg_replace('/(?:(\$?\w+)|"([^"]*)")\s*([:$\[\]{},])?/', '"$1$2"$3', $str);
    log::debug("JSON PASS2 $str");
    $decoded = json_decode('{'.$str.'}', true);
    if (!is_null($decoded)) return $decoded;
    
    // json not correct, perhaps we names without values, e.g. {x,y,z}
    // also match [] but don't set empty value
    $matches = array();
    if (!preg_match_all('/("\$?\w+")(\s*:\s*["\[{]?)?|(\])/', $str, $matches, PREG_SET_ORDER)) 
       throw new Exception("Invalid JSON (1) ORIG: $orig\n DECODED $str");
    
    $pos = 0;
    $array_open = false;
    foreach($matches as $match) {
      $key = $match[1];
      if (is_numeric($key)) continue;
      $value = at($match,2);
      $array_closed = at($match, 3);
      //log::debug("JSON PASS3 ".json_encode(array($key,$value,last($value),$array_open,$array_closed)));
      if ($array_closed) {  // closing array
        $array_open = false; 
        continue;
      }
      if (!is_null($value)) {
        $array_open = last($value) == '[';
        continue;
      }
      else if ($array_open) 
        continue;
      $pos = strpos($str, $key, $pos)+strlen($key);
      $str = substr($str, 0, $pos) . ':""'.substr($str, $pos);
    }
    
    $decoded =  json_decode('{'.$str.'}', true);
    if (is_null($decoded))
       throw new Exception("Invalid JSON (2) ORIG: $orig\n DECODED $str");

    return $decoded;
  }
   
  function expand_html(&$field)
  {
    $html = $field['html'];
    if (isset($field['has_data']) || is_null($html)) return;
    $matches = array();
    if (!preg_match_all('/\$(\w+)/', $html, $matches, PREG_SET_ORDER)) return;
    
    $exclude = array('code','name','desc');
    foreach($matches as $match) {
      $var = $match[1]; 
      if (in_array($var, $exclude, true)) continue;
      $value = $this->read_field($var, true);
      if (!is_null($value)) $field[$var] = $value;
    }
  }

  static function expand_values(&$row, $exclusions=array())
  {
    if (!is_array($row)) return;
    foreach($row as $key1=>&$value1) {
      if (is_array($value1) || in_array($key1, $exclusions)) continue;
      foreach ($row as $key2=>$value2) {
        if (!is_array($value2))
          $value1 = preg_replace('/\$'.$key2.'([^\w]*)/', "$value2\$1", $value1);
      }
    }
  }
  
  static function empty_fields(&$options, $fields=array('data','load'))
  {
    foreach($options as $key=>&$option)
    {
      if (is_numeric($key)) continue;
      if (in_array($key, $fields, true)) 
        $option = "";
      else if (is_array($option))
        page::empty_fields($option, $fields);
      else if ($key == 'action' && strpos($option, '::') !== false)
        $option = "";
    }
  }
   
  function validate($field)
  {
    foreach($field as $code=>$values) {
      if (!is_array($values)) continue;
      $valid = trim(at($values,'valid'));
      if ($value == '') continue;
      if (is_null($valid)) {
        $this->validate ($values);
        continue;
      }
      $matches = array();
      if (!preg_match_all('/([^,]+),?/', $valid, $matches, PREG_SET_ORDER)) 
        throw new Exception("Invalid validators $valid");

      $name = at($values, 'name');
      foreach($matches as $match) {
        $valid = $match[1];
        if ($valid == 'optional' && !$this->validator->check($code, $name)->provided()) continue;
        $this->validator->check($code, $name)->is($valid);
      }
    }
    
    return $this->validator->valid();
  }

  function read_page_field(&$page=null)
  {
    $page = $this->read_request('_page');
    $code = $this->request['_field'];
    $field = $this->read_request('_field');
    return null_merge($field, at($page, $code));
  }
  
  static function data($request)
  {
    log::debug('page::data page='.$request['_page']. ', field='.$request['_field']);
    $page = new page($request);
    $field = $page->read_page_field();    
    //page::expand_values($row, array('template','html'));    
    $data = $field['data'];
    
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/s', $data, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'inline') {
      $values = explode('|', $list);
      $rows = array();
      foreach($values as $pair) {
        list($code) = explode(',', $pair);
        $value['item_code'] = $code;
        $value['item_name'] = substr($pair, strlen($code)+1);
        $rows[] = $value;
      }
    }
    else if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_ASSOC);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]+)\)/', $data, $matches) ) {
      $rows = $this->call($matches[1], $matches[2], $field);
    }

    set_valid($rows, $field, 'template');
    echo json_encode($rows);
  }
  
  function call($function, $params, $options=null)
  {
    log::debug("FUNCTION $function PARAMS:".$params);
    list($class, $method) = explode('::', $function);
    $file = "$class.php";
    if (isset($method)) {
      if (file_exists($file)) 
        require_once("$class.php");
      else if (file_exists("../common/$file"))
        require_once("$class.php");
      else {
        log::error("No such file $file");
        return;
      }
    }
    
    $params = explode(',', $params);
    if (is_array($options)) {
      $options = array_merge($options, $this->request);
      foreach($params as &$param) {
        $param = replace_vars ($param, $options);
      }
    }
    return call_user_func_array($function, array_merge(array($this->request), $params));
  }
  
  static function action($request)
  {
    $page = new page($request);
    $page_options = array();
    $options = $page->read_page_field($page_options);
    page::expand_values($options);
    page::expand_values($page_options);
       
    if (array_key_exists('validate', $options) && !$page->validate($page_options)) 
      return;
     
    $action = at($options,'action');
    if (is_null($action)) return;
    
    $rows = array();
    $matches = array();
    if (!preg_match('/^([^:]+): ?(.+)/s', $action, $matches)) {
      throw new Exception("Invalid action spec $action");
    }
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $action, $matches) ) {
      $page->call($matches[1], $matches[2], $options);
    }
  }

  static function check_field($options, $field)
  {
    $value = $options[$field];
    if (isset($value)) return $value;
    
    log::warn("No $field parameter provided");
    return false;
  }
  
  static function load($request)   
  {  
    $page = new page($request);
    $options = $page->read_request('page');
    page::expand_values($options);

    if (!($key=page::check_field($request, 'key'))
      || !($load=page::check_field($options, 'load'))) return;
   
    log::debug("key=$key, $options=".json_encode($options));
    $rows = array();
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/s', $load, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $sql = str_replace('$key', addslashes($key), $list);
      $rows = $db->read_one($sql, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $load, $matches) ) {
      $page->call($matches[1], $matches[2]);
    }
  }
    
  static function respond($response, $value=null)
  {
    global $json;
    $json['_responses'][$response] = is_null($value)?'':$value;
  }
    
  static function alert($message)
  {
    page::respond('alert', $message);
  }
  
  static function redirect($url)
  {
    page::respond('redirect', $url);
  }

  static function show_dialog($dialog, $options=null)
  {
    page::respond('show_dialog', $dialog);
    if (!is_null($options)) page::respond('options', $options);
  }
  
  static function close_dialog($message=null)
  {
    if (!is_null($message)) page::alert($message);
    page::respond('close_dialog');
  }
  
  static function update($name, $value=null)
  {
    global $json;
    $responses = &$json['_responses'];
    $updates = &$responses['update'];
    if (is_array($name))
      page::null_merge ($updates, $name);
    else
      $updates[$name] = $value;
  }
}
