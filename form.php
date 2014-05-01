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
            . ", class, fields_class, label_position, data_url"
            . " from mukonin_form.form "
            . " where program_id in ('\$pid', '_generic') and code = '$code'"
            . " order by program_id desc", MYSQLI_ASSOC);
    
    // read fields
    $fields = form::get_fields("select distinct f.code"
            . ", ifnull(ff.my_name, f.name) name, f.length, ff.parent_field parent, ff.visible, ff.optional"
            . ", replace(ifnull(ff.my_description, f.description), '\$program', '$program_name') 'desc'"
            . ", ifnull(ff.my_input,f.input) input"
            . ", ifnull(ff.my_reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_field ff"
            . " on f.program_id = ff.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = ff.field_code and ff.form_code = '$code'"
            . " order by ff.program_id desc, ff.position asc");
    
    $actions = form::get_fields("select distinct f.code"
            . ",  ifnull(fa.my_name,f.name) name, fa.visible"
            . ", replace(ifnull(fa.my_description, f.description), '\$program', '$program_name') 'desc'"
            . ", ifnull(fa.my_input,f.input) input"
            . ", ifnull(fa.my_reference, f.reference) reference"
            . " from mukonin_form.field f join mukonin_form.form_action fa"
            . " on f.program_id = fa.program_id and f.program_id in ('\$pid', '_generic')"
            . " and f.code = fa.field_code and fa.form_code = '$code'"
            . " order by fa.program_id desc, fa.position asc");
    echo json_encode(array(
        'attributes'=>$attributes,
        'fields'=>$fields,
        'actions'=>$actions));
  }
  
  static function test()
  {
    echo json_encode(array("za_city"=>"7100"));
  }
  //put your code here
}
