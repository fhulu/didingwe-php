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
require_once 'db.php';


# read/booking/create/facility/options/
class page3
{  
  var $request;
  var $object;
  var $method;
  var $page;
  var $field;
  static $all_fields; 
  var $fields;
  var $types;
  var $validator;
  var $path;
  var $root;

  function __construct($request=null)
  {
    if (is_null($request)) $request = $_REQUEST;
    log::debug(json_encode($request));
    $this->request = $request;
    $this->path = explode('/', $request['path']);
    $this->method = array_shift($this->path);
    if (is_null($this->method))
      throw new Exception("No method parameter in request");    
    
    $this->fields = array('program', config::$program_name);
    $this->types = array();
    $this->validator = null;
    
    $this->load();
    $result = $this->{$this->method}();
    if (!is_null($result))
      echo json_encode($result);
 }
  
  static function run()
  {
    new page3();
  }
  
  function load()
  {
    $this->load_yaml('../common/controls.yml', false, page3::$all_fields); //todo cache common controls
    $this->load_yaml('custom_controls.yml', false, page3::$all_fields);
    $this->load_yaml('../common/fields.yml', false, page3::$all_fields); //todo cache common fields
    $this->load_yaml('custom_fields.yml', false, page3::$all_fields);
  }

  static function load_yaml($file, $strict, &$fields=array())
  {
    if (!file_exists($file)) {
      if ($strict) throw new Exception("Unable to load file $file");
      return $fields;
    }
    
    $data = yaml_parse_file($file); 
    if (is_null($data))
      throw new Exception ("Unable to parse file $file");
    return merge_to($fields, $data);
  }

  function load_field($path=null, $expand=array('html','field'))
  {
    if (is_null($path))
      $path = $this->path;
    else if (!is_array($path))
      $path = explode('/', $path);
    
    $this->object = array_shift($path);
    $this->fields = $this->load_yaml("$this->object.yml", true);
    if (sizeof($path) == 0 || is_null(at($this->fields, $path[0]))) {
      array_unshift($path, at($this->fields, 'default'));
    }
    $this->page = $path[0];
    $field  = $this->fields;
    foreach ($path as $step) {
      $this->field = $step;
      $step_field = at($field, $step);
      if (is_null($step_field)) {
        foreach($field as $values) {
          if (is_array($values) && at($values, 'code') != $step 
              || is_string($values) && $values != $step) continue;
          $step_field = $values;
          break;
        }
        if (is_null($step_field)) 
          throw new Exception("Invalid path ".implode('/', $path));
      }
      $field = $step_field;
      $this->set_types($this->fields, $field);
      $this->set_types(page3::$all_fields, $field);
      if (in_array('html', $expand)) {
        $this->expand_html($field, 'html');
        $this->expand_html($field, 'template');
      }
      if (in_array('field', $expand))
        $this->expand_field($field);
    }
    return $field;
  }
  
  function read()
  {    
    $page = $this->load_field(null, array('html'));
    return array(
      'page'=>$page,
      'types'=>$this->types,
    );
  }
  
  function set_types($parent, $field)
  {
    if (is_null($field)) return;
    if (!is_array($field)) {
      if (array_key_exists($field, $this->types)) return true;
      if (!array_key_exists($field, $parent)) return false;

      $this->types[$field] = $value = $parent[$field];
      if (is_array($value)) $this->set_types($parent, $value);
      return true;
    }
    
    $known_keys = array('name','desc','html','src', 'href', 'url', 
      'data','values', 'valid', 'attr', 'sort');
    foreach($field as $key=>&$value) {
      if (in_array($key, $known_keys, 1)) continue;
      
      if (!is_numeric($value))
        $this->set_types($parent, $value);        
      
      if (!is_numeric($key))
        $this->set_types($parent, $key);
    }
    
    return true;
  }
     
