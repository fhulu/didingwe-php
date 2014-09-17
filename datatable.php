<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../common/page.php';

class datatable 
{  
  static function read_action(&$actions, $options, $action)
  {
    if (array_key_exists($action, $actions)) return;

    if (array_key_exists($action, $options)) 
      $actions[$action] = $options[$action];
    else
      $actions[$action] = page::read_field_options($action);  
  }
  static function read_actions($options, $rows)
  {
    $actions = array();
    foreach($rows as $row) {
      foreach (explode(',', last($row)) as $action) {
        datatable::read_action($actions, $options, $action);
        if ($action === 'slide')
          datatable::read_action($actions, $options, 'slideoff');
        else if ($action === 'expand')
          datatable::read_action($actions, $options, 'collapse');
      }
    }
    return $actions;
  }
  
  
  static function read_db($sql, $options) 
  {
    global $db;
    $page_size = at($options,'page_size');
    if (is_null($page_size)) $page_size = 0;
    return $db->read($sql, MYSQLI_NUM, $page_size);    
  }
  
  static function read($request)
  {
    $options = page::read_field_options(at($request,'field'));
    if (is_null($options)) throw new Exception ("Could not find options for field" . at ($request, 'field'));
    page::expand_values($options);
    
    $data = $options['data'];
    if (!isset($data)) return;
    
    $rows = array();
    $matches = array();
    if (!preg_match('/^([^:]+):\s*(.+)/', $data, $matches)) {
      throw new Exception("Invalid table expression $data");
    }
    $type = $matches[1];
    $source = $matches[2];
    if ($type == 'sql') {
      global $db;
      $sql = $source;
      $matches = array();
      if (preg_match_all('/(\$\w+)/', $sql, $matches)) foreach($matches as $match) {
        $var = $match[1];
        $val = REQUEST($var);
        if (!is_null($val)) $sql = str_replace('$'.$var, $val, $sql);
      }
      $rows = datatable::read_db($sql, page::null_merge($options, $request));
      $fields = array();
      foreach($db->field_names as $name) {
        $field = page::read_field_options($name);
        page::expand_values($field);
        $fields[] = page::null_merge(array('code'=>$name), $field);
      }
      $data = array('fields'=>$fields, 'rows'=>$rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $data, $matches) ) {
      $data = page::call($request, $type, $source);
    }

    $fields = $data['fields'];
    if (at(last($fields),'code') === 'actions')
      $data['actions'] = datatable::read_actions($options, $rows);
    echo json_encode($data);
  }
}