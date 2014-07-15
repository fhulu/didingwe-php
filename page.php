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
  
  static function expand(&$row, $field)
  {
    $extras = explode('~',$row[$field]);
    foreach($extras as $attr) {
      $attr = explode('=>',$attr);
      $row[$attr[0]] = $attr[1];
    }
    unset($row[$field]);
    $html = &$row['html'];
    foreach($row as $key=>&$value) {
      $value = str_replace('$program', config::$program_name, $value);
      $html = str_replace("\$$key", $value, $html);
    }
  }
  
  static function read_children($code)
  {
    global $db;
    $rows =  $db->read("select f.code, f.type, cf.enabled, cf.visible, ft.html,
                cf.optional,f.name, f.initial_value value, f.description 'desc',
                f.custom, cf.custom overridden_custom
                from container_field cf join field f on cf.field_code = f.code 
                join field_type ft on f.type = ft.code
                where cf.parent_field_code = '$code'
                order by cf.position asc", MYSQLI_ASSOC);
    $children = array();
    foreach($rows as &$row) {
      $code = $row['code'];
      page::expand($row, 'overridden_custom');
      page::expand($row, 'custom');
      unset($row['code']);
      if (in_array($row['type'], array('page','container','form'))) 
        $row['children'] = page::read_children($code);
      $children[$code] = $row;
    }
    return $children;
  }
  
  
  static function read($request)
  {
    global $db;
    $code = $request['code'];

    global $db;
    $row =  $db->read_one("select f.code,type, f.name, f.description 'desc', initial_value value, html, custom"
            . " from field f join field_type ft on f.type = ft.code"
            . " where f.code = '$code'", MYSQLI_ASSOC);
    
    page::expand($row, 'custom');
    if (in_array($row['type'], array('page','container','form','input_page'))) 
      $row['children'] = page::read_children($code);
    
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
 
  
  static function reference($request)
  {
    $request = db::addslashes($request);
    $field = $request['field'];
    $form = $request['form'];
    global $db;
    $reference = $db->read_one_value("select ifnull(my_reference,f.reference) "
      . " from form_field ff left join field f"
      . " on ff.field_code = f.code"
      . " where ff.form_code = '$form' and ff.field_code = '$field'");
    
    
    $matches = array();
    preg_match('/(.+): (.+)/', $reference, $matches);
    $type = $matches[1];
    $list = $matches[2];
    
    if ($type == 'inline') {
      $values = explode('|', $list);
      $result = array();
      foreach($values as $pair) {
        list($code) = explode(',', $pair);
        $value['item_code'] = $code;
        $value['item_name'] = substr($pair, strlen($code)+1);
        $result[] = $value;
      }
      echo json_encode($result);
      return;
    }
    if ($type == 'sql') {
      $rows = $db->read($list);
      echo json_encode($rows);
      return;
    }
    
    $request['list'] = $reference;
    return ref::items($request);
  }
  
}
