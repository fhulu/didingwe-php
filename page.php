<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

//require_once 'db.php';
require_once 'validator.php';
require_once 'db.php';

class page_output
{
  var $values;
  function __construct() 
  {
    $this->values = null;
  }
  
  function __destruct() 
  {
    if ($this->values)
      echo json_encode ($this->values);
  }
  
}

{
  global $page_output;
  $page_output = new page_output();
  
  try {
    $page = new page();
  }
  catch (user_exception $exception) {
    log::error("UNCAUGHT EXCEPTION: " . $exception->getMessage() );
    log::stack($exception);
    page::show_dialog('/breach');
  }
  catch (Exception $exception)
  {
    log::error("UNCAUGHT EXCEPTION: " . $exception->getMessage() );
    log::stack($exception);
    page::show_dialog('/error_page');
  }
}

class page
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
  var $user;

  function __construct($echo=true, $request=null)
  {
    if (is_null($request)) $request = $_REQUEST;
    log::debug(json_encode($request));
    $this->request = $request;
    $this->path = explode('/', $request['path']);
    $this->method = $request['action'];
    if (is_null($this->method))
      throw new Exception("No method parameter in request");    
    
    $this->types = array();
    global $session;
    $this->user = $session->user;

    $this->load();
    $this->result = $this->{$this->method}();
  
    if ($echo && !is_null($this->result))
      echo json_encode($this->result);
  }
  
  function load()
  {
    if (sizeof(page::$all_fields) > 0) return;
    $this->load_yaml('../common/controls.yml', false, page::$all_fields); //todo cache common controls
    $this->load_yaml('custom_controls.yml', false, page::$all_fields);
    $this->load_yaml('../common/fields.yml', false, page::$all_fields); //todo cache common fields
    $this->load_yaml('custom_fields.yml', false, page::$all_fields);
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
    $this->page = array_shift($path);
    $field_exists  = !null_at($this->fields,$this->page);
    $this->fields = $this->filter_access($this->fields);
    $field  = at($this->fields,$this->page);
    if (is_null($field) && $field_exists)
      throw new user_exception("Unauthorized access to ".implode('/', $path));
    
    $this->set_types($this->fields, $field);
    $this->set_types(page::$all_fields, $field);
    $type = at($field, 'type');
    if (!is_null($type)) {
      $field = null_merge(at(page::$all_fields, $type), $field);
      $field = null_merge(at($this->fields, $type), $field);
      unset($field['type']);
    }
    if (in_array('html', $expand)) {
      $this->expand_html($field, 'html');
      $this->expand_html($field, 'template');
    }
    if (in_array('field', $expand))
      $this->expand_field($field);
    
    log::debug_json("PATH", $path);
    foreach ($path as $step) {
      $this->field = $step;
      $step_field = at($field, $step);
      if (is_null($step_field)) {
        foreach($field as $values) {
          if (is_array($values) && (at($values, 'code') != $step)
              || is_string($values) && $values != $step) continue;
          $step_field = $values;
          break;
        }
        if (is_null($step_field)) {
          log::error("MISTEP ".json_encode($field));
          throw new Exception("Invalid path step $step on ".implode('/', $path));
        }
      }
      $field = $step_field;
      if (is_string($field)) {
        $field = at($this->fields, $field);
        if (is_null($field)) $field = at($this->types, $field);
      }
      
      $this->set_types($this->fields, $field);
      $this->set_types(page::$all_fields, $field);
      if (in_array('html', $expand)) {
        $this->expand_html($field, 'html');
        $this->expand_html($field, 'template');
      }

      if (in_array('field', $expand))
        $this->expand_field($field);
    }
    $this->expand_params($field);
      
    return $field;
  }

  function get_field($name)
  {
    return null_merge(at(page::$all_fields, $name), at($this->fields, $name), false);
  }

  function read_field($path=null)
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
    $global_field = $this->traverse_field(page::$all_fields, $path);
    log::debug("GLOBAL FIELD ".json_encode($global_field));
    $local_field = $this->traverse_field($this->fields, $path);
    log::debug("LOCAL FIELD ".json_encode($local_field));
  }
  
  function traverse_field($fields, $path, $name=null)
  {
    if (is_null($fields)) return null;
    if (is_null($name)) $name = last($path);
    log::debug("TRAVERSE $name ".implode('/',$path). " ".json_encode($fields));
    $parent = array_shift($path);
    if (is_assoc($fields)) {
      $value = at($fields, $name);
      $sub_field = at($fields, $parent);
      if (is_null($sub_field)) return null;
      return null_merge($value, $this->traverse_field($sub_field, $path, $name),false);
    }
    
    foreach($fields as $value) {
      if ($value === $parent) return null;
      if (!is_array($value)) continue;
      foreach($value as $key=>$sub_val) {
        if ($key == $parent)
          return null_merge($value, $this->traverse_field ($value, $path, $name), false);
      }
    }
    return null;
  }
  
  
  function read($expand='html')
  {    
    $fields = $this->load_field(null, array($expand));
    $this->check_access($fields, true);
    $fields = $this->filter_access($fields);
    if ($expand === 'html') {
      page::empty_fields($fields);
      $this->expand_sub_pages($fields);
    }
    if ($this->user) {
      $user = $this->user;
      $fields['user_full_name'] = "$user->first_name $user->last_name";
    }
    return array(
      'path'=>implode('/',$this->path),
      'fields'=>$fields,
      'types'=>$this->types,
    );
  }
    
  function filter_access($options, $user_roles = null)
  {
    if (is_null($user_roles)) {
      global $session;
      require_once 'session.php';

      $user_roles = array('public');
      if (!is_null($session)) {
        $user_roles = $session->roles;
        if (in_array('super', $user_roles))
          $user_roles = array('user','reg', 'viewer','admin','clerk','manager');
      }
      log::debug("ROLES ".json_encode($user_roles));
    }
    
    $filtered = array();
    foreach($options as $key=>$option)
    {
      $original = $option;
      $expanded = false;
      if (is_numeric($key) && is_string($option)) {
        $option = at($this->types, $option);
        $expanded = true;
      }
      if  (!is_array($option)) {
        if (is_numeric($key))
          $filtered[] = $original;
        else
          $filtered[$key] = $original;
        continue;
      }
      $allowed_roles = at($option, 'access');
      if (is_array($allowed_roles)) $allowed_roles = last($allowed_roles);
      if ($allowed_roles != '') {
        $allowed = array_intersect($user_roles, explode(',', $allowed_roles));      //log::debug("PERMITTED $key ".  json_encode($allowed));
        if (sizeof($allowed) == 0) continue;
      }
      if (!$expanded)
        $option = $this->filter_access($option, $user_roles);
      if (sizeof($option) == 0) continue;
      if ($expanded) $option = $original;
      if (is_numeric($key))
        $filtered[] = $option;
      else
        $filtered[$key] = $option;
    }
    return $filtered;
  }
  
  function expand_sub_pages(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value, $key) use ($request) {
      if ($key !== 'page') return;
      $request['path'] = $value;
      $sub_page = new page(false, $request);
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
      if ($this->set_types($this->fields, $type) || $this->set_types(page::$all_fields, $type)) 
        $this->expand_html(at($this->types, $type), $html_type);
      return;
    };
    $matches = array();
    if (!preg_match_all('/\$(\w+)/', $html, $matches, PREG_SET_ORDER)) return;
    
    $exclude = array('code','name','desc', 'field');
    foreach($matches as $match) {
      $var = $match[1]; 
      if (in_array($var, $exclude, true)) continue;
      if ($this->set_types($this->fields, $var) || $this->set_types(page::$all_fields, $var)) {
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
  
  static function empty_fields(&$options, $fields=array('call','sql'))
  {
    foreach($options as $key=>&$option)
    {
      if (is_numeric($key)) continue;
      if (in_array($key, $fields, true)) 
        $option = "";
      else if (is_array($option))
        page::empty_fields($option, $fields);
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
    $field = $this->load_field(null, array('field'));
    $type = at($field, 'type');
    if (!is_null($type)) {
      $field = null_merge(at(page::$all_fields, $type), $field);
      $field = null_merge(at($this->fields, $type), $field);
      unset($field['type']);
    }
    log::debug_json('field', $field);
    $items = array();
    foreach($field as $item) {
      $items = null_merge($items, $this->reply($item, false), false);
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
      $options = array_merge($options, $this->request);
      foreach($params as &$param) {
        $param = replace_vars ($param, $options);
      }
    }
    else $options = $this->request;
    return call_user_func_array($function, array_merge(array($options), $params));
  }
  
  function merge_type($field, $type=null)
  {
    if (is_null($type)) $type = at($field, 'type');
    if (is_null($type)) return $field;
    $expanded = at($this->types, $type);
    if (is_null($expanded)) 
      throw new Exception("Unknown  type $type specified");
    $super_type = $this->merge_type($expanded);
    return array_merge_recursive_distinct($super_type, $field);
  }
  
  function expand_contents(&$parent)
  {
    $default_type = null;
    $length = sizeof($parent);
    $result = array();
    foreach($parent as &$value) {
      $code = null;
      if (is_array($value)) {
        //log::debug("EXPANDING VALUE ".json_encode($value));
        $type = at($value, 'type');
        if (!is_null($type)) {
          $default_type = at($this->types, $type);
          continue;
        }
        if (null_at($value, 'code')) {
          foreach ($value as $key=>$val) break;
          if (!is_array($val)) continue;
          $value = $val;
          $value['code'] = $key;
          //log::debug("EXPANDING overloaded ".json_encode($value));
        }
        if (null_at($value,'type'))
          $value = array_merge_recursive_distinct($default_type, $value);
        $type_value = at($this->types, $key);
        //log::debug("TYPE VALUE ".json_encode($type_value));
        $value = array_merge_recursive_distinct($type_value, $value);
        //log::debug("EXPANDED ".json_encode($value));
        continue;
      }
      if (!is_string($value) || preg_match('/\W/', $value)) continue;
      $code = $value;
      $value = at($this->types, $code);
      $value = array_merge_recursive_distinct($default_type, $value);
      $value['code'] = $code;
    }
    
  }

  function expand_field(&$field)
  {
    foreach ($field as $key=>&$value) {
      if (is_numeric($key)) {
        $this->expand_contents($field);
        break;
      }
      if (!is_array($value)) continue;
      $type_value = at($this->types, $key);
      $value = null_merge($type_value, $value, false);
      $this->expand_field($value);
    }
  }

  function check_access($field, $throw=false)
  {
    $allowed_roles = at($field, 'access');
    if (is_array($allowed_roles)) $allowed_roles = last($allowed_roles);
    if ($allowed_roles == '') return true;

    global $session;

    $user_roles = array('public');
    if (!is_null($session) && !is_null($session->user))
      $user_roles = $session->user->roles;
    
    if (in_array('super', $user_roles)) return true;
    
    $allowed = array_intersect($user_roles, explode(',', $allowed_roles));    
    if (sizeof($allowed) > 0) return true;
    if (!$throw) return false;
    $code = $field['code'];
    $path = implode('/', $this->path);
    throw new user_exception("Unauthorized access to PATH $path FIELD $code");
  }
  
  function action()
  {
    $invoker = $this->load_field(null, array('field'));
    log::debug_json("INVOKER ", $invoker);
    $fields = $this->fields[$this->page];
    $action = $invoker['action'];
    $this->expand_field($fields);
    $validate = at($action, 'validate');
    if (!is_null($validate) && $validate != 'none' && !$this->validate($fields))
      return null;
    
    return $this->reply($action);
  }
  
  static function replace_sql($sql, $options) 
  {
    global $session; 
    require_once 'session.php';
    $user = $session->user;
    $user_id = $user->id;
    $key = $options['key'];
    $sql = preg_replace('/\$uid([^\w]|$)/', "$user_id\$1", $sql);
    return preg_replace('/\$key([^\w]|$)/', "$key\$1", $sql);
  }
  
  function reply($action, $assoc = true)
  {
    $call = at($action ,'call');
    if ($call != '') { 
      $invoker = $this->path[sizeof($this->path)-1];
      log::debug("INVOKER $invoker ");
      $call = preg_replace('/\$class([^\w]|$)/', "$this->object\$1", $call);
      $call = preg_replace('/\$page([^\w]|$)/', "$this->page\$1", $call); 
      $call = preg_replace('/\$invoker([^\w]|$)/', "$invoker\$1", $call);
      $call = preg_replace('/\$default([^\w]|$)/', "$this->object::$this->page\$1", $call);

      $matches = array();
      if (!preg_match('/^([^\(]+)(?:\(([^\)]*)\))?/', $call, $matches) ) 
        throw new Exception("Invalid function spec $call");
      return $this->call($matches[1], $matches[2], $this->fields[$this->page]);
    }
    
    $sql = at($action,'sql');
    if ($sql == '') return null;
    $sql = page::replace_sql($sql, $this->request);
    global $db;
    return $assoc?$db->page_through_names($sql): $db->page_through_indices($sql);
  }

  static function check_field($options, $field)
  {
    $value = $options[$field];
    if (isset($value)) return $value;
    
    log::warn("No $field parameter provided");
    return false;
  }
  
  function fields()
  {
    return $this->read('field');
  }
  
  function values()   
  {  
    $options = $this->load_field(null, array('field'));
    log::debug("VALUES ".json_encode($options));
    $items = array();
    foreach($options as $option) {
      $items = null_merge($items, $this->reply($option), false);
    }
    
    return $items;
  }
    
  static function respond($response, $value=null)
  {
    global $page_output;
    $result = &$page_output->values;
    $result['_responses'][$response] = is_null($value)?'':$value;
  }
    
  static function alert($message)
  {
    page::respond('alert', $message);
  }
  
  static function redirect($url)
  {
    page::respond('redirect', $url);
  }

  static function show_dialog($dialog, $options=null, $values = null)
  {
    page::respond('show_dialog', $dialog);
    $options['values'] = $values;
    if (!is_null($options)) page::respond('options', $options);
  }
  
  static function dialog($dialog, $options=null, $values=null)
  {
    page::show_dialog($dialog, $options, $values);
  }

  static function close_dialog($message=null)
  {
    if (!is_null($message)) page::alert($message);
    page::respond('close_dialog');
  }
  
  static function update($name, $value=null)
  {
    global $page_output;
    $result = &$page_output->values;
    $responses = &$result['_responses'];
    $updates = &$responses['update'];
    if (is_array($name))
      null_merge ($updates, $name);
    else
      $updates[$name] = $value;
  }
  
  static function error($name, $value)
  {
    global $page_output;
    log::debug("ERROR $name $value ");
    $result = &$page_output->values;
    $errors = &$result['errors'];
    $errors[$name] = $value;
  }
  
}
