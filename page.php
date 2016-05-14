<?php
require_once 'validator.php';
require_once 'db.php';
require_once 'utils.php';
require_once('q.php');

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
  log::stack($exception);
  log::error("UNCAUGHT EXCEPTION: " . $exception->getMessage() );
  if ($_REQUEST['path'] != 'error_page')
    page::show_dialog('/error_page');
}
$page->output();

class page
{
  static $fields_stack = array();
  static $post_items = array('audit', 'call', 'clear_session', 'clear_values', 'db_name', 'error', 'post',
    'q', 'valid', 'validate', 'write_session');
  static $query_items = array('call', 'read_session', 'read_values', 'ref_list', 'sql', 'sql_values');
  static $atomic_items = array('action', 'attr', 'css', 'html', 'script', 'sql',
    'style', 'template', 'valid');
  static $user_roles = array('public');
  static $non_mergeable = array('action', 'attr', 'audit', 'call', 'clear_session',
    'clear_values', 'load_lineage', 'post', 'read_session', 'refresh', 'show_dialog',
    'sql_insert', 'sql_update', 'style', 'trigger', 'valid', 'validate', 'write_session');
  static $objectify = ['ref_list'];
  static $login_vars = ['uid','pid','roles','groups','email','first_name','last_name','cellphone'];
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
  var $answer;
  var $db;
  var $validated;
  var $aborted;

  var $rendering;
  var $page_stack;
  var $page_fields;
  var $expand_stack;
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
    $this->rendering = $this->method == 'read';
    $this->context = array();
    $this->aborted = false;
    $this->answer = null;
    $this->expand_stack = array();
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

    $this->object = $this->page = $path[0];
    $this->path = $path;
    $this->load();

