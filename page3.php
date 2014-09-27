<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of 
 * - request : http request
 * - object : object requested, if not supplied use common
 * - page : page required on the object
 * - method : method to be performed
 * @author fhulu
 */

//require_once 'db.php';
require_once 'validator.php';
require_once 'config.php';

class page_result
{
  var $fields;
  var $types;
  function __construct() {
    $this->types = array();
    $this->fields = array();
  }
}

class page3
{  
  var $requet;
  var $object;
  var $method;
  var $page;
  var $controls; 
  var $fields;
  var $result;
  var $types;
  var $validator;
  static function test()
  {
    new page3();
  }

  function __construct($request=null)
  {
    if (is_null($request)) $request = $_REQUEST;
    $this->request = $request;
    $this->object = at($request, 'object');
    if (is_null($this->object))
      throw new Exception("No object parameter in request");
    $this->method = at($request, 'method');
    if (is_null($this->method))
      throw new Exception("No method parameter in request");
    $this->page = at($request, 'page');
    $this->controls = array();
    $this->fields = array('program', config::$program_name);
    $this->result = new page_result();
    
    //todo: fix coupling validator with reporting to front end
    //$this->validator = new validator($request);
    
    $this->load();
    $this->{$this->method}();
    
    echo json_encode($this->result);
 }
  
  function load()
  {
    $this->load_yaml('../common/controls.yml', $this->controls); //todo cache common controls
    $this->load_yaml('custom_controls.yml', $this->controls);
    $this->load_yaml('../common/fields.yml', $this->fields); //todo cache common fields
    $this->load_yaml('custom_fields.yml', $this->fields);
    $this->load_yaml("$this->object.yml", $this->fields);
  }
  
  static function load_yaml($file, &$fields=array())
  {
    if (!file_exists($file)) return $fields;
    $data = yaml_parse_file($file); 
    if (is_null($data))
      throw new Exception ("Unable to parse file $file");
    return merge_to($fields, $data);
  }
  
  function read()
  {    
    $fields = $this->fields[$this->page];
    //$this->expand_fields($this->controls, $fields);
    $this->expand_fields($fields); 
    $this->result->fields = $fields;
    
    $types = array();
    while ($this->expand_types($fields, $types)) {
      $fields = $types;
    }
    $this->result->types = $types;
//    page::empty_fields($field);
//    echo json_encode($field);
  }
  
  
  function expand_types($fields, &$types)
  {
    $controls = $this->controls;
    $expanded = false;
    array_walk_recursive($fields, function($value, $key) use (&$types, $controls, &$expanded) {
      if ($types[$value] == $controls[$value]) return;
      if ($key == 'type' || $key == 'template' && ctype_alpha($value[0])) {
        $types[$value] = $controls[$value];
        $expanded = true;
      }
    });
    return $expanded;
  }
  
  function expand_fields(&$fields)
  {
    $parent = $this->fields;
    $known_keys = array('name','desc','html','src', 'href', 'url', 
      'data','values', 'valid', 'type', 'sort');
    foreach($fields as $key=>&$value) {
      if (in_array($key, $known_keys, 1)) continue;

      if (is_array($value)) 
        $this->expand_fields($value);
      
      if (!is_numeric($key)) {
        $options = at($this->fields, $key);
        if (is_array($options))
          $value = array_merge($options, $value);     
      }

      // numeric key
      if (is_array($value)) continue;
      $options = at($this->fields, $value);
      if (is_array($options)) $value = array($value=>$options);

    } 
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
      $options = array_merge($this->request, $options);
      foreach($params as &$param) {
        $param = page::replace_vars ($param, $options);
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
      $page->call($matches[1], $matches[2]);
    }
  }

  static function check_field($options, $field)
  {
    $value = $options[$field];
    if (isset($value)) return $value;
    
    log::warn("No $field parameter provided");
    return false;
  }
  
  static function values($request)   
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
