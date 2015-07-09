<?php
session_start();

require_once 'validator.php';
require_once 'db.php';
require_once 'utils.php';

class user_exception extends Exception {};

$page = new page();
try {
  $page->process();
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
 // page::show_dialog('/error_page');
}
$page->output();

class page
{  
  static $fields_stack = array(); 
  static $post_items = array('audit','post', 'valid');
  static $atomic_items = array('action', 'css', 'html', 'script', 'style', 'template', 'valid');
  static $user_roles = array('public');
  var $request;
  var $object;
  var $method;
  var $page;
  var $field;
  var $fields;
  var $types;
  var $validator;
  var $path;
  var $root;
  var $result;
  var $user;
  var $page_offset;
  var $reply;
  var $db;
  var $validated;
  
  var $rendering;
  var $page_stack;
  var $page_fields;
  var $expanding;
  var $context;
  
  function __construct($request=null, $user_db=null)
  {
    global $db;
    $this->db = is_null($user_db)?$db: $user_db;
    $this->result = null;
    
    if (is_null($request)) $request = $_REQUEST;
    log::debug_json("REQUEST",$request);
    $this->request = $request;
    $this->path = $request['path'];
    $this->method = $request['action'];
    $this->page_offset = 1;
    $this->fields = array();
    $this->page_fields = array();
    $this->page_stack = array();
    $this->types = array();
    $this->validated = array();
    $this->rendering = $request['action'] == 'read';
    $this->expanding = array();
    $this->context = array();
  }
  
  function process()
  {
    if (is_null($this->method))
      throw new Exception("No method parameter in request");
    
    $this->read_user();
    
    $path = $this->path;
    if ($path[0] == '/') $path = substr ($path, 1);
    $path = explode('/', $path);
    if (last($path) == '') array_pop($path);
    
    $this->path = $path;
    $this->load();
    
    log::debug_json("PATH".sizeof($path), $path);
    $this->root = $path[0];    
    if (sizeof($path) > 1) {
      $level1 = $this->page_fields[$this->root];
      if (!isset($level1) || !array_key_exists($path[1], $level1)) {
        $this->root = $path[1];
        array_shift($path);
      }
    }
    //$this->fields = $this->expand_field ($this->root); 
    //$this->set_context($path);
    $result = $this->{$this->method}();
    return $this->result = null_merge($result, $this->result, false);
  }
  
  function output()
  {
    if (!is_null($this->result))     
      echo json_encode($this->result);
  }
  
  
  function read_user($reload = false)
  {
    if (!$reload && $this->user && $this->user['uid']) return $this->user;
    log::debug_json("SESSION", $_SESSION);
    $user = $this->read_session('uid,partner_id,roles,groups,email_address,first_name,last_name,cellphone');
    $user['full_name'] = $user['first_name'] . " ". $user['last_name'];
    if (is_null($user['roles'])) $user['roles'] = array('public');
    $this->user = $user;
    log::debug_json("USER",$this->user);
    return $this->user;
  }
  
  static function load_yaml($file)
  {
    log::debug("YAML LOAD $file");
    if (!file_exists($file))
      return null;
    
    $data = yaml_parse_file($file); 
    if (is_null($data))
      throw new Exception ("Unable to parse file $file");
    return $data;
  }
  
  function load_field_stack($file, &$fields=array(), $search_paths=array('../common', '.'))
  {
    $i = 0;
    $path_size = sizeof($search_paths);
    
    $read_one = false;
    foreach($search_paths as $path) {
      $data = page::load_yaml("$path/$file", ++$i != $path_size);
      if (is_null($data)) continue;
      $read_one = true;
      
      $this->replace_keys($data);
      $fields[] = $data;
    }
    if (!$read_one)
      throw new Exception("Unable to load file $file"); 
    return $fields;
  }

  function load()
  {
    
    if (sizeof(page::$fields_stack) == 0) {
      $this->load_field_stack('controls.yml', page::$fields_stack);
      $this->load_field_stack('fields.yml', page::$fields_stack); 
    }

    if (sizeof($this->page_stack) != 0) return;
    
    $this->load_field_stack($this->path[0] . ".yml", $this->page_stack);
    $this->page_fields = $this->merge_stack($this->page_stack);
  }
  
