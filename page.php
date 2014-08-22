<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of form
 *
 * @author fhulu
 */

require_once 'db.php';
require_once 'validator.php';
require_once 'ref.php';

class page {
    
  static function expand_options(&$data, $field='options')
  {
    $options = $data[$field];
    unset($data[$field]);
    if (is_null($options)) return;
    
    $options = explode('~',$options);
    foreach($options as $option) {
      $matches = array();
      if (!preg_match('/^(\w+)(?:([:\$]|=>?)(.*))?/s', $option, $matches)) continue;
      list($name, $separator, $value) = array_slice($matches, 1);
      if (!preg_match('/^({.*}|\[.*\])$/', $value, $matches)) {
        $data[$name] = is_null($value)? page::read_field($name):$value;
        continue;
      }

      $my_options = page::read_field($name);
      if (sizeof($my_options)> 0)
        $data[$name] = is_array($data[$name])? array_merge($data[$name], $my_options): $my_options;

      $grouper = $value[0];
      $value = substr($value, 1, strlen($value)-2);
      if ($grouper == '[') {
         $data[$name] = explode(',', $value);
         continue;
      }

      $children = array();
      page::read_children($children, $name, $value);
      if (is_array($data[$name]))
        $data[$name] = array_merge($data[$name], $children);
      else
        $data[$name] = $children;
    }
  }
  
  
  static function read_child_template(&$data)
  {
    $template = $data['template'];
    if (is_null($template)) return;

    unset($data['template']);
    global $db;
    $row =  $db->read_one("select html template, options from field_type where code = '$template'",MYSQLI_ASSOC);
    $template = $row['template'];
    if (!isset($template)) return;

    page::expand_options($row);
    $data = array_merge ($data, $row);
  }
  
  static function set_data_flag(&$data)
  {
    if (!isset($data['data'])) return;
    unset($data['data']);
    $data['has_data'] = "";
  }
  
  
  static function read_children(&$data, $parent, $value)
  {
    //todo: extend children
    $matches = array();
    if (!preg_match_all('/(\w+:{.*}|[^,]+)/', $value, $matches)) {
      log::error("read_children() Unable to match value $value");
      return;
    }
    foreach ($matches[0] as $child) {
      $match = array();
      if ($child[0]=='$') {
        $child = $_REQUEST[substr($child,1)];
      }
      if (preg_match('/^(\w+):([^{].*)$/', $child, $match)) {
        $data[$match[1]] = $match[2];
        page::read_child_template($data);
        continue;
      }
      if (!preg_match('/^(\w+)(?::{(.*)})?$/', $child, $match)) {
        log::error("read_children() unable to match child $child");
        return;
      }
      $field = $match[1];
      $child = page::read_field($field); 
      $grand_children = $match[2];
      if ($grand_children != '') {
        page::read_children ($child, $field, $grand_children);
      }
      $data[$field] =$child;
    }
    return;
  }
 
  static function expand(&$data)
  {
    page::expand_options($data, 'type_options');
    page::expand_options($data, 'field_options');
    page::read_child_template($data);
    page::set_data_flag($data);
  }
 
  static function read_field_options($field)
  {
    global $db;
    $row =  $db->read_one(
      "select f.code, f.name, f.description 'desc', ft.html, ft.options type_options, f.options field_options"
      . " from field f left join field_type ft on f.type = ft.code"
      . " where f.code = '$field'", MYSQLI_ASSOC);
    
    if (is_null($row)) return null;
    
    foreach($row as $key=>$value) {
      if (is_null($value)) unset ($row[$key]);
    }
    page::expand_options($row, 'type_options');
    page::expand_options($row, 'field_options');
    return $row;
}
  
  static function read_field($field)
  {
    $data = page::read_field_options($field);
    
    if (sizeof($data) == 0) return $data;

    foreach($data as $key=>$value) {
      if (is_null($value)) unset ($data[$key]);
    }
    page::read_child_template($data);
    page::set_data_flag($data);
    page::expand_variables($data);
    return $data;
  }

  static function expand_variables(&$data)
  {
    if (isset($data['has_data'])) return;
    $matches = array();
    if (!preg_match_all('/\$(\w+)/', $data['html'], $matches)) return;
    
    $vars = array_diff($matches[1], array('code'));
    $page = $data['code'];
    foreach($vars as $var) {
      $value = page::read_field($var);
      if (is_null($value)) continue;
      $data[$var] = is_array($data[$var])? array_merge($data[$var], $value): $value;
    }
  }

  static function expand_values(&$row, $exclusions=array())
  {

    foreach($row as $key1=>&$value1) {
      if (in_array($key1, $exclusions)) continue;
      foreach ($row as $key2=>$value2) {
        $value1 = preg_replace('/\$'.$key2.'([^\w]*)/', "$value2\$1", $value1);
      }
    }
  }
  
