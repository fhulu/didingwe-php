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
    
    $fields = form::get_fields("select distinct ff.field_code code, ff.enabled"
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
    
    $actions = form::get_fields("select distinct fa.field_code code, fa.method"
            . ",  ifnull(fa.my_name,f.name) name, fa.visible"
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
  
  static function test()
  {
    echo json_encode(
      array("za_city"=>"7100",
        "otp"=>12345));
  }
  //put your code here
}