  function load_fields($file)
  {
    $stack = $this->load_field_stack($file);
    return $this->merge_stack($stack);
  }
  
  function allowed(&$field, $path=null, $throw=false)
  {
    if (!is_array($field)) return true;
    $access = $field['access'];
    if (!isset($access)) return true;
      
    if (!is_array($access)) $access = explode (',', $access);
    
    $allowed_roles = array_intersect($this->user['roles'], $access);  
    if (sizeof($allowed_roles) > 0) return true;
    if (!$throw) return false;
    throw new user_exception("Unauthorized access to PATH ".implode('/', $this->path) );
  }

//  function expand_type($type  )
//  {
//    $expanded = $this->types[$type];
//    if (isset($expanded)) return $expanded;
//    $expanded = $this->expand_field($type, array());
//    if (!is_array($expanded)) return $expanded;
//    return $this->types[$type] = $this->expand_field($type, array());;
//  }
   
  
  function expand_type($type, &$added = array()  )
  {
    $expanded = $this->types[$type];
    if (isset($expanded)) return $expanded;
    $expanded = $this->get_expanded_field($type);
    if (!is_array($expanded)) return null;
    $added[] = $type;
    return $this->types[$type] = $expanded;
  }
  
  function merge_type(&$field, &$added = array())
  {
    $type = $field['type'];
    if (!isset($type)) return $field;    
    $expanded = $this->expand_type($type, $added);
    if (is_null($expanded))
      throw new Exception("Unknown type $type");
    if (isset($expanded['type']))
      $field = merge_options($this->merge_type($expanded, $added), $field);
    else
      $field = merge_options($expanded, $field);
    
    return $field;
  }
  
   
  function expand_object($code, &$object, $path, $expanded_type=false) 
  {    
    if (!$expanded_type) {
      if (in_array($code, $this->expanding, true)) return;
      $this->expanding[] = $code;
    }
    $type = null;
    $branch = $path[0];
    $following_path = sizeof($path) > 0;
    $reached_path = !$following_path;
    if ($code == 'list') log::debug("FOLLOWING $following_path $branch REACHED $reached_path");
    foreach($object as $key=>&$values) {
      if (!is_string($key)) continue;
      if (in_array($key, page::$atomic_items,true)) continue;
      if ($key == 'type' && !$expanded_type) {
        $type = $this->expand_type($values);
        continue;
      }
      if ($following_path && $key != $branch) continue;
      if (!is_array($values)) continue;
      
      $values = $this->expand_field($key, $path, $values);

      if (!$this->allowed($values, $path, $following_path)) {
        unset($object[$key]);
        continue;
      }
      
//      if ($this->rendering) 
//        $this->expand_html($values);

      if ($following_path) {
        $reached_path = true;
        break;
      }
    }
    
    if (!is_null($type))// && !$this->rendering) 
      $object = merge_options($type, $object);
    
    if (!$reached_path) {
      if (!$expanded_type && $type) 
        return $this->expand_object ($code, $object, $path, true);
      throw new Exception("Invalid path". implode('/', $this->path));
    }
    else if ($following_path) 
       $object = $values;

    
    array_pop($this->expanding);
    return $object;
}
  