    log::debug_json("PATH".sizeof($path), $path);
    $this->root = $path[0];
    if (sizeof($path) > 1) {
      $level1 = $this->page_fields[$this->root];
      if (!isset($level1) || !array_key_exists($path[1], $level1)) {
        $this->root = $path[1];
        $this->page = $this->root;
        array_shift($path);
      }
    }
    $this->set_fields();
    if (!$this->rendering)
      $this->set_context($path);
    $result = $this->{$this->method}();
    return $this->result = null_merge($result, $this->result, false);
  }

  function output()
  {
    if ($this->result !== false)
      echo json_encode($this->result);
  }


  function read_user($reload = false)
  {
    if (!$reload && $this->user && $this->user['uid']) return $this->user;
    log::debug_json("SESSION", $_SESSION);
    $user = $this->read_session('uid,pid,roles,groups,email,first_name,last_name,cellphone');
    $user['full_name'] = $user['first_name'] . " ". $user['last_name'];
    if (is_null($user['roles'])) $user['roles'] = array('public');
    $this->user = $user;
    log::debug_json("USER",$this->user);
    return $this->user;
  }

  function load_field_stack($file, &$fields=array(), $search_paths=array('../common', '.'))
  {
    $read_one = false;
    foreach($search_paths as $path) {
      $data = load_yaml("$path/$file");
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

  function allowed($field, $throw=false)
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

  function verify_access($field)
  {
    $this->allowed($field, true);
  }


  function expand_type($type, &$added = array() )
  {
    if (is_array($type)) {
      $result = [];
      foreach($type as $t) {
        $expanded = $this->expand_type($t, $added);
        $result = merge_options($result, $expanded);
      }
      return $result;
    }
    $expanded = $this->types[$type];
    if (isset($expanded) || in_array($type, $this->expand_stack, true)) return $expanded;
    $expanded = $this->get_expanded_field($type);
    if (!is_array($expanded)) return null;
    $added[] = $type;
    $this->expand_stack[] = $type;
    $this->expand_types($expanded);
    $result = $this->types[$type] = $expanded;
    $this->merge_type($expanded);
    array_pop($this->expand_stack);
    return $result;
  }

  function merge_type(&$field, &$added = array())
  {
    $type = $field['type'];
    if (!isset($type) || $type == 'none'  || in_array($type, $this->expand_stack, true)) return $field;
    $expanded = $this->expand_type($type, $added);
    if (is_null($expanded)) {
      log::warn("Unknown type $type");
      return $field;
    }
    if (isset($expanded['type']))
      $field = merge_options($this->merge_type($expanded, $added), $field);
    else
      $field = merge_options($expanded, $field);
    return $field;
  }

  function throw_invalid_path()
  {
    throw new Exception("Invalid path ".implode('/', $this->path));
  }


  function find_array_field($array, $code, $parent)
  {
    $type = null;
    $default = null;
    foreach($array as $value) {
      $values = $own_type = null;
      if (is_assoc($value))
        list($key, $values) = assoc_element($value);
      else
        $key = $value;

      if ($key == 'type') {
        $type = $this->get_merged_field($values);
        continue;
      }
      if ($key == 'default') {
        $default = $values;
        continue;
      }

      if ($key != $code) continue;
      if (is_assoc($values)) {
        $own_type = $values['type'];
        if (is_null($own_type) && !is_null($type))
          $values = merge_options($type, $values);
      }
      else {
        $values = $type;
      }
      $values = $this->get_merged_field($key, $values);
      $this->parent_sow($parent, $key, $values);
      $this->derive_parent($parent, $values);

      return merge_options($default, $values);
    }
    return null;
  }

  function merge_stack($stack)
  {
    $fields = null;
    foreach ($stack as $level) {
      $fields = merge_options($fields, $level);
    }

    return $fields;
  }

 function merge_stack_field(&$stack, $code, &$base_field = null)
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
    $field = $this->merge_stack_field(page::$fields_stack, $code);
    $this->merge_stack_field($this->page_stack, $code, $field);
    return $field;
  }

  function get_merged_field($code, &$field=null)
  {
    if (page::not_mergeable($code)) return $field;
    $field = merge_options($this->expand_type($code), $field);
    return $this->merge_type($field);
  }

  function follow_path($path, $field = null)
  {
    if (is_null($field))  $field = $this->fields;
    $parent = $field;
    foreach($path as $branch) {
      if (is_assoc($field)) {
        $new_parent = $field;
        $field = $field[$branch];
        $this->derive_parent($parent, $field);
        $field = $this->get_merged_field($branch, $field);
        $parent = $new_parent;
      }
      else {
        $field = $this->find_array_field($field, $branch, $parent);
      }
      if (is_null($field))
        $this->throw_invalid_path ();
      $this->verify_access($field);
    }
    return $field;
  }

  function replace_keys(&$fields)
  {
    walk_recursive_down($fields, function($value, $key, &$parent) {
      if (is_numeric($key) || $key[0] != '$') return;
      $new_key = $this->request[substr($key,1)];
      if (!isset($new_key)) return;
      $parent[$new_key] = $value;
      unset($parent[$key]);
    });
  }

  function parent_sow($parent, $key, &$field)
  {
    $sow = $parent['sow'];
    if (!isset($sow)) return $field;
    if (!in_array($key, $sow, true)) return $field;
    return $field = merge_options($parent[$key], $field);
  }

  function derive_parent($parent, &$field)
  {
    $derive = $field['derive'];
    if (!isset($derive)) return $field;
    foreach($derive as $key) {
      $value = $field[$key];
      if (!isset($value))
        $field[$key] = $parent[$key];
      else if (is_array($value))
        $field[$key] = merge_options($parent[$key], $value);
      else if ($value[0] == '$')
        $field[$key] = $parent[substr($value,1)];
    }
  }

  function expand_value($value)
  {
    $matches = array();
    if (!preg_match_all('/\$(\w+)\b/', $value, $matches, PREG_SET_ORDER)) return;

    $exclude = array('classes', 'code', 'id', 'text', 'name','desc', 'field', 'templates');
    foreach($matches as $match) {
      $var = $match[1];
      if (in_array($var, $exclude, true)) continue;
      $this->expand_type($var);
    }
  }


  function expand_types(&$fields)
  {
    walk_recursive_down($fields, function($value, $key, &$parent) {
      if (!is_assoc($parent))
        list($type, $value) = assoc_element($value);
      else
        $type = $key;

      if ($type == $this->page) return;
      $is_style = ($type === 'styles');
      if (in_array($type, ['types', 'type', 'template', 'wrap', 'styles']) ) {
        $type = $value;
        $value = null;
      }
      if (is_null($value)  && in_array($type, page::$objectify)) {
        $value = [];
        $parent[$key] = [$type=>$value];
      }
      else if (!is_null($value) && is_string($value)) {
        $this->expand_value($value);
        return;
      }

      $added_types = array();
      if (is_string($type)) {
        if (isset($this->types[$type])) return;
        $expanded = $this->expand_type($type, $added_types);
      }
      else if (is_assoc($type)) {
        $expanded = $this->merge_type($type, $added_types);
      }
      else if (is_array($type)) {
        $expanded = $this->expand_type($type, $added_types);
      }
      else if ($is_style) {
        foreach ($type as $style) {
          $this->expand_type($style);
        }
      }

      if (!is_null($expanded))
        $this->merge_type($expanded, $added_types);

      $expanded = is_array($value)? merge_options($expanded, $value): $expanded;

      if (is_null($expanded)) return;

      if ($this->allowed($expanded)) return;

      if (!$this->rendering) return;

      unset($parent[$key]);
      foreach($added_types as $type) {
        unset($this->types[$type]);
      }
    },
    function (&$array) {
      array_compact($array);
    });
  }

  static function not_mergeable($key)
  {
    return preg_match('/^if /', $key) || in_array($key, page::$non_mergeable, true);
  }

  static function is_render_item($key)
  {
    return !preg_match('/^if /', $key)
      && !in_array($key, page::$post_items, true)
      && !in_array($key, page::$query_items, true);
  }

  function merge_fields(&$fields, $merged = array())
  {
    if (is_assoc($fields)) {
      if (isset($fields['type']))
        $this->merge_type($fields);
      foreach($fields as $key=>&$value) {
        if (!is_array($value) || page::not_mergeable($key)) continue;
        $value = $this->get_merged_field($key, $value);
        if (!in_array($key, $merged, true)) {
          $merged[] = $key;
          $this->merge_fields($value, $merged);
        }
      }
      return $fields;
    }
    $default_type = null;
    $default = null;
    foreach($fields as &$value) {
      list($key, $field) = assoc_element($value);
      if (page::not_mergeable($key)) continue;
      if ($key == 'type') {
        $default_type = $field;
        continue;
      }
      if ($key == 'default') {
        $default = $field;
        continue;
      }
      if (!is_array($field) && !is_null($field)) continue;
      if (is_array($field) && !is_null($default_type) && !isset($field['type']))
        $field['type'] = $default_type;
      $field = $this->get_merged_field($key, $field);
      if (is_array($field) && !in_array($key, $merged, true)  ) {
        $merged[] = $key;
        $this->merge_fields($field, $merged);
      }
      if (!is_null($default))
        $field = merge_options($default, $field);
      if (is_array($field))
        $value = array($key=>$field);
    }
    return $fields;
  }

  function set_fields()
  {
    $this->fields = $this->merge_stack_field(page::$fields_stack, $this->root);
    $this->merge_stack_field($this->page_stack, $this->root, $this->fields);
    $this->verify_access($this->fields);
    $this->expand_types($this->fields);
  }

  function remove_items(&$fields)
  {
    walk_recursive_down($fields, function(&$value, $key, &$parent) {
      if (!$this->is_render_item($key) || $key === 'access')
        unset($parent[$key]);
      if (in_array($key, page::$query_items, true))
        $parent['query'] = " ";
    });

  }

  function pre_read($fields)
  {
    $this->merge_fields($fields);
    $actions = $fields['read'];
    if (isset($actions)) $this->reply($actions);
  }

  function read()
  {
    $this->pre_read($this->fields);
    $this->types['control'] = $this->get_expanded_field('control');
    $this->types['template'] = $this->get_expanded_field('template');
    $this->remove_items($this->fields);
    $this->remove_items($this->types);
    return array(
      'path'=>implode('/',$this->path),
      'fields'=>$this->fields,
      'types'=>$this->types
    );
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

  function validate($field, $include)
  {
    $options = merge_options($this->context,$this->request);
    $validators = $this->load_fields('validators.yml');
    $fields = merge_options($this->merge_stack(page::$fields_stack), $this->page_fields, $this->fields);
    $this->validator = new validator(page::merge_options($_SESSION, $this->request), $fields, $validators);

    $exclude = array('audit','css','post','script','style', 'styles', 'type','valid','validate','values');

    if (is_string($include) && !is_array($include))
      $include = explode(',', $include);
    if (is_array($include)) {
      $delta_pos = array_search('delta', $include, true);
      if ($delta_pos !== false)
        array_splice($include, $delta_pos, 1, explode(',', $this->request['delta']));
    }
    else
     $include = true;

    $validated = array();
    walk_recursive_down($field, function($value, $key, $parent) use (&$exclude, &$validated, &$include) {
      if (!is_assoc($parent))
        list($code, $value) = assoc_element($value);
      else
        $code = $key;
      if ($include !== true && !in_array($code, $include, true)) return;
      if (in_array($code, $validated, true)) return false;
      if (in_array($code, $exclude, true)) return false;
      if (!is_null($value) && !is_array($value)) return false;


      $this->get_merged_field($code, $value);
      $valid = $value['valid'];
      if ($valid == "") return;

      $validated[] = $code;
      $validator = &$this->validator;
      $result = $validator->check($code, $value)->is($valid);
      if ($result === true) return;

      $error = $validator->error;
      if (is_array($error)) {
        list($error) = find_assoc_element($error, 'error');
        $this->reply($validator->error);
      }
      if (!is_null($error))
        page::error($code, $error);

    });
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
    $context = merge_options($this->fields, $this->context, $_SESSION, $this->request, $this->answer);
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
      $sowables = array('type', 'template', 'action');
      foreach($options1 as $v1) {
        if (!is_array($v1) || array_intersect($sowables,  array_keys($v1)) === array()) continue;
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

  function field_name($code, $field=null)
  {
    if (is_null($field)) $field = $this->get_merged_field($code);
    if (is_null($field) || is_null($field['name'])) return ucwords (str_replace ('_', ' ',$code));
    return $field['name'];
  }

  function audit_delta(&$detail)
  {
    if (strpos($detail, '$delta') === false) return true;
    $fields = trim($this->request['delta']);
    if ($fields == '') return $detail != '$delta';
    foreach(explode(',', $fields) as $key) {
      $delta[] .= $this->field_name($key) . ": ". $this->request[$key];
    }
    $detail = str_replace('$delta', implode(', ', $delta), $detail);
    return true;
  }

  function name($field)
  {
    $name = at($field, 'name');
    if (!is_null($name)) return $name;
    $code = last($this->path);
    return ucwords (str_replace ('_', ' ',$code));
  }

  function audit($action, $result)
  {
    global $db;
    $fields = $this->fields[$this->page];
    $result = null_merge($fields, $result, false);
    $detail = at($action, 'audit');
    $field = [];
    $context = merge_options($this->fields, $this->context, $_SESSION, $this->request, $result);
    if (is_array($detail)) {
      $field = $detail;
      $detail = $field['detail'];
      replace_fields($field, $context, true);
    }
    if ($detail) {
      $detail = replace_vars($detail, $user);
      if (!$this->audit_delta($detail)) return;
      $detail = replace_vars($detail, $context);
      $detail = page::decode_field($detail);
      $detail = page::decode_sql($detail);
      $detail = replace_vars($detail,$this->request);
    }
    $name = $field['action'];
    if (!isset($name)) $name = $this->name($action);
    $name = addslashes($name);
    $detail = addslashes($detail);
    $user = $this->read_user();
    $user = $user['uid'];
    $partner = $field['partner'];
    $db->insert("insert into audit_trail(user, partner, action, detail)
      values('$user', '$partner', '$name', '$detail')");

    $post = $field['post'];
    if (isset($post))
      $this->reply($post);
  }

  function action()
  {
    $invoker = $this->context;
    log::debug_json("ACTION ".last($this->path), $invoker);
    if (!isset($this->request['id'])) $this->request['id'] = last($this->path);
    $this->merge_fields($this->fields);
    $validate = at($invoker, 'validate');
    if ($validate != 'none' && !$this->validate($this->fields, $validate))
      return null;

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
    $partner_id = $user['pid'];
    $key = $options['key'];
    if (isset($user_id))
      $sql = preg_replace('/\$uid([^\w]|$)/', "$user_id\$1", $sql);
    if (isset($partner_id))
      $sql = preg_replace('/\$pid([^\w]|$)/', "$partner_id\$1", $sql);
    $sql = preg_replace('/\$key([^\w]|$)/', "$key\$1", $sql);
    return replace_vars($sql, $options, function(&$val) {
      $val = addslashes($val);
    });
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
    $sql = page::replace_sql($sql, $this->answer);
    $sql = page::replace_sql($sql, $this->context);
    return page::replace_sql($sql, $this->request);
  }

  function sql_values($sql)
  {
    $sql = $this->translate_sql($sql);
    return $this->foreach? $this->db->read($sql, MYSQL_ASSOC): $this->db->read_one($sql, MYSQL_ASSOC);
  }

  function sql_exec($sql)
  {
    return $this->db->exec($this->translate_sql($sql));
  }

  function get_db_name($arg)
  {
    $field = $this->get_merged_field($arg);
    return isset($field) && isset($field['db_name'])? $field['db_name']: $arg;
  }

  function get_sql_pair($arg)
  {
    if (!is_array($arg)) return [$this->get_db_name($arg), "'\$$arg'"];

    list($arg,$value) = assoc_element($arg);
    if ($value[0] == '/')
      $value = substr($value,1);
    else
      $value = "'". addslashes($value). "'";
    return [$this->get_db_name($arg), $value];
  }

  function parse_delta(&$args)
  {
    $delta_index = array_search('delta', $args, true);
    if ($delta_index === false) return;

    $delta = trim($this->request['delta']);
    $delta = $delta==''? null: explode(',', $delta);
    array_splice($args, $delta_index, 1, $delta);
  }

  function sql_update()
  {
    $args = page::parse_args(func_get_args());
    $table = array_shift($args);
    list($key_name,$key_value) = $this->get_sql_pair(array_shift($args));
    if (!isset($key_value)) $key_value = "\$$key_name";
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for sql_update");


    $this->parse_delta($args);

    if (!sizeof($args)) return null;

    $fields = $this->db->field_names($table);
    $sets = array();
    foreach($args as $arg) {
      list($arg,$value) = $this->get_sql_pair($arg);
      if (!in_array($arg, $fields)) continue;
      $sets[] = "$arg = $value";
    }
    if (!sizeof($sets)) return null;

    $sets = implode(',', $sets);

    $sql = "update $table set $sets where $key_name = $key_value";
    return $this->sql_exec($sql);
  }

  function sql_insert()
  {
    $args = page::parse_args(func_get_args());
    $table = array_shift($args);
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for sql_insert");
    $values = array();
    foreach($args as &$arg) {
      list($arg,$value) = $this->get_sql_pair($arg);
      $values[] = $value;
    }
    $args = implode(',', $args);
    $values = implode(',', $values);
    $sql = "insert $table($args) values($values)";
    $this->sql_exec($sql);
    return $this->sql_values("select last_insert_id() new_${table}_id");
  }

  function sql_select()
  {
    $args = func_get_args();
    $table = array_shift($args);
    $key = array_shift($args);
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for sql_select");
    $sql = "select from $table where $key = '\$$key'";
    return $this->sql_exec($sql);
  }

  function expand_ref_list(&$field, $code)
  {
    if (is_string($field))
      $field = ['list'=>$field];
    else if (!isset($field['list']))
      $field['list'] = $code;
    $base = $this->get_expanded_field('ref_list');
    $field = merge_options($base, $field);
    replace_fields($field, $field, true);
  }

  function ref_list($field)
  {
    $this->expand_ref_list($field, $this->path[sizeof($this->path)-2]);
    return $this->sql($field['sql']);
  }

  function update_context(&$options)
  {
    $context = page::merge_options($this->context, $options);
    replace_fields($options, $context);
  }

  function set_context($path)
  {
    $context = $this->follow_path($path);
    if (!is_null($this->user) && is_assoc($context)) {
      $user = $this->user;
      $context['user_full_name'] = $user['first_name'].  " ". $user['last_name'];
      $context['user_email'] = $user['email'];
      $context['uid'] = $user['uid'];
    }
    $context['server_host'] = page::base_url();
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

  function reply_if($method, $args)
  {
    $matches = array();
    if (!preg_match('/^if\s+(.+)$/', $method, $matches)) return false;

    if (sizeof($args) < 1) throw new Exception("Invalid number of parameters for 'if'");
    $condition = $matches[1];
    if (eval("return $condition;"))
      $this->reply($args);
    return true;
  }

  function reply($actions)
  {
    $post = at($actions, 'post');
    if (isset($post)) $actions = $post;
    if (is_null($actions)) return null;
    if (is_assoc($actions))  $actions = array($actions);
    $this->merge_fields($actions);

    log::debug_json("REPLY ACTIONS", $actions);

    $methods = array('abort', 'alert', 'assert', 'call', 'clear_session', 'clear_values',
      'close_dialog', 'foreach', 'let', 'load_lineage', 'logoff', 'read_session', 'read_values',
       'redirect', 'ref_list', 'show_dialog', 'show_captcha', 'sql', 'sql_exec',
       'sql_rows', 'sql_insert','sql_update', 'sql_values', 'refresh', 'trigger',
       'update', 'upload', 'view_doc', 'write_session');
    foreach($actions as $action) {
      if ($this->aborted) return false;
      if (is_array($action)) {
        list($method, $parameter) = assoc_element($action);
      }
      else {
        $method = $action;
        $parameter = array();
      }
      if (is_null($parameter))
        $parameter = array();
      else if (!is_array($parameter) || is_assoc($parameter))
        $parameter = array($parameter);
      global $config;
      if ($method != 'foreach') {
        $values = merge_options($this->request, $config, $this->context, $this->answer);
        replace_fields($parameter, $values);
        replace_fields($method, $values);
      }
      log::debug_json("REPLY ACTION $method", $parameter);
      if ($this->reply_if($method, $parameter)) continue;
      if (is_callable("q::$method")) {
        array_unshift($parameter, $method);
        $method = 'q';
      }
      else if (is_function($method)) {
        $parameter = [$method];
        $method = 'call';
      }
      else if (!in_array($method, $methods))
	      continue;
      if ($method == 'foreach')
        $result = $this->reply_foreach($parameter);
      else
        $result = call_user_func_array(array($this, $method), $parameter);
      if ($result === false) {
        $this->aborted = true;
        return false;
      };
      if ($this->foreach) return $result;
      if (is_null($result)) continue;
      if (!is_array($result)) $result = array($result);
      if (is_null($this->answer))
        $this->answer = $result;
      else
        $this->answer = array_merge($this->answer, $result);
    }
    return $this->answer;
  }

  function values()
  {
    log::debug_json("VALUES '$this->root'", $this->context);
    $values = $this->context['values'];
    if (is_null($values)) $values = $this->context;
    return $this->reply($values);
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

  function redirect($url)
  {
    if (!is_array($url)) $url = array("url"=>$url);
    $url['url'] = replace_vars($url['url'], $this->context);
    log::debug_json("REDIRECT", $url);
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
    $responses = &$result['_responses'];
    $errors = &$responses['errors'];
    $errors[$name] = $value;
  }

  static function collapse($field)
  {
    if (!is_array($field))  return array('id'=>$field);
    foreach($field as $key=>$value) break;
    $field = $field[$key];
    $field['id'] = $key;
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
      $args = page::parse_args(func_get_args());
    }
    $options = array("event"=>$event);
    if (!is_null($selector)) $options['sink'] = $selector;
    if (sizeof($args) > 2) $options['params'] = array_slice($args,2);
    page::respond('trigger', $options);
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
    $vars = page::parse_args(func_get_args());
    $this->parse_delta($vars);
    foreach($vars as $var) {
      if ($var == 'request' && !isset($this->request['request']))
        call_user_func_array (array($this, 'write_session'), array_keys($this->request));
      else if (is_array($var)) {
        list($var,$value) = assoc_element($var);
        $_SESSION[$var] = $value;
      }
      else if (isset($this->answer[$var]))
        $_SESSION[$var] = $this->answer[$var];
      else if   (isset($this->request[$var]))
        $_SESSION[$var] = $this->request[$var];
    }
  }

  function read_session()
  {
    $vars = page::parse_args(func_get_args());
    if (sizeof($vars) == 0) return $_SESSION;
    $values = array();
    foreach($vars as $var) {
      if (isset($_SESSION[$var]))
        $values[$var] = $_SESSION[$var];
    }
    return $values;
  }

  function read_session_list()
  {
    $values = call_user_func_array(array($this, 'read_session'), func_get_args());
    return array_values($values);
  }

  function read_values($values)
  {
    replace_fields($values, $this->answer, true);
    replace_fields($values, $this->request, true);
    replace_fields($values, $_SESSION, true);
    replace_fields($values, $this->context, true);
    return $values;
  }

  function let($values)
  {
    return $this->read_values($values);
  }

  static function abort()
  {
    $args = page::parse_args(func_get_args());
    if (sizeof($args) > 1) {
      list($name, $message) = $args;
      page::error($name, $message);
    }
    return false;
  }

  function load_lineage($key_name, $table, $name, $parent_name)
  {
    global $db;
    $keys = $this->answer[$key_name];
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
    $args = page::parse_args(func_get_args());
    if (sizeof($args) == 0) {
      $this->answer = null;
      return;
    }
    foreach($args as $arg)
    {
      unset($this->answer[$arg]);
    }
  }

  static function parse_args($args)
  {
    if (sizeof($args) != 1 || is_assoc($args[0])) return $args;
    $args = explode (',', $args[0]);
    foreach($args as &$arg) {
      $arg = trim($arg);
    }
    return $args;
  }

  function clear_session()
  {
    $vars = page::parse_args(func_get_args());
    if (sizeof($vars) == 0) $vars = array_keys($_SESSION);
    foreach($vars as $var) {
      if (isset($_SESSION[$var]) && !in_array($var, page::$login_vars))
        unset($_SESSION[$var]);
    }
  }

  static function is_displayable($field)
  {
    if (!is_array($field)) return true;
    if (isset($field['hide']) && $field['hide']) return false;
    if (isset($field['show']) && !$field['show']) return false;
    return true;
  }

  static function refresh($sink)
  {
    page::trigger("refresh,$sink");
  }

  function calender()
  {
    global $db;
    $advance = $req['advance'];
    $day1 = $req['month'];
    $key = $req['key'];
    if (is_null($day1)) $day1 = Date('Y-m-01');
    if ($advance != 0) $day1 = $db->read_one_value("select '$day1' + interval $advance month");

    list($month, $month_name) = $db->read_one("select month('$day1'), date_format('$day1', '%M %Y')", MYSQLI_NUM);

    $sql = " select i,
  concat('<div',if(month(sun)=1,'',' class=\"other\"'),'>',day(sun),'</div>') sun,
  concat('<div',if(month(mon)=1,'',' class=\"other\"'),'>',day(mon),'</div>') mon,
  concat('<div',if(month(tue)=1,'',' class=\"other\"'),'>',day(tue),'</div>') tue,
  concat('<div',if(month(wed)=1,'',' class=\"other\"'),'>',day(wed),'</div>') wed,
  concat('<div',if(month(thu)=1,'',' class=\"other\"'),'>',day(thu),'</div>') thu,
  concat('<div',if(month(fri)=1,'',' class=\"other\"'),'>',day(fri),'</div>') fri,
  concat('<div',if(month(sat)=1,'',' class=\"other\"'),'>',day(sat),'</div>') sat
  from
    (select i, '$day1' - interval (dayofweek('2016-01-1')-1-i*7) day sun,
        '2016-01-1' - interval (dayofweek('2016-01-1')-2-i*7) day mon,
        '2016-01-1' - interval (dayofweek('2016-01-1')-3-i*7) day tue,
        '2016-01-1' - interval (dayofweek('2016-01-1')-4-i*7) day wed,
        '2016-01-1' - interval (dayofweek('2016-01-1')-5-i*7) day thu,
        '2016-01-1' - interval (dayofweek('2016-01-1')-6-i*7) day fri,
        '2016-01-1' - interval (dayofweek('2016-01-1')-7-i*7) day sat
        from integers where i  < 6) tmp";

    $rows = $db->read($sql, MYSQLI_NUM);

    return array("month"=>$day1, "month_name"=>$month_name,"rows"=>$rows, "total"=>6);
  }

  function show_captcha()
  {
    log::debug("showing captcha");
    require_once('../common/captcha.php');
  }

  static function base_url()
  {
    $protocol = 'http';
    if(isset($_SERVER['HTTPS']))
      $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
  }

  function upload()
  {
    $code = last($this->path);
    $this->merge_fields($this->context);
    $pre_upload = $this->context['pre_upload'];
    if ($pre_upload) {
      $pre_upload = $this->reply($pre_upload);
      if ($pre_upload === false) return false;
    }
    $partner_id = $pre_upload['partner'];
    if (!isset($partner_id)) $partner_id = $_SESSION['pid'];
    global $config;
    $options = [
        'control' => "file_$code",
        'allowed_exts' => $this->context['allowed_extensions'],
        'type' => page::field_name($code),
        'user_id' => $_SESSION['uid'],
        'partner_id' => $partner_id,
        'path' => $config['upload_path']
      ];

    require_once 'document.php';
    $result = document::upload($options);
    if (!is_array($result)) return page::error($code, $result);

    return array_merge(['partner_id'=>$partner_id], $result);
  }


  function view_doc($id)
  {
    //todo: verify permissions
    require_once 'document.php';
    document::view($id);
  }

  function assert($condition)
  {
    if (!eval("return $condition;"))
      throw new Exception("Assert failed for $condition");
  }

  function q($method, $args)
  {
    $merge = $args['merge'];
    if (!isset($merge) || $merge != false) {
      $args = page::merge_options($this->answer, $args);
      $this->update_context($args);
      global $config;
      $args = page::merge_options($this->get_expanded_field($method), $args);
      $args = page::merge_options($config[$method], $args);
      replace_fields($args,$args,true);
    }
    return q::put($method, $args);
  }

  function reply_foreach($args)
  {
    $this->foreach = true;

    $data = $this->reply(array_shift($args));
    if ($data === false) return false;
    $this->foreach = false;
    foreach($data as $row) {
      $this->answer = array_merge($this->answer, $row);
      foreach($args as $arg) {
        if ($this->reply($arg) === false) return false;
      }
      ++$i;
    }
    return null;
  }

  function logoff()
  {
    session_destroy();
    $_SESSION = [];
    $this->read_user(true);
  }
}