  function expand_html($field, $html_type)
  {
    $html = at($field, $html_type);
    if (is_null($html)) {
      $type = at($field, 'type');
      if ($this->set_types($this->fields, $type) || $this->set_types(page3::$all_fields, $type)) 
        $this->expand_html(at($this->types, $type), $html_type);
      return;
    };
    $matches = array();
    if (!preg_match_all('/\$(\w+)/', $html, $matches, PREG_SET_ORDER)) return;
    
    $exclude = array('code','name','desc', 'field');
    foreach($matches as $match) {
      $var = $match[1]; 
      if (in_array($var, $exclude, true)) continue;
      if ($this->set_types($this->fields, $var) || $this->set_types(page3::$all_fields, $var)) {
        $this->expand_html(at($this->types, $var), $html_type);
        continue;
      }
      $yaml_file = "$var.yml";
      if (!file_exists($yaml_file)) 
        throw new Exception("Failure expanding variable \$$var. Cannot find YAML file $yaml_file ");
      
      $fields = $this->load_yaml($yaml_file);
      $this->set_types(page3::$all_fields, $fields);
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
  
  static function empty_fields(&$options, $fields=array('data','sql'))
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
    if (is_null($this->validator))
      $this->validator = new validator($this->request);
    //todo: validate only required fields;
    foreach($field as $code=>$values) {
      if (!is_array($values)) continue;
      if (is_numeric($code)) {
        $code = at($values, 'code');
        if (is_null($code)) {
          $this->validate($values);
          continue;
        }
      }
      $valid = at($values,'valid');
      if (is_array($valid)) $valid = last($valid);
      if ($valid == '') {
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

  function data()
  {
    $options = $this->load_field(null, array('field'));
    $items = array();
    foreach($options as $option) {
      $sql = at($option, 'sql');
      if (!is_null($sql)) {
        global $db;
        $items = array_merge($items, $db->page_through_indices($sql));
        continue;
      }
    }
    
    return $items;
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
  
  function merge_type($field, $type=null)
  {
    if (is_null($type)) $type = at($field, 'type');
    if (is_null($type)) return $field;
    $expanded = at($this->types, $type);
    if (is_null($expanded)) return $field;
    $super_type = $this->merge_type($expanded);
    return null_merge($super_type, $field, false);
  }
  
  function expand_contents(&$parent)
  {
    $default_type = null;
    $length = sizeof($parent);
    $result = array();
    foreach($parent as &$value) {
      $code = null;
      if (is_array($value)) {
        $type = at($value, 'type');
        if (!is_null($type)) {
          $default_type = $type;
          continue;
        }
        $code = at($value, 'code');
        if (is_null($code)) foreach ($value as $code=>$val) break; 
        $value = $value[$code];
        $value['code'] = $code;
        $value = null_merge(at($this->types, $code), $value, false);
        $value = $this->merge_type($value, $default_type);
        continue;
      }
      if (!is_string($value) || preg_match('/\W/', $value)) continue;
      $code = $value;
      if (!null_at($this->types, $value)) {  
        $value = at($this->types,$value);
        $value = $this->merge_type($value, $default_type);
      }
      else {
        if (is_null($default_type))          continue;
        $value = $default_type;
      }
      $value['code'] = $code;
    }
    
  }

  function expand_field(&$field)
  {
    if (!is_assoc($field)) {
      $this->expand_contents($field);
      return;
    }
    foreach ($field as $key=>&$value) {
      if (!is_array($value)) continue;
      $value = null_merge(at($this->types,$key), $value, false);
      $this->expand_field($value);
    }
  }

  function action()
  {
//    $path_len = sizeof($this->path);
//    $path = array_slice($this->path, 0, $path_len-2); // skip actions and action
//    $fields = $this->load_field($path);
//    $this->expand_field($fields);
//    $action_name = $this->path[$path_len-1];
//    $parent_name = $this->path[$path_len-2];
//    $actions = $fields[$parent_name];
//    foreach ($actions as $action) {
//      if (at($action,'code') === $action_name) break;
//    }    
//
    $action = $this->load_field(null, array('field'));
    log::debug("ACTION ".json_encode($action));
    $fields = $this->fields[$this->page];
    $this->expand_field($fields);
    log::debug("FIELDS ".json_encode($fields));
    if (array_key_exists('validate', $action) && !$this->validate($fields)) {
      return array("errors"=>$this->validator->errors);
    }

    $call = at($action ,'call');
    if ($call === 'default') 
      $call = "$this->object::".$this->path[1].'()';
    
    log::debug("CALL $call");
    if ($call != '') { 
      $matches = array();
      if (!preg_match('/^([^\(]+)\(([^\)]*)\)/', $call, $matches) ) 
        throw Exception("Invalid function spec $call");
      return $this->call($matches[1], $matches[2]);
    }
    
    $sql = at($action,'sql');
    if ($sql == '') return;
    
    global $db;
    return $db->read($sql, MYSQLI_ASSOC);
  }

  static function check_field($options, $field)
  {
    $value = $options[$field];
    if (isset($value)) return $value;
    
    log::warn("No $field parameter provided");
    return false;
  }
  
  function values()   
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