  function expand_array(&$array, $path)
  {
    $type = null;
    $size = sizeof($array);
    $branch = $path[0];
    $following_path = sizeof($path) > 0;

    for($index=0; $index < $size; ++$index) {
      $value = &$array[$index];
      $values = null;
      if (is_assoc($value)) 
        list($key, $values) = assoc_element($value);
      else 
        $key = $value;
      
      // expand type if set
      if ($key == 'type') {
        $type = $this->expand_type($values);
        continue;
      }
      if ($key == 'template') {
        $this->expand_type($values);
        continue;
      }
      
      if ($following_path && $key != $branch) continue;
      if (in_array($key, page::$atomic_items, true)) continue;
      
      // leave out post items when rendering
      if ($this->rendering && in_array($key, page::$post_items, true)) {
        array_splice($array, $index, 1);
        --$index;
        --$size;
        continue;
      }
      
      if (is_string($values)) continue;
      
      $own_type = $values['type'];
      $expanded = $this->expand_field($key, $path);
      if (!is_array($expanded)) $expanded = null;
      $is_page = false;
      
      if ($own_type == 'page' || $own_type=='' && $type == 'page') {
        $is_page = true;
        $expanded = merge_options($expanded, $values);
        $merged_values = $this->load_sub_page($key, $expanded);
      }
      else if ($own_type != '') {
        $own_type = $this->expand_type($own_type);
        $expanded = merge_options($expanded, $values);
        $merged_values = merge_options($own_type, $expanded);
      }
      else if (!is_null($type)) {
        $expanded = merge_options($expanded, $values);
        $merged_values = merge_options($expanded, $type);
      }
      else if (is_array($expanded)) {
        $this->expand_type($key);
        $expanded = $merged_values =  merge_options($expanded, $values);
      }
      else
        continue;
      
      if (!$this->allowed($merged_values, $path, $following_path)) {
        array_splice($array, $index, 1);
        --$index;
        --$size;
        continue;
      }
      
//      if ($this->rendering && !$is_page) {
//        $this->expand_html($merged_values);
//        if (!in_array($key, $this->expanding, true)) {
//        //  if ($type) unset($expanded['type']);
//          $value = array($key=>$expanded);
//        }
//      }
//      else
        $value = array($key=>$merged_values);

      if ($following_path) {
        $array = $value[$key];
        break;
      }
    }

   }
  
  function merge_stack($stack)
  {
    $fields = null;
    foreach ($stack as $level) {
      $fields = merge_options($fields, $level);
    }
    
    return $fields;
  }

 function get_stack_field(&$stack, $code, &$base_field = null)
  {
    foreach ($stack as $fields) {
      $child_field = $fields[$code];
      if (!isset($child_field)) continue;
      $base_field = merge_options($base_field, $child_field);
    }
    
    return $base_field;
  }

  function get_expanded_field($code)
  {
    $field = $this->get_stack_field(page::$fields_stack, $code);
    $this->get_stack_field($this->page_stack, $code, $field);
    return $field;
  }
  
  function expand_field($code, $path=array(), &$field=null)
  {    
    $stack = $this->get_expanded_field($code);
    $field = merge_options($stack, $field);
    if (is_null($field)) return $code;
        
    array_shift($path);
    if (is_assoc($field)) 
      $this->expand_object($code, $field, $path);
    else
      $this->expand_array($field, $path);

    return $field;
  }
    
  function replace_keys(&$fields)
  {
    foreach($fields as $key=>&$value) {
      if (is_array($value)) 
        $this->replace_keys($value);
      if (is_numeric($key)) continue;
      if ($key[0] != '$') continue;
      $new_key = $this->request[substr($key,1)];
      $fields[$new_key] = $value;
      unset($fields[$key]);
    };
  }
  
  function expand_types($fields)
  {
    walk_recursive($fields, function($value, $key) {
      if (is_string($value) && (is_int($key) || in_array($key, array('type','template'), true)))
        $type = $value;
      else if (!is_array($value))
        return;
      else if (is_string($key))
        $type = $key;
      
      if (isset($this->types[$type])) return;
      $expanded = $this->expand_type($type);
      if (is_null($expanded)) return;
      $this->merge_type($expanded);
      $this->expand_types($expanded);
    });
  }
  
  function filter_access(&$fields)
  {
    walk_recursive_down($fields, function($value, $key, &$parent) {
      if (!is_assoc($parent)) {
        list($type, $value) = assoc_element($value);
        $value = merge_options($this->types[$type], $value); 
        if (!is_array($value)) return;
      }
      else if (!is_assoc($value))
        return;
      else
        $value = merge_options($this->types[$key], $value); 
      
      $added_types = array();
      $this->merge_type($value, $added_types);
      
      if ($this->allowed($value)) return;
      
      unset($parent[$key]);
      foreach($added_types as $type) {
        unset($this->types[$type]);
      }
    },
    function (&$array) {
      if (!is_assoc($array))
        $array = array_values($array);
    });
  }
  
