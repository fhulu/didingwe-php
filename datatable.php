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
  
  static function get_sql_fields($sql)
  {
    $matches = array();
    
    preg_match_all('/([^,]+),?/', substr(ltrim($sql),7), $matches, PREG_SET_ORDER);
    $fields = array();
    foreach($matches as $match) {
      $fields[] = trim($match[1]);
    }
    return $fields;
  }  
  
  static function field_named($fields, $name)
  {
    foreach($fields as $field) {
      list($real, $alias) = explode(' ',$field);
      if (is_null($alias)) list($alias) = explode('.', $field); 
      if ($alias == $name) return $real;
    }
      
    return null;
  }
  static function read_db($sql, $options) 
  {
    $page_size = at($options,'page_size');
    if (is_null($page_size)) $page_size = 0;
    $page_num = at($options, 'page_num');
    $offset = is_null($page_num)? 0: $page_size*($page_num-1);
    global $db;
    $sql_fields = datatable::get_sql_fields($sql);
    $sql = preg_replace('/^\s*select /i', 'select SQL_CALC_FOUND_ROWS ',$sql);
    $sort_field = at($options,'sort');
    log::debug("OPTIONS ".  json_encode($options));
    if (!is_null($sort_field)) {
      $sort_order = at($options,'sort_order');
      $sql .= " order by ". datatable::field_named($sql_fields, $sort_field) . " $sort_order"; 
    }
    $filtered = at($options, 'filtered');
    $rows = $db->page_indices($sql, $page_size, $offset);
    $names = $db->field_names;
    $total = $db->row_count();
    $fields = array();
    foreach($names as $name) {
      $field = page::read_field_options($name);
      page::expand_values($field);
      $fields[] = page::null_merge(array('code'=>$name), $field);
    }
    
    return array('fields'=>$fields, 'rows'=>$rows, 'total'=>$total,'sort'=>$sort_field, 'sort_order'=>$sort_order);
  }
  
  static function read($request)
  {
    log::debug("REQUEST ".json_encode($request));
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
      $data = datatable::read_db($sql, page::null_merge($options, $request));
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $data, $matches) ) {
      $data = page::call($request, $type, $source);
    }

    $fields = $data['fields'];
    if (at(last($fields),'code') == 'actions')
      $data['actions'] = datatable::read_actions($options, $data['rows']);
    echo json_encode($data);
  }
}