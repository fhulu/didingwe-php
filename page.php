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
      if (!preg_match('/^(\w+)(?:([:\$]|=>?)(.*))?/', $option, $matches)) continue;
      list($name, $separator, $value) = array_slice($matches, 1);
      if (!preg_match('/^{(.*)}$/', $value, $matches)) {
        $data[$name] = $value;
        continue;
      }

      $children = array();
      page::read_children($children, $name, $matches[1]);
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
    $row =  $db->read_one("select html template,options from template where code = '$template'"
            . " union"
            . " select html template, options from field_type where code = '$template'",MYSQLI_ASSOC);
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
    $matches = array();
    if (!preg_match_all('/(\w+:{.*}|[^,]+)/', $value, $matches)) {
      log::error("read_children() Unable to match value $value");
      return;
    }
    foreach ($matches[0] as $child) {
      $match = array();
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
      $child = array('field'=>$field);
      page::read_field($child); 
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
 
  static function read_field(&$data, $known = array())
  {
    $page = $data['page'];
    $field = $data['field'];
    unset($data['field']);

    global $db;
    $row =  $db->read_one(
      "select f.code, f.name, f.description 'desc', ft.html, ft.options type_options, f.options field_options"
      . " from field f join field_type ft on f.type = ft.code"
      . " where f.code = '$field'", MYSQLI_ASSOC);
    
    if (sizeof($row) == 0) {
      $data['name'] = $data['desc'] = '';
      return;
    }
    $data = array_merge($data, $row);
    page::expand($data);
    page::expand_variables($data, $known);
    foreach($data as $key=>$value) {
      if (is_null($value)) unset ($data[$key]);
    }
  }

  static function expand_variables(&$data, $known = array())
  {
    if (isset($data['has_data'])) return;
    $matches = array();
    if (!preg_match_all('/\$([\w]+)/', $data['html'], $matches)) return;
    
    $known = array_merge($known, $data);
    $vars = array_diff($matches[1], array('code'));
    $page = $data['code'];
    foreach($vars as $var) {
      if (array_key_exists($var, $known)) continue;
      $values = array('field'=>$var,'page'=>$page);
      page::read_field($values, $known);
      $data[$var] = $values;
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
    $data = array();
    $data['field'] = $request['field'];
    $data['page'] = $request['parent'];
    $data['program'] = config::$program_name;
    page::read_field($data);
    echo json_encode($data);

  }
  
  static function validate($request)
  {
    global $db;
    $code = $request['code'];
    $rows = $db->read("select distinct ff.optional"
            . ", ifnull(ff.my_field_code, f.code) code"
            . ", ifnull(ff.my_validation, f.validation) validation"
            . ", ifnull(ff.my_name, f.name) name"
            . ", ifnull(ff.my_size, f.size) size"
            . ", ifnull(ff.my_min_length, f.min_length) min_length"
            . ", ff.optional"
            . ", ifnull(ff.my_reference, f.reference) reference"
            . " from form_field ff left join field f"
            . " on f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code"
            . " where ff.program_id in ('\$pid', '_generic')"
            . " and ff.form_code = '$code'"
            . " order by ff.program_id desc, ff.position asc", MYSQLI_ASSOC);
    
    
    $v = new validator($request);
    
    foreach ($rows as $row) {
      $code = $row['code'];
      $name = $row['name'];
      if ($row['optional'] != 0 && !$v->check($code, $name)->provided()) continue;
      
      $min_length = $row['min_length'];
      if ($min_length != 0) 
        $v->check($code, $name)->at_least($min_length);
      
      $validator = $row['validation'];
      if ($validator != '')
        $v->check($code, $name)->is($validator);
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
  static function data($request)
  {
    $field = $request['field'];
    $sql = "select f.code, ft.options type_options, f.options field_options"
              ." from field f join field_type ft on f.type = ft.code"
              ." where f.code = '$field'";

    global $db;
    $row =  $db->read_one($sql, MYSQLI_ASSOC);

    page::expand_options($row, 'type_options');
    page::expand_options($row, 'field_options');
    page::read_child_template($row);
    page::expand_values($row, array('template'));
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
  
}