  function read()
  {
    $this->fields = $this->get_stack_field(page::$fields_stack, $this->root);
    $this->get_stack_field($this->page_stack, $this->root, $this->fields);
//    $this->fields = $this->expand_field($this->root);
    $this->expand_types($this->fields);
    $this->filter_access($this->types);
    
    if ($this->user) {
      $this->fields['user_full_name'] = $this->user['full_name'];
    }
    return array(
      'path'=>implode('/',$this->path),
      'fields'=>$this->fields,
      'types'=>$this->types
    );
  }   
  
  function load_sub_page($code, &$fields)
  {
    $request = $this->request;
    
    log::debug_json("LOADING SUB $code", $fields);
    if (!is_array($fields) && !is_assoc($fields)) 
//      $fields = $fields[0];
    
    
    $url = $request['url'];
    if (isset($url) || !isset($this->page_fields[$code])) {
      log::debug("ext load sub $code $url");
      $request['path'] = isset($url)? $url: $code;
      $sub_page = new page($request, $this->db);
      $result = $sub_page->process();
    }
    else {
      $sub_page = new page($request, $this->db);
      $sub_page->path = array($this->path[0], $code);
      $sub_page->page_stack = &$this->page_stack;
      $sub_page->page_fields = &$this->page_fields;
      $result = $sub_page->read();
    }
    unset($fields['type']);
    $result['fields'] = merge_options($result['fields'], $fields);
    $result['type'] = 'page';
    $result['access'] = $result['fields']['access'];
    return $fields = $result;
  }
  
