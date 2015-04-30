<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

//require_once 'db.php';
require_once 'validator.php';
require_once 'db.php';
require_once 'user.php';
require_once 'utils.php';

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
  page::show_dialog('/error_page');
}
$page->output();

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
  var $page_offset;
  var $reply;
  var $db;
  
  function __construct($request=null, $user_db=null)
  {
    global $db;
    $this->db = is_null($user_db)?$db: $user_db;
    $this->result = null;
    
    if (is_null($request)) $request = $_REQUEST;
    log::debug_json("REQUEST",json_encode($request));
    $this->request = $request;
    $this->path = explode('/', $request['path']);
    $this->method = $request['action'];

    $this->page_offset = 1;
    
    $this->types = array();

  }
  
  function process()
  {
    if (is_null($this->method))
      throw new Exception("No method parameter in request");
    
    $this->read_user();
    $this->load();
    $result = $this->{$this->method}();
    $this->result = null_merge($result, $this->result, false);
  }
  
  function output()
  {
    if (!is_null($this->result))     
      echo json_encode($this->result);
  }
  
  
  function read_user()
  {
    log::debug_json("SESSION",$_SESSION['instance']);
    if (!isset($_SESSION['instance'])) return;
    require_once 'session.php';
    global $session;
    $session = unserialize($_SESSION['instance']);
    $this->user = $session->user;
    log::debug_json("USER",$this->user);
  }
  
  function load()
  {
    if (sizeof(page::$all_fields) > 0) return;
    $this->load_yaml('controls.yml', false, page::$all_fields); //todo cache common controls
    $this->load_yaml('fields.yml', false, page::$all_fields); //todo cache common fields
  }

  static function load_yaml($file, $strict=false, &$fields=array(), $loading = false)
  {
    if (!$loading) {
      page::load_yaml("../common/$file", false, $fields, true); 
      $strict &= (sizeof($fields) == 0);
      return page::load_yaml($file, $strict, $fields, true);
    }
      
    log::debug("YAML LOAD $file");
    if (!file_exists($file)) {
      if ($strict) throw new Exception("Unable to load file $file");
      return $fields;
    }
    
    $data = yaml_parse_file($file); 
    if (is_null($data))
      throw new Exception ("Unable to parse file $file");
    return $fields = merge_options($fields, $data);
  }

  function load_field($path=null, $expand=array('html','field'))
  {
    if (is_null($path))
      $path = $this->path;
    else if (!is_array($path))
      $path = explode('/', $path);
    
    if (sizeof($path) == 1)
      array_unshift ($path, $path[0]);
   
    $this->object = array_shift($path);
    $this->fields = $this->load_yaml("$this->object.yml", true);
    $this->page = array_shift($path);
    $field  = at($this->fields, $this->page);
    if (is_null($field)) {
      $this->page_offset = 0;
      array_unshift($path, $this->page);
      $this->page = $this->object;
      $field = at($this->fields, $this->page);
    }   

    $this->set_types($this->fields, $field);
    $this->set_types(page::$all_fields, $field);
    $type = at($field, 'type');
    if (!is_null($type)) {
      $field = merge_options(at(page::$all_fields, $type), $field);
      $field = merge_options(at($this->fields, $type), $field);
      unset($field['type']);
    }
    $this->check_access($field);
    if (in_array('html', $expand)) {
      $this->expand_html($field, 'html');
      $this->expand_html($field, 'template');
    }
    if (in_array('field', $expand))
      $this->expand_field($field);
    
    log::debug_json("LOAD FIELD PATH", $path);
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
    return merge_options(at(page::$all_fields, $name), at($this->fields, $name));
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
      'types'=>$this->filter_access($this->types)
    );
  }
    
  static function allow_access(&$options, $key, $value)
  {
    if (is_numeric($key))
      $options[] = $value;
    else
      $options[$key] = $value;
  }
  
  function filter_access($options, $user_roles = null)
  {
    if (is_null($user_roles)) {
      global $session;
      require_once 'session.php';

      $user_roles = array('public');
      if (!is_null($session)) {
        if (!is_null($session->roles)) $user_roles =  $session->roles;
        if (in_array('super', $user_roles, true))
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
      }
      if (!is_array($option)) {
        page::allow_access($filtered, $key, $original);
        continue;
      }
      if (!is_numeric($key)) {
        $option = merge_options(at($this->types, $key), $option);
      }
      $allowed_roles = at($option, 'access');
      if (!is_null($allowed_roles)) {
        if (!is_array($allowed_roles)) $allowed_roles = explode(',', $allowed_roles);
        $allowed = array_intersect($user_roles, $allowed_roles);      //log::debug("PERMITTED $key ".  json_encode($allowed));
        if (sizeof($allowed) == 0) continue;
      }
      $option = $original;
      if (is_array($option))
        $option = $this->filter_access($option, $user_roles);
      if (sizeof($option) == 0) continue;
      page::allow_access($filtered, $key, $option);
    }
    return $filtered;
  }
  
  function expand_sub_pages(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value, $key) use ($request) {
      if ($key !== 'page') return;
      $request['path'] = $value;
      $sub_page = new page($request);
      $sub_page->process();
      $value = $sub_page->result;
    });
  }
  
  function expand_params(&$fields)
  {
    $request = $this->request;
    array_walk_recursive($fields, function(&$value, $key) use ($request) {
      if ($key != 'sql')
        $value = replace_vars ($value, $request);
    });
  }
    
  function set_types($parent, $field)
  {
    if (is_null($field)) return false;
    if (!is_array($field)) {
      if (!array_key_exists($field, $parent)) return false;
      if (array_key_exists($field, $this->types)) {
        $this->types[$field] = merge_options($this->types[$field], $parent[$field]); 
        return true;
      }

      $this->types[$field] = $value = $parent[$field];
      if (is_array($value)) $this->set_types($parent, $value);
      return true;
    }
    
    $known_keys = array('name','desc','html','src', 'href', 'url', 
      'sql','values', 'valid', 'attr', 'sort');
    foreach($field as $key=>&$value) {
      if (in_array($key, $known_keys, 1)) continue;
      
      if (!is_numeric($value) && !is_bool($value))  //todo: check for scalar
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
      if ($valid == '') {
        $this->validate ($values);
        continue;
      }
      
      $name = at($values, 'name');
      if ($name == '') $name = validator::title($code);
      if (!is_array($valid)) $valid = array($valid);
      foreach($valid as $check) {
        if (!$this->validator->check($code, $name)->is($check)) break;
      }
    }
    
    return $this->validator->valid();
  }

  function data()
  {
    $field = $this->load_field(null, array('field'));
    $type = at($field, 'type');
    if (!is_null($type)) {
      $field = merge_options(at(page::$all_fields, $type), $field);
      $field = merge_options(at($this->fields, $type), $field);
      unset($field['type']);
    }
    log::debug("DATA ".json_encode($field));
    return $this->reply($field);
  }
  
  function call_method($function, $params, $options=null)
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
    
    $params = explode(',', $params);
    if (is_array($options)) {
      $options = array_merge($options, $this->request);
      foreach($params as &$param) {
        $param = replace_vars (trim($param), $options);
      }
    }
    else $options = $this->request;
    if (is_array($this->reply)) $options = array_merge($options, $this->reply);
    return call_user_func_array($function, array_merge(array($options), $params));
  }
  
  static function merge_options($options1, $options2)
  {
    //return merge_options($options1, $options2);
    if (!is_array($options2)) return $options1;
    if (!is_array($options1) || !is_assoc($options1) && is_assoc($options2)) return $options2;
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
  

  function merge_type($field, $type=null)
  {
    if (is_null($type)) $type = at($field, 'type');
    if (is_null($type)) return $field;
    $expanded = at($this->types, $type);
    if (is_null($expanded)) 
      throw new Exception("Unknown  type $type specified");
    $super_type = $this->merge_type($expanded);
    return page::merge_options($super_type, $field);
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
          $value = merge_options($default_type, $value);
        $type_value = at($this->types, $key);
        //log::debug("TYPE VALUE ".json_encode($type_value));
        $value = merge_options($type_value, $value);
        //log::debug("EXPANDED ".json_encode($value));
        continue;
      }
      if (!is_string($value) || preg_match('/\W/', $value)) continue;
      $code = $value;
      $value = at($this->types, $code);
      $value = merge_options($default_type, $value);
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
      $value = merge_options($type_value, $value);
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
  
  static function decode_field($message)
  {
    $decodes = array();
    preg_match_all('/decode\(([^,]+)\s*,\s*([\w.]+)\.([^.]+)\s*,\s*(\w+)\)/', $message, $decodes, PREG_SET_ORDER);
    foreach($decodes as $decoded) {
      list($match,$key,$table,$key_field, $display_field) = $decoded;
      $key = addslashes($key);
      $display = $db->read_one_value("select $display_field from $table where $key_field = '$key'");
      $message = str_replace($match, $display, $detail);
    }
    return $message;
  }
  
  
  static function decode_sql($message)
  {
    $matches = array();
    preg_match_all('/sql\s*\(((?>[^()]|(?R))*)\)/', $message, $matches, PREG_SET_ORDER);
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
    if (is_null($name)) $name = ucwords (str_replace ('_', ' ',at($action, 'code')));
    $result = null_merge($fields, $result, false);
    $detail = at($action, 'audit');
    if ($detail) {
      $detail = replace_vars($detail, $result);
      $detail = page::decode_field($detail);
      $detail = page::decode_sql($detail);
    }
    $user = $this->user;
    if (!$user) {
      global $session;
      $user = $session->user;
    }
    $name = addslashes($name);
    $detail = addslashes($detail);
    $db->insert("insert into audit_trail(user_id, action, detail)
      values($user->id, '$name', '$detail')");
  }
  
  function action()
  {
    $invoker = $this->load_field(null, array('field'));
    log::debug_json("action INVOKER ", $invoker);
    $validate = at($invoker, 'validate');
    if (!is_null($validate) && $validate != 'none') {
      $fields = $this->fields[$this->page];
      $this->expand_field($fields);
      if (!$this->validate($fields)) return null;
    }
    
    $result = $this->reply($invoker);
    if (!page::has_errors() && array_key_exists('audit', $invoker))
      $this->audit($invoker, $result);
    return $result;
  }
  
  static function replace_sql($sql, $options) 
  {
    global $session; 
    require_once 'session.php';
    $user = $session->user;
    $user_id = $user->id;
    $key = $options['key'];
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
    return page::replace_sql($sql, db::quote($values));
  }
  
  function sql_values($sql)
  {
    return $this->db->read_one($this->translate_sql($sql), MYSQL_ASSOC);
  }
  
  function sql_exec($sql)
  {
    return $this->db->exec($this->translate_sql($sql));
  }
  
  function call($method)
  {
    $path_len = sizeof($this->path);
    $invoker = $this->path[$path_len-1];
    $context = $this->fields[$this->page];
    log::debug_json("PATH $this->page", $this->path);
    $i = $this->page_offset+1;
    $branch = $this->path[$i];
    for (; $i < $path_len-1; ++$i) {
      $branch = $this->path[$i];
      if (is_assoc($context)) {
       log::debug_json("ASSOC BRANCH $branch ", $context);
       $context = $context[$branch];
        continue;
      }

      log::debug_json("ARRAY BRANCH $branch ", $context);
      foreach($context as $pair) {
        if(!isset($pair[$branch])) continue;
        $context = $pair[$branch];
        break;
      }
    }
    $context = page::merge_options($this->fields[$branch], $context);
    log::debug_json("CALL $invoker $path_len", $context);
    $method = preg_replace('/\$class([^\w]|$)/', "$this->object\$1", $method);
    $method = preg_replace('/\$page([^\w]|$)/', "$this->page\$1", $method); 
    $method = preg_replace('/\$invoker([^\w]|$)/', "$invoker\$1", $method);
    $method = preg_replace('/\$default([^\w]|$)/', "$this->object::$this->page\$1", $method);

    $matches = array();
    if (!preg_match('/^([^\(]+)(?:\(([^\)]*)\))?/', $method, $matches) ) 
      throw new Exception("Invalid function spec $method");
    return $this->call_method($matches[1], $matches[2], $context);    
  }
  
  
  function reply($actions)
  {
    $this->reply = null;
    $post = at($actions, 'post');
    if (isset($post)) $actions = $post;
    if (is_assoc($actions))  $actions = array($actions);
    
    log::debug_json("REPLY", $actions);
   
    $methods = array('alert', 'call', 'close_dialog', 'show', 'show_dialog', 
      'redirect', 'sql', 'sql_exec','sql_rows','sql_values','trigger', 'update');
    foreach($actions as $action) {
      foreach($action as $method=>$parameter) {
        if ($method == 'code') {
          $method = $parameter;
          $parameter = null;
        }
        if (!in_array($method, $methods)) continue;
        $result = $this->{$method}($parameter);
        if ($result === false) return false;
        if (is_null($result)) continue;
        if (is_null($this->reply)) 
          $this->reply = $result;
        else
          $this->reply = array_merge($this->reply, $result);
      }
    }
    return $this->reply;
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
    return $this->reply($options);
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
    if (!is_null($message)) page::alert($message);
    page::respond('close_dialog');
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
    log::debug_json("PAGE", $page);
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
    $result = &$page->errors;
    return !is_null(at($result, 'errors'));
  }
  
  static function trigger($event, $selector=null, $arg1=null)
  {
    $options = array("event"=>$event);
    if (!is_null($selector)) $options['sink'] = $selector;
    if (!is_null($arg1)) $options['args'] = array_slice(func_get_args(),2);
    page::respond('trigger', $options);    
  }
}
