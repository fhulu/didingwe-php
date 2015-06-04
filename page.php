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
    log::debug_json("REQUEST",$request);
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
  
  
  function read_user($reload = false)
  {
    if (!$reload && $this->user && $this->user['uid']) return $this->user;
    log::debug_json("SESSION", $_SESSION);
    $user = $this->read_session('uid,partner_id,roles,groups,email_address,first_name,last_name,cellphone');
    if (is_null($user['roles'])) $user['roles'] = array('public');
    $this->user = $user;
    log::debug_json("USER",$this->user);
    return $this->user;
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

  
  static function read_step($field, $step)
  {
    $step_field = at($field, $step);
    if (!is_null($step_field)) return array($step=>$step_field);   
    foreach($field as $values) {
      if (is_string($values) && $values == $step) return $values;
      $step_field = at($values, $step);
      if (!is_null($step_field)) return array($step=>$step_field);
      $code  = at($values, 'code');
      if ($code == $step) return array($step=>$values);
      if (is_array($values) && !is_assoc($values)) 
        return page::read_step ($values, $step);
    }
    return null;
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
      $step_field = page::read_step($field, $step);
      if (is_null($step_field)) {
        log::error("MISTEP ".json_encode($field));
        throw new Exception("Invalid path step $step on ".implode('/', $path));
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
      
    return is_null($step)?$field:$field[$step];
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
      $fields['user_full_name'] = "$user[first_name] $user[last_name]";
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
  
  function filter_access($options)
  {
    $user_roles = $this->user['roles'];
    if (in_array('super',$user_roles)) return $options;
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
        $allowed = array_intersect($user_roles, $allowed_roles);      
        if (sizeof($allowed) == 0) continue;
      }
      $option = $original;
      if (is_array($option))
        $option = $this->filter_access($option);
      if (sizeof($option) == 0) continue;
      page::allow_access($filtered, $key, $option);
    }
    return $filtered;
  }
  
  function expand_sub_pages(&$fields)
  {
    $request = $this->request;
    walk_recursive($fields, function(&$value, $key) use ($request) {
      if (!is_array($value) && $key !== 'page' 
        || is_array($value) && $value['type'] !== 'page') return;
      if ($key === 'page') 
        $path = $value;
      else if (!is_null($value['url']))
        $path = $value['url'];
      else 
        $path = $key;
      $request['path'] = $path;
      $sub_page = new page($request);
      $sub_page->process();
      $sub_page->result['fields'] = page::merge_options($value, $sub_page->result['fields']);
      $value = $sub_page->result;
      if ($key !== 'page') $value['type'] = 'page';
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
    if (is_null($this->validator)) {
      $options = page::merge_options($this->request, $this->get_context());
      $this->validator = new validator(page::merge_options($_SESSION, $options));
    }
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
    if (sizeof($params) > 0) {
      $options = $this->get_context();
      if (is_array($options)) {
        $options = array_merge($options, $this->request);
        foreach($params as &$param) {
          $param = replace_vars (trim($param), $options);
        }
      }
      else $options = $this->request;
      if (is_array($this->reply)) $options = array_merge($options, $this->reply);
      $options = page::merge_options($this->fields[$this->page], $options);

      $context = array_merge($_SESSION, $options, $this->request);
      array_walk($params, function(&$val) use (&$context) {
        if ($val == 'context') $val = $context;
        if ($val == 'request') $val = $this->request;
      }); 
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
  

  function merge_type($field, $type=null)
  {
    if (is_null($type)) $type = at($field, 'type');
    if (is_null($type)) return $field;
    $expanded = is_array($type)? $type: at($this->types, $type);

    if (is_null($expanded)) {
      $expanded = at(page::$all_fields, $type);
      if (is_null($expanded)) 
        throw new Exception("Unknown  type $type specified");
      $this->type[$type] = $expanded;
    }
    $super_type = $this->merge_type($expanded);
    return page::merge_options($super_type, $field);
  }
  
  function expand_contents(&$parent, $known)
  {
    $default_type = null;
    $length = sizeof($parent);
    $result = array();
    $reserved = array('sql', 'action', 'template', 'attr','valid');
    $found = array();
    foreach($parent as &$value) {
      if (is_array($value)) {
        $type = at($value, 'type');
        if (!is_null($type) && !is_array($type)) {
          $default_type = at($this->types, $type);
          continue;
        }

        if (!is_null($value['code']))
          $code = $value['code'];
        else
          list($code, $element) = assoc_element ($value);
        
        if (in_array($code, $reserved)) continue;

        $type_value = $this->get_type($code);
        $element = merge_options($type_value, $element);
        $my_type = at($element, 'type');
        $element = page::merge_type($element, is_null($my_type)?$default_type: $my_type);
        if (is_array($element) && $element['merge'] && $found[$code]) {
          $found[$code] = merge_options($found[$code], $element);
          continue;
        }
        $value[$code] = $element;
        $found[$code] = &$value[$code];
        continue;
      }
      if (!is_string($value) || preg_match('/\W/', $value)) continue;
      $value = array($value=>merge_options($default_type, at($this->types, $value)));
    }
    
  }

  function get_type($type, $known=array())
  {
    $expanded = at($this->types, $type);
    if (is_null($expanded)) {
      $expanded = page::merge_options(at(page::$all_fields, $type), at($known, $type));
      if (!is_null($expanded)) $this->types[$type] = $expanded;        
    }
    return $expanded;
  }
  
  function expand_field(&$field, $known=array())
  {
    $reserved = array('action','valid');
    foreach ($field as $key=>&$value) {
      if (in_array($key, $reserved)) continue;
      if (is_numeric($key)) {
        $this->expand_contents($field, $known);
        break;
      }
      $known_value = $known[$key];
      
      if (!is_array($value)) {
        if (!$known_value) continue;
        $value = $known_value;
      }
      else if ($known_value) {
        $value = page::merge_options ($known_value, $value);
      }
      $type_value = $this->get_type($key);
      $value = merge_options($type_value, $value);
      $this->expand_field($value, $known);
      $known[$key] = $value;
    }
  }

  function check_access($field, $throw=false)
  {
    $allowed_roles = at($field, 'access');
    if (is_array($allowed_roles)) $allowed_roles = last($allowed_roles);
    if ($allowed_roles == '') return true;

    $user_roles = $this->user['roles'];    
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
    $invoker = $this->load_field(null, array('field'));
    log::debug_json("ACTION", $invoker);
    $this->check_access($invoker, true);
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
    $context = $this->get_context();
    $values = null_merge($this->request, $this->reply, false);
    return page::replace_sql($sql, null_merge($context, $values));
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
    $context = page::merge_options($this->get_context(), $options);
    replace_fields($options, $context);
  }
   
  function get_context()
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
       $context = $context[$branch];
        continue;
      }

      foreach($context as $pair) {
        if(!isset($pair[$branch])) continue;
        $context = $pair[$branch];
        break;
      }
    }
    if (!is_null($this->user)) {
      $user = $this->user;
      $context['user_full_name'] = $user['first_name'].  " ". $user['last_name'];
      $context['user_email'] = $user['email'];
      $context['uid'] = $user['uid'];
    }
    if (is_array($context) && !is_assoc($context)) $context = $context[0];
    $new_context = at($context, $invoker);
    return page::merge_options($this->fields[$branch], $new_context?$new_context:$context);
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
  
  function upload()
  {
    $options = $this->load_field(null, array('field'));
    log::debug_json("UPLOAD", $options);
    require_once 'document.php';
    $id = document::upload($options['code']."_file", $options['format']);
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
      if (isset($this->reply[$var]))
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