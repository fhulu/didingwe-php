<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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
  var $result;

  function __construct($echo=true, $request=null)
  {
    if (is_null($request)) $request = $_REQUEST;
    log::debug(json_encode($request));
    $this->request = $request;
    $this->path = explode('/', $request['path']);
    $this->method = array_shift($this->path);
    if (is_null($this->method))
      throw new Exception("No method parameter in request");    
    
    $this->types = array();
    $this->validator = null;
    
    $this->load();
    $this->result = $this->{$this->method}();
    if ($echo && !is_null($this->result))
      echo json_encode($this->result);
  }
   
  static function run()
  {
    new page3();
  }
  
  function load()
  {
    if (sizeof(page3::$all_fields) > 0) return;
    $this->load_yaml('../common/controls.yml', false, page3::$all_fields); //todo cache common controls
    $this->load_yaml('custom_controls.yml', false, page3::$all_fields);
    $this->load_yaml('../common/fields.yml', false, page3::$all_fields); //todo cache common fields
    $this->load_yaml('custom_fields.yml', false, page3::$all_fields);
  }

  static function load_yaml($file, $strict, &$fields=array())
  {
    if (!file_exists($file)) $file = "../common/$file";
    log::debug("YAML LOAD $file");
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
    if (sizeof($path) == 0) {
      array_unshift($path, $this->object);
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
    $this->expand_params($field);
    $this->filter_access($field);
    return $field;
  }
  
  function read()
  {    
    $fields = $this->load_field(null, array('html'));
    $this->expand_sub_pages($fields);
    global $session;
    if ($session && $session->user) {
      $user = $session->user;
      $fields['user_full_name'] = "$user->first_name $user->last_name";
    }
    return array(
      'path'=>implode('/',$this->path),
      'fields'=>$fields,
      'types'=>$this->types,
    );
  }
    
  static function filter_access(&$options, $user_roles = null)
  {
    if (is_null($user_roles)) {
      global $session;

      $user_roles = array('public');
      if (!is_null($session) && !is_null($session->user))
        $user_roles = $session->user->roles;
      log::debug("ROLES ".json_encode($user_roles));
    }
    
    $filtered = array();
    foreach($options as $key=>$option)
    {
      if (!is_array($option)) {
        if (is_numeric($key))
          $filtered[] = $option;
        else
          $filtered[$key] = $option;
        continue;
      }
      $allowed_roles = at($option, 'access');
      if ($allowed_roles == '') {
        page3::filter_access($option, $user_roles);
      }
      else {
        $allowed = array_intersect($user_roles, explode(',', $allowed_roles));      //log::debug("PERMITTED $key ".  json_encode($allowed));
        if (sizeof($allowed) == 0) continue;
        page3::filter_access($option, $user_roles);
      }
      if (count($option) == 0) continue;
      if (is_numeric($key))
        $filtered[] = $option;
      else
        $filtered[$key] = $option;
    }
    $options = $filtered;
  }
  
  function expand_sub_pages(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value, $key) use ($request) {
      if ($key !== 'page') return;
      $request['path'] = 'read/'.$value;
      $sub_page = new page3(false, $request);
      $value = $sub_page->result;
    });
  }
  
  function expand_params(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value) use ($request) {
      $value = replace_vars ($value, $request);
    });
  }
    
  function set_types($parent, $field)
  {
    if (is_null($field)) return false;
    if (!is_array($field)) {
      if (array_key_exists($field, $this->types)) return true;
      if (!array_key_exists($field, $parent)) return false;

      $this->types[$field] = $value = $parent[$field];
      if (is_array($value)) $this->set_types($parent, $value);
      return true;
    }
    
    $known_keys = array('name','desc','html','src', 'href', 'url', 
      'sql','values', 'valid', 'attr', 'sort');
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
      }
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
        if (is_null($code)) {
          foreach ($value as $code=>$val) break; 
          if (!is_array($val)) continue;
        }
        $value = $value[$code];
        $value = null_merge(at($this->types, $code), $value, false);
        $value = $this->merge_type($value, $default_type);
        $value['code'] = $code;
        continue;
      }
      if (!is_string($value) || preg_match('/\W/', $value)) continue;
      $code = $value;
      if (!null_at($this->types, $value)) {  
        $value = at($this->types,$value);
        $value = $this->merge_type($value, $default_type);
      }
      else {
        if (is_null($default_type)) continue;
        $value = at($this->types, $default_type);
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
    $action = $this->load_field(null, array('field'));
    log::debug("ACTION ".json_encode($action));
    $fields = $this->fields[$this->page];
    $this->expand_field($fields);
    log::debug("FIELDS ".json_encode($fields));
    if (array_key_exists('validate', $action) && !$this->validate($fields)) {
      return array("errors"=>$this->validator->errors);
    }
    
    return $this->reply($action);
  }
  
  function reply($action)
  {
    $call = at($action ,'call');
    if ($call === 'default') 
      $call = "$this->object::".$this->path[1].'()';
    
    if ($call != '') { 
      $matches = array();
      if (!preg_match('/^([^\(]+)\(([^\)]*)\)/', $call, $matches) ) 
        throw Exception("Invalid function spec $call");
      return $this->call($matches[1], $matches[2]);
    }
    
    $sql = at($action,'sql');
    if ($sql == '') return null;
    
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
    $options = $this->load_field(null, array('field'));
    log::debug("EDIT ".json_encode($options));
    $items = array();
    foreach($options as $option) {
      $items = null_merge($items, $this->reply($option));
    }
    
    return $items;
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
