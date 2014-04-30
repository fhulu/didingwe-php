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
  static function load($request) 
  {
    global $db;
    $code = $request['code'];
    $program_name = config::$program_name;
    $attributes = $db->read_one("select '$program_name' program, function_code, title, method"
            . ", replace(description, '\$program', '$program_name') 'desc'"
            . ", class, fields_class, label_position"
            . " from mukonin_form.form "
            . " where program_id in ('\$pid', '_generic') and code = '$code'"
            . " order by program_id desc", MYSQLI_ASSOC);
    
    $rows = $db->read("select f.code, f.name, f.length, ff.filter_field filter, ff.visible, ff.optional"
            . ", replace(f.description, '\$program', '$program_name') 'desc'"
            . ", ifnull(ff.input,f.input) input"
            . ", ifnull(ff.reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_field ff"
            . " on f.program_id = ff.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code and ff.form_code = '$code'",MYSQLI_ASSOC);
   
    $fields = array();
    foreach($rows as $row) {
      $code = $row['code'];
      unset($row['code']);
      $fields[$code] = $row;
    }
    echo json_encode(array('attributes'=>$attributes, 'fields'=>$fields));
  }
  //put your code here
}
