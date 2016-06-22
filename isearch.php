<?php

class isearch
{
  static function filter_term($sql, $term, $fields, $conjuctor = "and")
  {
    $likes = array();
    foreach($fields as $field) {
      $likes[] = "$field like '%$term%'";
    }
    $likes = "(". implode(' or ', $likes) . ")";
    if (strripos($sql, "where "))
      return $sql .= " $conjuctor $likes";

    return $sql .= " where $likes";
  }

  static function split_words($term)
  {
    $matches = array();
    preg_match_all('/([^\s,]+)([\s,]+)?/', $term, $matches, PREG_SET_ORDER);
    $words = array();
    $sep = "";
    foreach($matches as $match) {
      $words[] = $match[1];
      $phrase .= $match[1];
      if (sizeof($words) > 1) $words[] = $phrase;
      $phrase .= $match[2];
    }
    return $words;
  }


  static function get_sql(&$options)
  {
    $sql = $options['sql'];
    if (is_null($sql)) {
      $table = $options['table'];
      if (is_null($table))
        throw new Exception("No table or sql supplied for isearch");
      $fields = $options['fields'];
      if (!isset($fields) || $fields == '*') {
        global $db;
        $fields = $options['fields'] = $db->field_names($table);
      }
      $sql = "select " . implode(',', $fields) . " from $table";
    }
    return page::replace_sql($sql, $options);
  }

  static function q($options)
  {
    global $db;
    $sql =  isearch::get_sql($options);
    if ($sql == '') return;
    $words = isearch::split_words($options['term']);
    // $search = $options['search'];
    // if (is_null($search))
      $search = $options['fields'];
    $conjuctor = "and";
    foreach($words as $word) {
      $sql = isearch::filter_term($sql, $word, $search, $conjuctor);
      $conjuctor = "or";
    }
    $sql = preg_replace('/^\s*(select )/i', '$1 SQL_CALC_FOUND_ROWS ', $sql, 1);
    $size = $options['size'];
    if (!isset($size)) $size = 0;
    $offset = $options['offset'];
    if (!isset($offset)) $offset = 0;
    if ($size == 0)
      $rows = $db->page_through_indices($sql, 1000, $offset);
    else
      $rows = $db->page_indices($sql, $size, $offset);
    $total = $db->row_count();
    return array('rows' => $rows, 'total' => $total);
  }
}
