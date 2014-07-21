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
  
  static function expand_options(&$row, $field='options')
  {
    $options = $row[$field];
    unset($row[$field]);
    if (is_null($options)) return;
    
    $options = explode('~',$options);
    foreach($options as $option) {
      list($name,$value) = explode('=>',$option);
      $row[$name] = $value;
    }
  }
  
  
  static function read_child_template(&$data)
  {
    $template = $data['template'];
    if (is_null($template)) return;
    
    global $db;
    $row =  $db->read_one("select html template,options from template where code = '$template'"
            . " union"
            . " select html template, options from field_type where code = '$template'",MYSQLI_ASSOC);
    if (!isset($row['template'])) {
      unset($data['template']);
      return;
    }
    page::expand_options($row);
    $data = array_merge ($data, $row);
  }
  
  static function set_data_flag(&$data)
  {
    if (!isset($data['data'])) return;
    unset($data['data']);
    $data['has_data'] = "";
  }
  
  static function read_children(&$data, $field='children')
  {
    page::read_child_template($data);
    
    $children = $data[$field];
    if (is_null($children)) return;
    
    $code = $data['code'];
    $children = implode('","', explode(',',$children));

    global $db;
    $rows =  $db->read("select f.code, f.type, f.name, f.description 'desc', ft.html"
                  .", ft.options type_options, f.options field_options, cf.options page_options"
                ." from field f join field_type ft on f.type = ft.code"
                  ." left join container_field_options cf on cf.field_code = f.code"
                   ." and cf.parent_field_code = '$code'"
                ." where f.code in (\"$children\")"
                  . "order by field(f.code,\"$children\")", MYSQLI_ASSOC);
    $children = array();
    foreach($rows as $row) {
      $code = $row['code'];
      page::expand_options($row, 'type_options');
      page::expand_options($row, 'field_options');
      page::expand_options($row, 'page_options');
      page::read_children($row);
      page::set_data_flag($row);
      page::expand_variables($row);
      unset($row['code']);
      $children[$code] = $row;
      }
    $data[$field] = $children;
    unset($row['code']);
  }
 
  static function read_field($field, &$row)
  {
    global $db;
    $data =  $db->read_one("select f.code,type, f.name, f.description 'desc'"
            . ", html, ft.options type_options, f.options field_options"
            . " from field f join field_type ft on f.type = ft.code"
            . " where f.code = '$field'", MYSQLI_ASSOC);
    
    $row = array_merge($row, $data);
    page::expand_options($row, 'type_options');
    page::expand_options($row, 'field_options');
    page::set_data_flag($row);
    page::expand_variables($row);
    page::read_children($row);
  }
  
  static function expand_variables(&$row)
  {
    // check if all variables in html can be expanded/sustituted
    $matches = array();
    if (!preg_match_all('/\$([\w]+)/', $row['html'], $matches)) return;
    
    $vars = array_diff($matches[1], array('code', 'children'));
    foreach($vars as $var) {
      if (array_key_exists($var, $row)) continue;
      log::debug("EXPANDING $var");
      $values = array_diff($row, array('code', 'children', 'template', 'html'));
      page::read_field($var, $values);
      $row[$var] = $values;
    }
  }
  
  static function read($request)
  {
    global $db;
    $field = $request['code'];
    $row['program'] = config::$program_name;
    page::read_field($field, $row);
    echo json_encode($row);

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
 
  
  static function data($request)
  {
    $field = $request['field'];
    $page = $request['page'];
    $sql = "select  ft.options type_options, f.options field_options, cf.options page_options"
              ." from field f join field_type ft on f.type = ft.code"
                ." left join container_field_options cf on cf.field_code = f.code"
                 ." and cf.parent_field_code = '$page'"
              ." where f.code = '$field'";
    
    global $db;
    $row =  $db->read_one($sql, MYSQLI_ASSOC);
    
    page::expand_options($row, 'type_options');
    page::expand_options($row, 'field_options');
    page::expand_options($row, 'page_options');
    page::read_child_template($row);
    
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
      log::debug("FUNCTION ".$matches[1]." PARAMS:".$matches[2]);
      $rows = call_user_func($matches[1], $request, $matches[2]);
    }
    
    echo json_encode(array("template"=>$template, "children"=>$rows));
  }
  
}
