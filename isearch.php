<?php

class isearch
{
  static function filter_term($sql, $term, $fields, $conjuctor = "and")
  {
    array_shift($fields);
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

  static function q($options)
  {
    $page_size = at($options, 'max_size');
    if (is_null($page_size))
      $page_size = 0;
    global $db;
    $sql =  page::replace_sql($options['sql'], $options);
    if ($sql == '') return;
    $words = isearch::split_words($options['term']);
    $fields = $options['fields'];
    $conjuctor = "and";
    foreach($words as $word) {
      $sql = isearch::filter_term($sql, $word, $fields, $conjuctor);
      $conjuctor = "or";
    }
    $sql = preg_replace('/^\s*(select )/i', '$1 SQL_CALC_FOUND_ROWS ', $sql, 1);
    if ($page_size == 0)
      $rows = $db->page_through_indices($sql, 1000);
    else
      $rows = $db->page_indices($sql, $page_size);
    $total = $db->row_count();
    return array('rows' => $rows, 'total' => $total);
  }
}
