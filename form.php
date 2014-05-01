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

  static function get_fields($sql)
  {
    global $db;
    $rows = $db->read($sql,MYSQLI_ASSOC);
   
    $fields = array();
    foreach($rows as $row) {
      $code = $row['code'];
      unset($row['code']);
      $fields[$code] = $row;
    }
    return $fields;
  }
  static function load($request) 
  {
    global $db;
    $code = $request['code'];
    $program_name = config::$program_name;
    
    // read form attributes
    $attributes = $db->read_one("select '$program_name' program, function_code, title, method"
            . ", replace(description, '\$program', '$program_name') 'desc'"
            . ", class, fields_class, label_position"
            . " from mukonin_form.form "
            . " where program_id in ('\$pid', '_generic') and code = '$code'"
            . " order by program_id desc", MYSQLI_ASSOC);
    
    // read fields
    $rows = $db->read("select f.code, f.name, f.length, ff.filter_field filter, ff.visible, ff.optional"
            . ", replace(f.description, '\$program', '$program_name') 'desc'"
            . ", ifnull(ff.input,f.input) input"
            . ", ifnull(ff.reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_field ff"
            . " on f.program_id = ff.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code and ff.form_code = '$code'",MYSQLI_ASSOC);
   
    $fields = form::get_fields("select distinct f.code, f.name, f.length, ff.filter_field filter, ff.visible, ff.optional"
            . ", replace(f.description, '\$program', '$program_name') 'desc'"
            . ", ifnull(ff.input,f.input) input"
            . ", ifnull(ff.reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_field ff"
            . " on f.program_id = ff.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code and ff.form_code = '$code'"
            . " order by ff.program_id desc, ff.position asc");
    
    $actions = form::get_fields("select distinct f.code, f.name, fa.visible"
            . ", replace(f.description, '\$program', '$program_name') 'desc'"
            . ", ifnull(fa.input,f.input) input"
            . ", ifnull(fa.reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_action fa"
            . " on f.program_id = fa.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = fa.field_code and fa.form_code = '$code'"
            . " order by fa.program_id desc, fa.position asc");
    echo json_encode(array(
        'attributes'=>$attributes,
        'fields'=>$fields,
        'actions'=>$actions));
  }
  //put your code here
}