  static function read($request)
  {
    $data = page::read_field($request['page']);
    $data['program'] = config::$program_name;
    echo json_encode($data);

  }
  
  static function validate($request, $page_options)
  {
    $fields = array_keys($request);
    $fields = array_diff($fields, array('a','_page','_field'));
    if (sizeof($fields) == 0) return false;
    
    $fields = implode("','", $fields);
    global $db;
    $rows =  $db->read(
      "select f.code, f.name, ft.options type_options, f.options field_options"
      . " from field f left join field_type ft on f.type = ft.code"
      . " where f.code in ('$fields')", MYSQLI_ASSOC);
    
    
    if (sizeof($rows) == 0) return false;
    
    $v = new validator($request);
    
    foreach ($rows as $row) {
      page::expand_options($row, 'type_options');
      page::expand_options($row, 'field_options');
      $code = $row['code'];
      $name = $row['name'];
      $field_validator = $row['valid'];
      $page_validator = $page_optins[$code]['valid'];
      $validators = $page_validator==null?$field_validator:$page_validator;
      if (is_null($validators)) continue;
      $validators = explode(',',$validators);
      foreach($validators as $validator) {
        if ($validator == 'optional' && !$v->check($code, $name)->provided()) continue;
        $v->check($code, $name)->is($validator);
      }
    }
    
    return $v->valid();
  }
 
  static function recode(&$rows, $field='code')
  {
    $result = array();
    foreach($rows as $row)
    {
      $code = $row[$field];
      unset($row[$field]);
      $result[$code] = $row; 
    }
    $rows = $result;
  }
  
  static function expand_templates(&$data) 
  {
    foreach($data as $key=>&$value) {
      if (!is_array($value)) continue;
      page::read_child_template($value);
      page::expand_templates($value);
    }
  }
  
  static function read_page_field_options($request)
  {
    $page = $request['_page'];
    $page_options = page::read_field_options($page);
    $field = $request['_field'];
    $options = page::read_field_options($field);
    if (isset($page_options[$field]))
      $options = array_merge($options, $page_options[$field]);
    return $options;
  }
  
  static function data($request)
  {
    $row = page::read_page_field_options($request);
    page::read_child_template($row);
    page::expand_values($row, array('template','html'));
    $data = $row['data'];
    $template = $row['template'];
    
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/', $data, $matches);
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
      $function = $matches[1];
      log::debug("FUNCTION $function PARAMS:".$matches[2]);
      list($class, $method) = explode('::', $function);
      if (isset($method)) require_once("$class.php");
      $rows = call_user_func($function, $request, $matches[2]);
    }

    if (isset($template)) $rows['template'] = $template;  
    page::expand_templates($rows);  
    echo json_encode($rows);
  }
  
  static function action($request)
  {
    $options = page::read_page_field_options($request);
    page::expand_values($options);
    if (array_key_exists('validate', $options) && !page::validate($request, $options))
      return;
    
    $action = $options['action'];
    if (!isset($action)) return;
    
    log::debug("action=$action");
    
    $rows = array();
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/', $action, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $action, $matches) ) {
      $function = $matches[1];
      log::debug("FUNCTION $function PARAMS:".$matches[2]);
      list($class, $method) = explode('::', $function);
      if (isset($method)) require_once("$class.php");
      call_user_func($function, $request, $matches[2]);
    }
  }

  static function load($request)   
  {  
    $options = page::read_field_options($request['page']);
    page::expand_values($options);

    $key = $request['key'];
    if (!isset($key))  {
      log::warn("No key provided");
      return;
    }
    log::debug("key=$key, $options=".json_encode($options));
    $rows = array();
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/', $options['load'], $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $sql = str_replace('$key', addslashes($key), $list);
      $rows = $db->read_one($sql, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $action, $matches) ) {
      $function = $matches[1];
      log::debug("FUNCTION $function PARAMS:".$matches[2]);
      list($class, $method) = explode('::', $function);
      if (isset($method)) require_once("$class.php");
      call_user_func($function, $request, $matches[2]);
    }
  }
  
  static function table($request)
  {
    $options = page::read_field_options($request['field']);
    
    page::expand_values($options);
    
    $data = $options['data'];
    if (!isset($data)) return;
    
    $rows = array();
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/', $data, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_NUM);
      $fields = array();
      foreach($db->field_names as $name) {
        $fields[$name] = page::read_field_options($name);
      }
      echo json_encode(array('fields'=>$fields, 'rows'=>$rows));
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $data, $matches) ) {
      $function = $matches[1];
      log::debug("FUNCTION $function PARAMS:".$matches[2]);
      list($class, $method) = explode('::', $function);
      if (isset($method)) require_once("$class.php");
      call_user_func($function, $request, $matches[2]);
    }
  }
  
  static function test($req)
  {
    log::debug(json_encode($req));
    echo json_encode($req);
  }
}
