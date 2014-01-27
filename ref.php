<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ref
 *
 * @author fhulu
 */
class ref 
{
  
  static function items($request)
  {
    $list = addslashes($request['list']);    
    $sql = "select item_code, item_name, item_desc from mukonin_audit.ref_list "
            . "where list_name = '$list'";
    return ref::encode_sql($request, $sql);
  }
  
  static function get_sql_extension($request)
  {
    $filter = addslashes($request['filter']);
    $exclude = addslashes($request['exclude']);
    $sql = '';
    if ($filter != '') {
//      $filter = array_map('mysql_real_escape_string', explode("','", $filter));
      $filter = implode("','", explode(',', $filter));
      $sql .= " and l.item_code in ('$filter')";
    }
    if ($exclude != '') {
      $exclude = implode("','", explode(',', $exclude));
      $sql .= " and l.item_code not in ('$exclude')";
    }
    return $sql;
  }
  
  static function encode_sql($request, $sql)
  {
    global $db;
    
    $sql .= ref::get_sql_extension($request);
    list($encode) = explode('/', $_GET['a']);
    if ($encode == 'json') {
      $rows = $db->read($sql, MYSQL_ASSOC);
      echo json_encode ($rows);
      return;
    }
    if ($encode == 'select') {
      $list = ucwords(str_replace('_', ' ', $request['list']));
      $selected = $request['selected'];
      if ($selected == '')
        echo "<option selected>--Select $list--</option>\n";
      else 
        echo "<option selected>--Select $list--</option>\n";
      $rows = $db->read($sql, MYSQL_NUM);
      foreach($rows as $row) {
        $key = $row[0];
        $item =  $row[1];
        if ($selected == $key) $selected = " selected";
        echo "<option value='$key'$selected>$item</option>\n";
      }
      return;
    }
    log::warn("Encoding type not specified");
  }
  
  static function assoc($request)
  {
    $name = addslashes($request['name']);
    $list = addslashes($request['list']);
    $code = addslashes($request['code']);
    
    $sql = "select a.code2, l.item_name, l.item_desc "
            . "from mukonin_audit.ref_assoc a "
            . "join mukonin_audit.ref_list l on l.item_code = a.code2 "
            . " and a.name = '$name' and a.list2 = '$list'"
            . " and a.code1 = '$code'";
    
    return ref::encode_sql($request, $sql);
  }

  static function reverse_assoc($request)
  {
    $name = addslashes($request['name']);
    $list = addslashes($request['list']);
    $code = addslashes($request['code']);
    
    $sql = "select a.code1, l.item_name, l.item_desc "
            . "from mukonin_audit.ref_assoc a "
            . "join mukonin_audit.ref_list l on l.item_code = a.code1 "
            . " and a.name = '$name' and a.list1 = '$list'"
            . " and a.code2 = '$code'";
    
    return ref::encode_sql($request, $sql);
  }
}
