<?php
require_once 'validator.php';
require_once 'db.php';
require_once 'utils.php';
require_once('q.php');

class user_exception extends Exception {};

try {
  global $page;
  $page = new page();
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
  static $query_items = array('call','read_session', 'read_values', 'ref_list', 'sql', 'sql_values', 'refresh');
  static $atomic_items = array('action', 'attr', 'css', 'html', 'script', 'sql',
    'style', 'template', 'valid');
  static $user_roles = array('public');
  static $non_mergeable = array('action', 'attr', 'audit', 'call', 'clear_session',
    'clear_values', 'error', 'for_each', 'load_lineage', 'keep_values', 'read_session', 'refresh', 'show_dialog',
    'sql_insert', 'sql_update', 'style', 'trigger', 'valid', 'validate', 'write_session');
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
  var $sub_page;
  var $includes;
  var $modules;

  function __construct($request=null, $user_db=null)
  {
    global $db;
    $this->db = is_null($user_db)?$db: $user_db;
    $this->result = null;
    $this->includes = [];

    if (is_null($request)) $request = $_REQUEST;
    log::debug_json("REQUEST",$request);
    $this->request = $request;
    $this->path = replace_vars($request['path'], $request);
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
    $this->sub_page = false;
    $this->modules = ['this'=>$this];
  }

  function process()
  {
    if (is_null($this->method))
      throw new Exception("No method parameter in request");

    $this->roles = $this->get_module('auth')->get_roles();
    log::debug_json("SESSION", $_SESSION);
    $path = $this->path;
    if ($path[0] == '/') $path = substr ($path, 1);

    $path = explode('/', $path);
    if (last($path) == '') array_pop($path);

    $this->object = $this->page = $path[0];
    if (sizeof($path) < 2)
      array_unshift($path, $path[0]);
    $this->path = $path;
    $this->load();

    $this->root = $path[0];
    log::debug_json("PATH".sizeof($path), $path);
    $this->root = $path[1];
    $this->set_fields();
    if (!$this->rendering)
      $this->set_context();
    if ($this->sub_page) return;
    $result = $this->{$this->method}();
    return $this->result = null_merge($result, $this->result, false);
  }

  function output()
  {
    if ($this->result !== false)
      echo json_encode($this->result);
    log::debug_json("OUTPUT", $this->result);
  }


  function include_external(&$data)
  {
    $files = $data['include'];
    if (!isset($files)) return;
    $fields = [];
    foreach($files as $file) {
      if (in_array($file, $this->includes, true)) continue;
      $this->includes[] = $file;
      $this->load_field_stack($file, $fields);
    }
    $fields = $this->merge_stack($fields);
    $data = merge_options($fields, $data);
  }

  function load_field_stack($file, &$fields=array())
  {
    $read_one = false;
    $languages = [''];
    global $config;
    $search_paths = $config['search_paths'];
    if ($this->request['lang']) $languages[] = ".". $this->request['lang'];
    foreach($languages as $lang) {
      foreach($search_paths as $path) {
        $data = load_yaml("$path/$file$lang.yml");
        if (is_null($data)) continue;
        $read_one = true;

        $this->include_external($data);
        $this->replace_keys($data);
        $fields[] = $data;
      }
    }
    if (!$read_one)
      throw new Exception("Unable to load file $file");
    return $fields;
  }

  function load()
  {

    if (sizeof(page::$fields_stack) == 0) {
      $this->load_field_stack('controls', page::$fields_stack);
      $this->load_field_stack('fields', page::$fields_stack);
    }

    if (sizeof($this->page_stack) != 0) return;

    $this->load_field_stack($this->path[0], $this->page_stack);
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
    $partner_access = $field['partner_access'];
    $restricted = isset($access) || isset($partner_access);
    if (!$restricted || $this->get_module('auth')->authorized($access, $partner_access)) return true;
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
    if (!$expanded['sub_page'])
      $expanded = merge_options($expanded, $this->get_expanded_field($type));
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
    if (is_string($type) && strpos($type, '$') !== false) {
      global $config;
      $new_type = replace_vars($type, $this->request);
      $new_type = replace_vars($new_type, $config);
      if ($type != $new_type) $field['type'] = $new_type;
      $type = $new_type;
    }
    $expanded = $this->expand_type($type, $added);
    if (is_null($expanded))
      throw new Exception("Unknown type $type");

    if (isset($expanded['type']))
      $field = merge_options($this->merge_type($expanded, $added), $field);
    else
      $field = merge_options($expanded, $field);
    return $field;
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

      if ($key[0] == '$') {
        $key = substr($key, 1);
        $values = merge_options($values, $parent[$key]);
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

  function read_external($path)
  {
    $request = $this->request;
    $request['path'] = $path;
    $page = new page($request);
    $page->sub_page = true;
    $page->process();
    $this->types = merge_options($page->types, $this->types);
    return $page->fields;
  }

  function get_expanded_field($code)
  {
    $matches = [];
    if (preg_match('/^(\/\w+|\w+\/)([\w\/]*)$/', $code, $matches)) {
      $page = str_replace('/','', $matches[1]);
      if ($page != $this->page)
        return $this->read_external($code);
      $code = str_replace('/','', $matches[2]); //todo: take care of inner paths greater than 2
    }
    $field = $this->merge_stack_field(page::$fields_stack, $code);
    $this->merge_stack_field($this->page_stack, $code, $field);
    return $field;
  }

  function get_merged_field($code, &$field=null)
  {
    if (page::not_mergeable($code)) return $field;
    if (is_null($field))
      return $field = $this->expand_type($code);
    $merged = $field;
    $this->merge_type($merged);
    return $field = merge_options($this->expand_type($code), $merged, $field);
  }

  function follow_path()
  {
    $path = $this->path;
    array_splice($path,0,2);
    $field = $this->fields;
    foreach($path as $branch) {
      if (is_assoc($field)) {
        $new_parent = $field;
        $field = $field[$branch];
        // $this->merge_fields($field, $parent);
        if ($parent)
          $this->derive_parent($parent, $field);
        $parent = $new_parent;
      }
      else {
        $field = $this->find_array_field($field, $branch, $parent);
      }
      if (is_null($field))
        throw new Exception("Invalid path ".implode('/', $this->path) . " on branch $branch");
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

  static function is_module($x, &$class="", &$method="")
  {
    if (!is_string($x)) return false;
    global $config;
    list($class, $method) = explode('.', $x);
    return $class == 'this' || in_array($class, $config['modules']);
  }

  function get_module($class, &$method="")
  {
    if (!page::is_module($class, $class, $method)) return false;
    $module = $this->modules[$class];
    if ($module) return $module;
    require_once("$class.php");
    $this->modules[$class] =  $module = new $class($this);
    return $module;

  }
  function get_module_method($x)
  {
    $module = $this->get_module($x, $method);
    if (!$module || !$method) return false;
    return [$module,$method];
  }

  function replace_vars(&$fields)
  {
    do {
      $replaced = false;
      walk_recursive_down($fields, function(&$value, $key, &$parent) use (&$replaced) {
        if (in_array($key, ['action','post', 'audit', 'values', 'valid', 'validate'])) return false;
        if (!is_string($value) || strpos($value, '$') === false) return;
        global $config;
        $new_value = replace_vars($value, $this->request);
        if ($new_value != $value) $replaced = true;
        $value = replace_vars($new_value, $config);
        if ($new_value != $value) $replaced = true;
      });
    } while ($replaced);
  }


  function expand_types(&$fields)
  {
    $this->replace_vars($fields);
    walk_recursive_down($fields, function($value, $key, &$parent) {
      if (!is_assoc($parent)) {
        if (is_string($value) && strpos($value, '/') !== false) return;
        list($type, $value) = assoc_element($value);
      }
      else
        $type = $key;

      if ($type == $this->page) return;
      $is_style = ($type === 'styles');
      if (in_array($type, ['types', 'type', 'template', 'wrap', 'styles']) ) {
        $type = $value;
        $value = null;
      }
      if (is_null($value)  && page::is_module($type)) {
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

      if (is_null($expanded)) return false;

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
    return preg_match('/^if |\.\w+$/', $key) || in_array($key, page::$non_mergeable, true);
  }

  static function is_render_item($key)
  {
    return !preg_match('/^if /', $key)
      && !in_array($key, page::$post_items, true)
      && !in_array($key, page::$query_items, true)
      && !page::is_module($key);
  }

  function merge_fields(&$fields, $parent=[], $merged=[])
  {
    if (is_assoc($fields)) {
      if (isset($fields['type']))
        $this->merge_type($fields);
      $this->derive_parent($parent, $field);
      foreach($fields as $key=>&$value) {
        if (!is_array($value) || page::not_mergeable($key)) continue;
        $value = $this->get_merged_field($key, $value);
        if (!in_array($key, $merged, true) || !is_assoc($value)) {
          $merged[] = $key;
          $this->merge_fields($value, $parent, $merged);
        }
      }
      return $fields;
    }
    $default_type = null;
    $default = null;
    foreach($fields as &$value) {
      list($key, $field) = assoc_element($value);
      if (strpos($key, '/') !== false) continue;
      if (page::not_mergeable($key)) continue;
      if ($key == 'type') {
        if (is_string($field) && $field[0] == '$') $field = $parent[substr($field,1)];
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
      if (is_array($field) && (!in_array($key, $merged, true) || !is_assoc($field))) {
        $merged[] = $key;
        $this->merge_fields($field, $parent, $merged);
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
    $this->expand_types($this->fields);
    if (!$this->sub_page)
      $this->verify_access($this->fields);
  }

  function remove_items(&$fields)
  {
    walk_recursive_down($fields, function(&$value, $key, &$parent) {
      if (!$this->is_render_item($key) || $key === 'access')
        unset($parent[$key]);
      if (in_array($key, page::$query_items, true) || page::is_module($key) || preg_match('/^if /', $key))
        $parent['query'] = " ";
    });

  }

  function pre_read($fields)
  {
    $this->merge_fields($fields);
    $actions = $fields['read'];
    if (!isset($actions)) return;
    if ($actions == 'action') {
      $this->context = $fields;
      return $this->action();
    }
    return $this->reply($actions);
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

    $post_prefix = $field['post_prefix'];
    if ($post_prefix) {
      $values = [];
      $offset = strlen($post_prefix);
      foreach($this->request as $key=>$value) {
        if (strpos($key, $post_prefix) === 0)
          $values[substr($key,$offset)] = $value;
      }
    }
    else
      $values = $this->request;
    $values = merge_options($values, $this->answer);
    $options = merge_options($this->context,$values, $this->answer);
    $validators = $this->load_fields('validators');
    $fields = merge_options($this->merge_stack(page::$fields_stack), $this->page_fields, $this->fields);
    $this->validator = new validator($values, $fields, $validators);

    $exclude = array('audit','css','post','script','style', 'styles', 'type','valid','validate','values');

    if (is_string($include))
      $include = explode(',', $include);
    if (is_array($include))
      $this->parse_delta($include);
    else
     $include = true;

    walk_recursive_down($field, function($value, $key, $parent) use (&$exclude, &$include) {
      if (!is_assoc($parent))
        list($code, $value) = assoc_element($value);
      else
        $code = $key;

      if ($include !== true && !in_array($code, $include, true)) return;
      $validator = &$this->validator;
      if ($validator->checked($code)) return false;
      if (in_array($code, $exclude, true)) return false;
      if (!is_null($value) && !is_array($value)) return false;

      $valid = $value['valid'];
      if ($valid == 'ignore') return false;
      if ($valid == "") return;
      $result = $validator->validate($code, $value, $valid);
      if ($result === true) return;

      $error = $validator->error;
      if (is_array($error))
        $this->reply($validator->error);
      else if (!is_null($error))
        page::error($code, $error);

    });
    return $this->validator->valid();
  }

  function data()
  {
    if (!isset($this->context['id'])) $this->context['id'] = last($this->path);
    return $this->reply($this->context);
  }

  function call_method($function, $params)
  {
    log::debug("FUNCTION $function PARAMS:".$params);
    list($class, $method) = explode('::', $function);
    global $config;
    $search_paths = array_reverse($config['search_paths']);
    if (isset($method)) {
      foreach($search_paths as $path) {
        $file_path = "$path/$class.php";
        if (($file_found = file_exists($file_path))) break;
      }
      if (!$file_found) {
        log::error("No such file $class.php");
        return;
      }
      require_once($file_path);
    }

    if (!is_callable($function)) {
      log::warn("Uncallable function $function");
      return;
    }

    if ($params === '')
      return call_user_func($function);

    $params = explode(',', $params);
    $context = merge_options($this->context, $_SESSION['variables'], $this->request, $this->answer);
    replace_fields($context, $this->request);
    replace_fields($params, $this->request);
    replace_fields($params, $context);
    foreach($params as &$val) {
      if ($val == 'context') $val = $context;
      if ($val == 'request') $val = $this->request;
      if ($val == 'root') $val = merge_options($this->fields, $context);
    }
    return call_user_func_array($function, $params);
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

  function audit($action)
  {
    global $db;
    $fields = $this->fields[$this->page];
    $result = null_merge($fields, $this->answer, false);
    $detail = $action['audit'];
    if (!isset($detail)) $detail = $action;
    $field = [];
    $context = merge_options($this->fields, $this->context, $_SESSION['variables'], $this->request, $result);
    if (is_array($detail)) {
      $field = $detail;
      $detail = $field['detail'];
      replace_fields($field, $context, true);
    }
    if (is_string($detail)) {
      $detail = replace_vars($detail, $user);
      if (!$this->audit_delta($detail)) return;
      $detail = replace_vars($detail, $context);
      $detail = page::decode_field($detail);
      $detail = page::decode_sql($detail);
      $detail = replace_vars($detail,$this->request);
    }
    else $detail = "";
    $post = $field['post'];
    if (isset($post))
      $this->reply($post);
    $name = $field['action'];
    if (!isset($name)) $name = $this->name($action);
    $name = addslashes($name);
    $detail = addslashes($detail);
    $collection = $this->get_module('collection');
    $sid = $this->get_module('auth')->get_session_id();
    $user = $collection->values('session', $sid, 'user');
    $partner = $collection->values('session', $sid, 'partner');
    $collection->insert('audit','', ['session'=>'$sid'], ['time'=>"/sysdate()"], $user, $partner, ['action'=>$name], ['detail'=>$detail]);
  }

  function action()
  {
    $invoker = $this->context;
    if (!isset($this->context['id'])) $this->context['id'] = last($this->path);
    if (!isset($this->context['name'])) $this->context['name'] = $this->name($this->context);
    $this->merge_fields($this->fields);
    $pre_validation = $invoker['pre_validation'];
    if ($pre_validation && $this->reply($pre_validation) === false)
      return false;
    $validate = at($invoker, 'validate');
    if ($validate != 'none' && !$this->validate($this->fields, $validate))
      return null;
    $audit_first = $invoker['audit_first'];
    if ($audit_first)
      $this->audit($invoker,[]);
    $result = $this->reply($invoker);
    if (!$this->aborted && !$audit_first && !page::has_errors() && array_key_exists('audit', $invoker))
      $this->audit($invoker);
    return $result;
  }

  function replace_sid(&$str)
  {
    replace_fields($str, ['sid'=>$this->get_module('auth')->get_session_id()]);
  }

  static function replace_sql(&$sql, $options)
  {
    $sql =  replace_vars($sql, $options, function(&$val) {
      $val = addslashes($val);
    });
    return $sql;
  }


  function sql_data($sql)
  {
    $sql = $this->translate_sql($sql);
    $offset = on_null($this->request['offset'], 0);
    $size = on_null($this->request['size'], 0);
    $sort = $this->request['sort'];
    if (isset($sort))
      $sql .= "order by $sort " . $this->request['sort_order'];
    return ['data'=>$this->db->page($sql, $size, $offset, null, ['fetch'=>MYSQLI_NUM]), 'count'=>$this->db->row_count()];
  }

  function sql($sql)
  {
    if (preg_match('/\s*select/i', $sql)) return $this->sql_data($sql);
    $sql = $this->translate_sql($sql);
    return ['data'=>$this->db->exec($sql),'count'=>$this->db->row_count()];
  }

  function translate_sql($sql)
  {
    page::replace_sid($sql);
    page::replace_sql($sql, $this->answer);
    page::replace_sql($sql, $this->context);
    page::replace_sql($sql, $this->request);
    return preg_replace('/\$\w+/', '', $sql);
  }

  function translate_context($str)
  {
    page::replace_sid($str);
    $str = replace_vars($str, $this->answer);
    $str = replace_vars($str, $this->context);
    return replace_vars($str, $this->request);
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

  function get_sql_pairs($args)
  {
    $pairs = [];
    foreach($args as $arg) {
      $pairs[] = $this->get_sql_pair($arg);
    }
    return $pairs;
  }

  function parse_delta(&$args)
  {
    $delta_index = array_search('delta', $args, true);
    if ($delta_index === false) return;

    $delta = trim($this->request['delta']);
    if (!$delta) {
      array_splice($args, $delta_index, 1);
      return;
    }
    $delta = explode(',', $delta);
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
    $context = merge_options($this->context, $options);
    replace_fields($options, $context);
  }

  function set_context()
  {
    $this->merge_fields($this->fields);
    $this->context = $this->follow_path();
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
    if (preg_match('/^(\w+\.\w+)(?:\((.*)\))$/', $condition, $matches)) {
      list($module, $method) = $this->get_module_method($matches[1]);
      if (call_user_func_array([$module,$method],explode(',',$matches[2])))
        $this->reply($args);
    }
    else if (eval("return $condition;"))
      $this->reply($args);
    return true;
  }


  function replace_fields(&$field) {
    replace_fields($field, $this->answer, true);
    replace_fields($field, $this->request, true);
    replace_fields($field, $this->context, true);
  }

  function reply($actions)
  {
    $post = at($actions, 'post');
    if (isset($post)) $actions = $post;
    if (is_null($actions)) return null;
    if (is_assoc($actions))  $actions = array($actions);
    $this->merge_fields($actions, $this->fields);

    log::debug_json("REPLY ACTIONS", $actions);

    $methods = array('abort', 'alert', 'assert', 'audit', 'call', 'clear_session', 'clear_values',
      'close_dialog', 'error', 'foreach', 'let', 'load_lineage', 'logoff',  'keep_values', 'read_config',  'read_server', 'read_session', 'read_values',
       'redirect', 'ref_list', 'show_dialog', 'show_captcha', 'sql', 'sql_exec',
       'sql_rows', 'sql_insert','sql_update', 'sql_values', 'refresh', 'trigger',
       'update', 'upload', 'view_doc', 'write_session');
    foreach($actions as $action) {
      if ($this->aborted) return false;
      if ($this->broken) return $this->answer;
      if (is_array($action)) {
        list($method, $parameter) = assoc_element($action);
      }
      else {
        $method = $action;
        $parameter = array();
      }
      if ($method == 'break') {
        $this->broken = true;
        return $this->answer;
      }
      if (is_null($parameter))
        $parameter = array();
      else if (!is_array($parameter) || is_assoc($parameter))
        $parameter = array($parameter);

      $this->replace_sid($method);
      $this->replace_fields($method);
      $this->replace_sid($parameter);
      if (in_array('$_result', $parameter, true)) {
        $parameter['_result'] = $this->answer;
        unset($parameter['$_result']);
      }

      log::debug_json("REPLY ACTION $method", $parameter);
      if ($this->reply_if($method, $parameter)) continue;

      $context = $this;
      $module_method  = $this->get_module_method($method);
      if ($module_method)
        list($context, $method) = $module_method;
      else if (is_function($method)) {
        $parameter = [$method];
        $method = 'call';
      }
      else if (!in_array($method, $methods))
	      continue;
      if ($method == 'foreach')
        $result = $this->reply_foreach($parameter);
      else {
        $this->replace_fields($parameter);
        $result = call_user_func_array(array($context, $method), $parameter);
      }
      if ($result === false) {
        $this->aborted = true;
        return false;
      };
      if ($this->foreach) return $result;
      if (is_null($result)) continue;
      if (!is_array($result)) $result = array($result);
      $this->answer = merge_options($this->answer, $result);
    }
    return $this->answer;
  }

  function values()
  {
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
      $updates = merge_options($updates, $name);
    else if (is_null($value))
      $updates[$name] = $page->answer[$name];
    else
      $updates[$name] = $value;
  }

  function error($name, $value='')
  {
    if (!$value) {
      $value = $name;
      $name = $this->context['id'];
    }
    log::debug("ERROR $name $value ");
    $result = &$this->result;
    $responses = &$result['_responses'];
    $errors = &$responses['errors'];
    $errors[$name] = $value;
    return false;
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
    if (!$selector) $selector = ".didi-listener";
    $options['sink'] = $selector;
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
    $session = &$_SESSION['variables'];
    foreach($vars as $var) {
      $this->replace_fields($var);
      if ($var == 'request' && !isset($this->request['request']))
        call_user_func_array (array($this, 'write_session'), array_keys($this->request));
      else if (is_array($var)) {
        list($var,$value) = assoc_element($var);
        $this->replace_fields($var);
        $session[$var] = $value;
      }
      else if (isset($this->answer[$var]))
        $session[$var] = $this->answer[$var];
      else if   (isset($this->request[$var]))
        $session[$var] = $this->request[$var];
    }
  }

  function read_settings($settings,$args)
  {
    $vars = page::parse_args($args);
    $values = array();
    foreach($vars as $var) {
      $alias = $var;
      $this->replace_fields($var);
      if (is_array($var))
        list($alias,$var) = assoc_element($var);
      if (isset($settings[$var]))
        $values[$alias] = $settings[$var];
    }
    return $values;
  }

  function read_session()
  {
    $args = func_get_args();
    $session = &$_SESSION['variables'];
    if (sizeof($args) == 0) return $session;
    return $this->read_settings($session, $args);
  }

  function read_session_list()
  {
    $values = call_user_func_array(array($this, 'read_session'), func_get_args());
    return array_values($values);
  }

  function read_config()
  {
    global $config;
    return $this->read_settings($config, func_get_args());
  }

  function read_values($values)
  {
    $this->replace_fields($values);
    return $values;
  }

  function let($values)
  {
    $this->answer = merge_options($this->answer, $this->read_values($values));
  }

  static function abort()
  {
    $args = page::parse_args(func_get_args());
    switch(sizeof($args)) {
      case 1: page::alert($args[0]); break;
      case 2: page::error($args[0], $args[1]); break;
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
    if (sizeof($args) == 0)
      $this->answer = null;
    else foreach($args as $arg) {
      unset($this->answer[$arg]);
      unset($this->request[$arg]);
    }
  }

  function keep_values()
  {
    $args = page::parse_args(func_get_args());
    foreach($this->answer as $key=>$value) {
      if (!in_array($key, $args, true)) unset($this->answer[$key]);
    }
  }


  static function verify_args(&$args, $cmd, $min_count)
  {
    if (sizeof($args) < $min_count)
      throw new Exception("Too few arguments for $cmd, must be at least $min_count");
    return $args;
  }

  static function parse_args($args, $cmd="", $min_count=0)
  {
    if (empty($args) || sizeof($args) > 1 || is_array($args[0])) return page::verify_args($args, $cmd, $min_count);
    $args = array_map(function($arg) {
      return trim($arg);
    }, explode (',', $args[0]));

    return page::verify_args($args, $cmd, $min_count);
  }

  function clear_session()
  {
    $vars = page::parse_args(func_get_args());
    $session = &$_SESSION['variables'];
    if (sizeof($vars) == 0) $vars = array_keys($session);
    foreach($vars as $var) {
      if (isset($session[$var]))
        unset($session[$var]);
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
      $args = merge_options($this->answer, $args);
      $this->update_context($args);
      global $config;
      $args = merge_options($this->get_expanded_field($method), $args);
      $args = merge_options($config[$method], $args);
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
    $this->broken = false;
    foreach($data as $row) {
      $this->answer = array_merge($this->answer, $row);
      foreach($args as $arg) {
        if ($this->reply($arg) === false) return false;
      }
      if ($this->broken) break;
      ++$i;
    }
    $this->broken = false;
    return null;
  }


  function read_server()
  {
    $args = func_get_args();
    $result = $this->read_settings($_SERVER, $args);
    if (!in_array('BASE_URL', $args)) return $result;

    $protocol = 'http';
    if(isset($_SERVER['HTTPS']))
      $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    $result['BASE_URL'] = $protocol . "://" . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
    return $result;
  }

  function merge_context($setting, &$options)
  {
    global $config;
    $options = merge_options($config[$setting], $options);
    $this->merge_fields($options);
    replace_fields($options, $options, true);
    replace_fields($options, $this->answer, true);
  }
}