  function expand_params(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value, $key) use ($request) {
      if ($key != 'sql')
        $value = replace_vars ($value, $request);
    });
  }
    
     
  function expand_html($field)
  {
    if (!is_array($field)) return;
    
    $expandables = array('html','template');
    $exclude = array('code','name','desc', 'field');
    foreach($field as $key=>$value) {
      if (!in_array($key, $expandables, true)) continue;
      $matches = array();
      if (!preg_match_all('/\$(\w+)\b/', $value, $matches, PREG_SET_ORDER)) continue;
      
      foreach($matches as $match) {
        $var = $match[1];
        if (in_array($var, $exclude, true)) continue;
        $this->expand_type($var);
      }
    }
  }
   
  function validate($field)
  {
    if (is_null($this->validator)) { 
      $options = merge_options($this->context,$this->request);
      $validators = $this->load_fields('validators.yml');
      $fields = merge_options($this->page_fields, $this->fields);
      $this->validator = new validator(page::merge_options($_SESSION, $options), $fields, $validators);
    }
    //todo: validate only required fields;
    foreach($field as $code=>$values) {
      if (!is_array($values) || in_array($code, $this->validated)) continue;
      if (is_numeric($code)) {
        $code = at($values, 'code');
        if (is_null($code)) {
          $this->validate($values);
          continue;
        }
      }
      $valid = at($values,'valid');
      $this->validated[] = $code;
      if ($valid == '') {
        $this->validate ($values);
        continue;
      }
      $this->validator->validate($code, $values);
    }
    
    return $this->validator->valid();
  }

  function data()
  {
    log::debug_json("DATA ".last($this->path), $this->context);
    return $this->reply($this->context);
  }
  
  function call_method($function, $params)
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
    
    if (!is_callable($function)) {
      log::warn("Uncallable function $function");
      return;
    }
    
    if ($params === '')
      return call_user_func($function);
    
    $params = explode(',', $params);
    $context = merge_options($this->fields, $this->request);
    replace_fields($context, $this->request);
    replace_fields($params, $this->request);
    replace_fields($params, $context);
    log::debug_json("PARAMS", $params);
    foreach($params as &$val) {
      if ($val == 'context') $val = $context;
      if ($val == 'request') $val = $this->request;
    }
    return call_user_func_array($function, $params);
  }
  
  static function merge_options($options1, $options2)
  {
    //return merge_options($options1, $options2);
    if (is_null($options1)) return $options2;
    if (is_null($options2) || sizeof($options2) == 0) return $options1;
    if (!is_array($options2)) return $options2;
    if (!is_assoc($options1) && is_assoc($options2)) return $options2;
    if (is_assoc($options1) && !is_assoc($options2)) return $options2;
    if (!is_assoc($options1)) {
      $new_values = array();
      $inheritables = array('type', 'template', 'action');
      foreach($options1 as $v1) {
        if (!is_array($v1) || array_intersect($inheritables,  array_keys($v1)) === array()) continue;
        $new_values[] = $v1;
      }
      return array_merge($new_values, $options2);
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
      $result[$key] = page::merge_options($value, $value2);
    }
    return $result; 
  }  
  

  
  static function decode_field($message)
  {
    global $db;
    $decodes = array();
    preg_match_all('/decode\((\w+) *, *(\w+)\.(\w+)([=<>]|<>)([^)]+)\)/ms', $message, $decodes, PREG_SET_ORDER);
    foreach($decodes as $decoded) {
      list($match, $display_field, $table,$key_field, $compare, $key) = $decoded;
      $key = addslashes($key);
      $display = $db->read_one_value("select $display_field from $table where $key_field $compare '$key'");
      $message = str_replace($match, $display, $message);
    }
    return $message;
  }
  
  
  static function decode_sql($message)
  {
    $matches = array();
    preg_match_all('/sql\s*\((.+)\)/ims', $message, $matches, PREG_SET_ORDER);
    global $db;
    foreach($matches as $match) {
      $data = $db->read_one($match[1], MYSQLI_NUM);
      $message = str_replace($match[0], implode(' ', $data), $message);
    }
    return $message;
  }
  
  function audit($action, $result)
  {
    global $db;
    $fields = $this->fields[$this->page];
    $name = at($action, 'name');
    if (is_null($name)) {
      $code = at($action, 'code');
      if (is_null($code)) $code = last($this->path);
      $name = ucwords (str_replace ('_', ' ',$code));
    }
    $result = null_merge($fields, $result, false);
    $detail = at($action, 'audit');
    if ($detail) {
      $detail = replace_vars($detail, $user);
      $detail = replace_vars($detail, $result);
      $detail = page::decode_field($detail);
      $detail = page::decode_sql($detail);
    }
    $name = addslashes($name);
    $detail = addslashes($detail);
    $user = $this->read_user(); 
    $user_id = $user['uid'];
    $db->insert("insert into audit_trail(user_id, action, detail)
      values($user_id, '$name', '$detail')");
  }
  
  function action()
  {
    $invoker = $this->context;
    log::debug_json("ACTION ".last($this->path), $invoker);
    $validate = at($invoker, 'validate');
    if (!is_null($validate) && $validate != 'none') {
      if (!$this->validate($this->fields)) return null;
    }
    
    $result = $this->reply($invoker);
    if (!page::has_errors() && array_key_exists('audit', $invoker))
      $this->audit($invoker, $result);
    return $result;
  }
  
  static function replace_sql($sql, $options) 
  {
    global $page; 
    $user = $page->user;
    $user_id = $user['uid'];
    $key = $options['key'];
    if ($user_id)
      $sql = preg_replace('/\$uid([^\w]|$)/', "$user_id\$1", $sql);
    $sql = preg_replace('/\$key([^\w]|$)/', "$key\$1", $sql);
    return replace_vars($sql, $options);
  }
  
  function sql($sql)
  {
    $sql = $this->translate_sql($sql);
    if (preg_match('/\s*select/i', $sql))
      return $this->db->page_through_indices($sql);    
    return $this->db->exec($sql);
  }
  
  function translate_sql($sql)
  {
    $values = null_merge($this->request, $this->reply, false);
    return page::replace_sql($sql, null_merge($this->context, $values));
  }
  
  function sql_values($sql)
  {
    return $this->db->read_one($this->translate_sql($sql), MYSQL_ASSOC);
  }
  
  function sql_exec($sql)
  {
    return $this->db->exec($this->translate_sql($sql));
  }
  
  function update_context(&$options)
  {
    $context = page::merge_options($this->context, $options);
    replace_fields($options, $context);
  }
   
  function set_context($path)
  {
    $context = $this->expand_field($this->root, $path);
    if (!is_null($this->user) && is_assoc($context)) {
      $user = $this->user;
      $context['user_full_name'] = $user['first_name'].  " ". $user['last_name'];
      $context['user_email'] = $user['email'];
      $context['uid'] = $user['uid'];
    }
    $this->context = $context;
  }
  
  function call($method)
  {
    if ($method == '') return null;
    $method = preg_replace('/\$class([^\w]|$)/', "$this->object\$1", $method);
    $method = preg_replace('/\$page([^\w]|$)/', "$this->page\$1", $method); 
    $path_len = sizeof($this->path);
    $invoker = $this->path[$path_len-1];
    $method = preg_replace('/\$invoker([^\w]|$)/', "$invoker\$1", $method);
    $method = preg_replace('/\$default([^\w]|$)/', "$this->object::$this->page\$1", $method);

    $matches = array();
    if (!preg_match('/^([^\(]+)(?:\(([^\)]*)\))?/', $method, $matches) ) 
      throw new Exception("Invalid function spec $method");
    return $this->call_method($matches[1], $matches[2]);    
  }
  
  function reply($actions)
  {
    $this->reply = null;
    $post = at($actions, 'post');
    if (isset($post)) $actions = $post;
    if (is_assoc($actions))  $actions = array($actions);
    
    log::debug_json("REPLY", $actions);
  
    $methods = array('alert', 'abort', 'call', 'clear_session', 'clear_values',
      'close_dialog', 'load_lineage', 'read_session', 'redirect', 'send_email', 
      'show_dialog', 'sql', 'sql_exec','sql_rows','sql_values', 'trigger', 
      'update', 'write_session');
    foreach($actions as $action) {      
      if (!is_array($action)) $action = array("code"=>$action);
      foreach($action as $method=>$parameter) {
        if ($method == 'code') {
          $method = $parameter;
          $parameter = sizeof($action) == 1? null: $action; 
        }
        $matches = array();
        if (preg_match('/^if( +not)? +(\w+) +(\w+)$/', $method, $matches)) {
          $not = $matches[1] != '';
          $check = $this->reply[$matches[2]];
          if (!$check && !$not || $check && $not) continue;
          $method = $matches[3];
        }
        
        if (!in_array($method, $methods)) continue;
        replace_fields($parameter, $this->reply);
        if (!is_array($parameter) || is_assoc($parameter)) $parameter = array($parameter);
        $result = call_user_func_array(array($this, $method), $parameter);
        if ($result === false) return false;
        if (is_null($result)) continue;
        if (!is_array($result)) $result = array($result);
        if (is_null($this->reply)) 
          $this->reply = $result;
        else
          $this->reply = array_merge($this->reply, $result);
      }
    }
    return $this->reply;
  }
  
  function values()   
  {  
    log::debug_json("VALUES '$this->root'", $this->context);
    return $this->reply($this->context);
  }
  
  function upload()
  {
    require_once 'document.php';
    $this->fields = $this->expand_field($this->path[0], $this->path);
    $code = last($this->path);
    $id = document::upload($code."_file", $this->fields['format']);
    if (!is_null($id))
      page::update("id", $id);
  }
    
  static function respond($response, $value=null)
  {
    global $page;
    if (is_null($value)) $value = '';
    $result = &$page->result;
    $values = $result['_responses'][$response];
    if (is_null($values)) 
      $values = $value;
    else if (is_assoc($values))
      $values = array_merge(array($values), array($value));
    else if (is_array($values))
      $values[] = $value;
    else 
      $values = array($values, $value);
    $result['_responses'][$response] = $values;
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
    page::respond('close_dialog', $message);
  }
  
  static function update($name, $value=null)
  {
    global $page;
    $result = &$page->result;
    $responses = &$result['_responses'];
    $updates = &$responses['update'];
    if (is_array($name))
      $updates = null_merge ($updates, $name);
    else
      $updates[$name] = $value;
  }
  
  static function error($name, $value)
  {
    global $page;
    log::debug("ERROR $name $value ");
    $result = &$page->result;
    $errors = &$result['errors'];
    $errors[$name] = $value;
  }
  
  static function collapse($field)
  {
    if (!is_array($field))  return array('code'=>$field);
    foreach($field as $key=>$value) break;
    $field = $field[$key];
    $field['code'] = $key;
    return $field;
  }
  
  static function has_errors()
  {
    global $page;
    $result = &$page->result;
    return !is_null(at($result, 'errors'));
  }
  
  static function trigger($event, $selector=null)
  {
    if (strpos($event, ',') !== false) {
      $args = explode(',',$event);
      list($event, $selector) = $args;
      log::debug_json("TRIGGER ", $args);
    }
    else {
      $args = func_get_args();
    }
    $options = array("event"=>$event);
    if (!is_null($selector)) $options['sink'] = $selector;
    if (sizeof($args) > 2) $options['args'] = array_slice($args,2);
    page::respond('trigger', $options);    
  }
  
  function send_email($options)
  {
    $options = page::merge_options($options, $this->reply);
    $this->update_context($options);
    $options = page::merge_options($this->fields['send_email'], $options);
    $header_array = $options['headers'];
    $header_string = "";
    foreach($header_array as $header) {
      $header = assoc_element($header);
      $header_string .= $header[0] . ": " . $header[1] . "\r\n";
    }
    $from = $options['from'];
    $header_string .= "from: $from\r\n";
    $to = $options['to'];
    $message = $options['message'];
    $subject = $options ['subject'];
    log::debug("SENDMAIL from $from to $to");
    log::debug("HEADERS: $header_string");
    log::debug("SUBJECT: $subject");
    $result = mail($to, $subject, $message, $header_string);
    log::debug("RESULT: $result");
  }
  
  static function preg_match_test($req)
  {
    $pattern = $req['pattern'];
    $subject = $req['subject'];
    $matches = array();
    if ($req['type'] === 'all')
      $result = preg_match_all($pattern, $subject, $matches,PREG_SET_ORDER);
    else {
      $result = preg_match($pattern, $subject, $matches);
      $matches = array($matches);
    }
    page::update('result', $result);
    return array("rows"=>$matches, "total"=>sizeof($matches));
  }

  function write_session()
  {
    $vars = func_get_args();
    if (sizeof($vars) == 1) $vars = explode (',', $vars[0]);
    log::debug_json("WRITE SESSION VARS", $vars);

    foreach($vars as $var) {
      if ($var == 'request' && !isset($this->request['request']))
        call_user_func_array (array($this, 'write_session'), array_keys($this->request));
      else if (isset($this->reply[$var]))
        $_SESSION[$var] = $this->reply[$var];
      else if   (isset($this->request[$var]))
        $_SESSION[$var] = $this->request[$var];
    }
  }

  function read_session()
  {
    $vars = func_get_args();
    if (sizeof($vars) == 1) $vars = explode (',', $vars[0]);

    $values = array();
    foreach($vars as $var) {
      if (isset($_SESSION[$var]))
        $values[$var] = $_SESSION[$var];
    }
    return $values;
    
  }

  static function abort($error_name, $error_message)
  {
    page::error($error_name, $error_message);
    return false;
  }
  
  function load_lineage($key_name, $table, $name, $parent_name)
  {
    global $db;
    $keys = $this->reply[$key_name];
    if (!is_array($keys)) $keys = explode(',', $keys);
    $loaded_values = array();
    foreach ($keys as $value) {
      $values = array($value);
      $db->lineage($values, $name, $parent_name, $table);
      $loaded_values = array_merge($loaded_values, $values);
    }
    return array($key_name=>$loaded_values);
  }
  
  
  function clear_values()
  {
    $this->reply = null;
  }

  
  function clear_session()
  {
    $vars = func_get_args();
    if (sizeof($vars) == 1) $vars = explode (',', $vars[0]);

    $values = array();
    foreach($vars as $var) {
      if (isset($_SESSION[$var]))
        unset($_SESSION[$var]);
    }
  }
  
}