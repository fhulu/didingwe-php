<?php

class isearch
{
  static function filter_term($sql, $term, $fields)
  {
    array_shift($fields);
    $likes = array();
    foreach($fields as $field) {
      $likes[] = "$field like '%$term%'";
    }
    $likes = "(". implode(' or ', $likes) . ")";
    $where_pos = strripos($sql, "where ");
    if ($where_pos === false)
      $sql .= " where $likes";
    else
      $sql = substr($sql, 0, $where_pos + 6) . " and $likes";
    return $sql;
  }

  static function q($options)
  {
    $page_size = at($options, 'max_size');
    if (is_null($page_size))
      $page_size = 0;
    global $db;
    $sql =  page::replace_sql($options['sql'], $options);
    if ($sql == '') return;
    $sql = isearch::filter_term($sql, $options['term'], $options['fields']);
    $sql = preg_replace('/^\s*(select )/i', '$1 SQL_CALC_FOUND_ROWS ', $sql, 1);
    if ($page_size == 0)
      $rows = $db->page_through_indices($sql, 1000);
    else
      $rows = $db->page_indices($sql, $page_size);
    $total = $db->row_count();
    return array('rows' => $rows, 'total' => $total);
  }
}
