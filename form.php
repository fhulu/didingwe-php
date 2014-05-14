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

class form {

  static function get_fields($sql, $key='code')
  {
    global $db;
    $rows = $db->read($sql,MYSQLI_ASSOC);
   
    $fields = array();
    foreach($rows as $row) {
      $code = $row[$key];
      unset($row[$key]);
      $fields[$code] = $row;
    }
    return $fields;
  }
  
  static function read_attributes($code)
  {
    global $db;
    $program_name = config::$program_name;
    return $db->read_one("select '$program_name' program, function_code, title, method, class"
            . ", replace(description, '\$program', '$program_name') 'desc'"
            . ", class, fields_class, label_suffix, label_position, data_url"
            . " from mukonin_form.form "
            . " where program_id in ('\$pid', '_generic') and code = '$code'"
            . " order by program_id desc", MYSQLI_ASSOC);
    
  }
  static function load($request) 
  {
    global $db;
    $code = $request['code'];
    $program_name = config::$program_name;

    $forms = form::get_fields("select f.code, f.title, wf.nav_position, wf.show_back, wf.show_next, wf.next_action"
            . " from mukonin_form.wizard_form wf join mukonin_form.form f "
            . " on f.program_id in ('\$pid', '_generic')"
            . " and wf.program_id in ('\$pid', '_generic')"
            . " and f.code = wf.form_code"
            . " and wf.wizard_code = '$code'"
            . " order by wf.program_id desc, wf.position asc");
    if (sizeof($forms) != 0) {
      echo json_encode(array(
          "program"=>config::$program_name,
          "size"=>sizeof($forms), 
          "forms"=>$forms));
      return;
    }
    
    $attributes = form::read_attributes($code);
    
    $fields = form::get_fields("select distinct ff.enabled"
            . ", ifnull(ff.my_field_code, f.code) code"
            . ", ifnull(ff.my_name, f.name) name"
            . ", ifnull(ff.my_initial_value, f.initial_value) initial"
            . ", ifnull(ff.my_size, f.size) size"
            . ", ff.parent_field parent, ff.visible, ff.optional"
            . ", replace(ifnull(ff.my_description, f.description), '\$program', '$program_name') 'desc'"
            . ", ifnull(ff.my_input,f.input) input"
            . ", ifnull(ff.my_reference, f.reference) reference"
            . " from mukonin_form.form_field ff left join mukonin_form.field f"
            . " on f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code"
            . " where ff.program_id in ('\$pid', '_generic')"
            . " and ff.form_code = '$code'"
            . " order by ff.program_id desc, ff.position asc");
    
    $actions = form::get_fields("select distinct fa.field_code code, fa.method, fa.validator"
            . ", ifnull(fa.my_name,f.name) name, fa.visible"
            . ", replace(ifnull(fa.my_description, f.description), '\$program', '$program_name') 'desc'"
            . ", ifnull(fa.my_input,f.input) input"
            . ", ifnull(fa.my_reference, f.reference) reference"
            . " from mukonin_form.form_action fa left join mukonin_form.field f"
            . " on f.program_id in ('\$pid', '_generic')"
            . " and f.code = fa.field_code"
            . " where fa.program_id in ('\$pid', '_generic')"
            . " and fa.form_code = '$code'"
            . " order by fa.program_id desc, fa.position asc");
    echo json_encode(array(
        'attributes'=>$attributes,
        'fields'=>$fields,
        'actions'=>$actions));
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
            . " from mukonin_form.form_field ff left join mukonin_form.field f"
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
 
  
  static function reference()
  {
    $request = db::addslashes($request);
    $field = $request['field'];
    $form = $request['form'];
    global $db;
    $reference = $db->read_one_value("select ifnull(my_reference,f.reference) "
      . " from mukonin_form.form_field ff left join mukonin_form.field f"
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
    }
    
    $request['list'] = $reference;
    return ref::items($request);
  }
  
}
