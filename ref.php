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
    $list = $request['list']; 
    list($class,$method) = explode('::', $list);
    if (!is_null($method)) return call_user_func($list, $request);
    
    if (strpos($list, '|') !== false) return ref::inline($list);
   
    $matches = array();
    if (preg_match('/([^:]+):(.+)/', $list, $matches)) {
      $type = strtolower($matches[1]);
      switch($type) {
        case 'sql':  return ref::encode_sql($matches[2]);
        default: 
          log::warn("Unknown type $type");
          return;
      }
    }
    
    list($code,$name,$table)= explode(',', $list);
    if (isset($name)) {
      $sql = "select $code item_code, $name item_name from $table order by $name";
    }
    else {
      $list = addslashes($list);   
      $sql = "select item_code, item_name, item_desc from mukonin_audit.ref_list "
              . "where list_name = '$list'"
              . " and program_id in (0,\$pid) "
              . " order by item_name";
    }
    return ref::encode_sql($request, $sql);
  }
  
  static function inline($list)
  {
    $values = explode('|', $list);
    $result = array();
    foreach($values as $value) {
      list($code, $value) = explode(',', $value);
      $row['item_code'] = $code;
      $row['item_name'] = $value;
      $result[] = $row;
    }
    echo json_encode($result);
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
  
  static function encode_json($request, $sql)
  {
    global $db;
    $rows = $db->read($sql, MYSQL_ASSOC);
    echo json_encode ($rows);
  }
  
  static function encode_select($request, $sql)
  {
    $list = ucwords(str_replace('_', ' ', $request['list']));
    $selected = $request['selected'];
    if ($selected == '')
      echo "<option selected>--Select $list--</option>\n";
    else 
      echo "<option selected>--Select $list--</option>\n";
    
    global $db;
    $rows = $db->read($sql, MYSQL_NUM);
    foreach($rows as $row) {
      $key = $row[0];
      $item =  $row[1];
      if ($selected == $key) $selected = " selected";
      echo "<option value='$key'$selected>$item</option>\n";
    }    
  }

  static function encode_sql($request, $sql)
  {
    $sql .= ref::get_sql_extension($request);
    list($encode) = explode('/', $_GET['a']);
    $callback = "ref::encode_$encode";
    if (!is_callable($callback)) {
      log::warn("Encoding type not specified");
      return;
    }
    
    return call_user_func($callback, $request, $sql);
  }
  
  static function query($request, $search_code='code1', $result_list='list2', $result_code='code2')
  {
    $name = addslashes($request['name']);
    $list = addslashes($request['list']);
    $code = addslashes($request['code']);
    
    $sql = "select a.$result_code item_code, l.item_name, l.item_desc "
            . "from mukonin_audit.ref_assoc a "
            . "join mukonin_audit.ref_list l "
            . " on a.$result_list = l.list_name"
            . " and l.item_code = a.$result_code "
            . " and a.name = '$name' and a.$result_list = '$list'"
            . " and a.$search_code = '$code'";
    
    return ref::encode_sql($request, $sql);
  }

  static function reverse_query($request)
  {
    return ref::query($request, 'code2', 'list1', 'code1');
  }
  
  static function add($request)
  {
    $v = new validator($request);
    $v->check('list')->is('at_least(2)');
    $v->check('name')->is('at_least(2)');
    $v->check('code')->is('at_least(2)');
    if (!$v->valid()) return false;

    $list = addslashes($request['list']);
    $code = addslashes($request['code']);
    $name = addslashes($request['name']);
    $desc = addslashes($request['desc']);

    $sql = "insert into mukonin_audit.ref_list(program_id, list_name, item_code, item_name, item_desc)"
            . "values (\$pid, '$list', '$code', '$name', '$desc')";
    
    global $db;
    $db->exec($sql);
  }

  static function delete($request)
  {
    $list = addslashes($request['list']);
    $code = addslashes($request['code']);

    $sql = "delete from mukonin_audit.ref_list"
            . "where program_id = \$pid and  list_name = '$list', item_code='$code'";
   
    global $db;
    $db->exec($sql);
  }
  
  static function assoc($request)
  {
    $program_id = config::$program_id;
    $v = new validator($request);
    $v->check('name')->is('at_least(2)');
    $v->check('list1')->is('at_least(2)');
    $v->check('code1')->is('at_least(2)');
    $v->check('list2')->is('at_least(2)');
    $v->check('code2')->is('at_least(2)');
    if (!$v->valid()) return false;
    
    $name = addslashes($request['name']);
    $desc = addslashes($request['list1_desc']);
    $code = addslashes($request['code1']);

    global $db;
    $db->exec("insert ignore mukonin_audit.ref_list(program_id, list_name, item_code, item_name, item_desc)"
            . "values(\$pid, '$name', '$list', '$code', '$desc')");
    
    $desc = addslashes($request['list2_desc']);
    $code = addslashes($request['code2']);
    $db->exec("insert ignore mukonin_audit.ref_list(program_id, list_name, item_code, item_name, item_desc)"
            . "values(\$pid, '$name', '$list', '$code', '$desc')");
  }
}
