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
  var $requet;
  var $object;
  var $method;
  var $page;
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
    
    //todo: fix coupling validator with reporting to front end
    //$this->validator = new validator($request);
    
    $this->load();
    $result = $this->{$this->method}();

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

  function load_field($path=null, $expand=false)
  {
    if (is_null($path))
      $path = $this->path;
    else if (!is_array($path))
      $path = explode('/', $path);
    
    $object_name = array_shift($path);
    $this->fields = $this->load_yaml("$object_name.yml", true);
    if (sizeof($path) == 0) {
      $path[] = at($this->fields, 'default');
    }
    $field  = $this->fields;
    foreach ($path as $step) {
      $field = $field[$step];
      $this->set_types($this->fields, $field);
      $this->set_types(page3::$all_fields, $field);
      if (!$expand) continue;
      $this->expand_html($field, 'html');
      $this->expand_html($field, 'template');
    }
    return $field;
  }
  
  function read()
  {    
    $page = $this->load_field(null, true);
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
  
  function data()
  {
    $options = $this->load_field();
    $items = array();
    foreach($options as $option) {
      $data = at($option, 'data');
      if (is_null($data)) {
        $items[] = $option;
        continue;
      }
      $sql = at($data, 'sql');
      if (!is_null($sql)) {
        global $db;
        $items = array_merge($items, $db->read($sql, MYSQLI_ASSOC));
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
