<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../common/page.php';
require_once('pdf/fpdf.php');

class datatable 
{
  static function get_sql_fields($sql) 
  {
    $matches = array();
    $sql = preg_replace('/[\n\r\s]/', ' ', $sql);
    if (!preg_match('/^select (((?!from).)*)(?:from.+)?$/', $sql, $matches))
      throw new Exception("Invalid or complex SQL while parsing fields");
    $pattern = <<< PATTERN
/[^,]*\((?>[^()]|(?R))*\)( [^,]+)?|[^,]*'(?>[^()]|(?R))*'( [^,]+)?|[^,]*"(?>[^()]|(?R))*"( [^,]+)?|[^,]+/
PATTERN;
    $fields_sql = $matches[1];
    if (!preg_match_all($pattern, $fields_sql, $matches))
      throw new Exception("Invalid or complex SQL while splitting fields $fields_sql");
      
    $fields = array();
    foreach ($matches[0] as $field) {
      $aliases = array();
      if (!preg_match('/^(.+end) +as .*$|^(.+end) .*$|^(.+) +as .*$|^(.+)$/', $field, $aliases)) 
        throw new Exception ("Invalid SQL field $field");
      array_shift($aliases);
      foreach ($aliases as $alias) {
        if ($alias!='') break;
      }
      $fields[] = trim($alias);
    }
    return $fields;
  }

  static function field_named($fields, $name) 
  {
    foreach ($fields as $field) {
      $props = db::parse_column_name($field);
      if ($props['alias'] == $name)
        return $props['spec'];
    }
    return null;
  }

  static function get_field_index($options, $code)
  {
    $index = -1;
    foreach($options['fields'] as $field) {
      ++$index;
      $field = page::collapse($field);
      log::debug_json($index, $field);
      if ($field['hide']) continue;
      if ($field['code'] == $code ) break;
    }
    return $index;
  }
  
  static function sort(&$sql, $fields, $options) 
  {
    $sort_field = at($options, 'sort');
    if (is_null($sort_field))
      return;
    $index = datatable::get_field_index($options, $sort_field);
    $db_sort_field = at($fields, $index);
    $sort_order = at($options, 'sort_order');
    if (is_array($sort_order)) $sort_order = last($sort_order);
    $sql .= " order by $db_sort_field $sort_order";
  }

  static function filter(&$sql, $fields, $options) {
    $filter = at($options, 'filtered');
    if (is_null($filter))
      return;

    $index = -1;
    $where = '';
    foreach (explode('|', $filter) as $value) {
      ++$index;
      if (trim($value) === '') continue;
      $field = $fields[$index];
      $where .= " and $field like '%$value%' ";
    }

    if ($where === '')
      return;
    $where_pos = strripos($sql, "where ");
    $where = substr($where, 5);
    if ($where_pos === false)
      $sql .= " where $where";
    else
      $sql = substr($sql, 0, $where_pos + 6) . "$where and " . substr($sql, $where_pos + 6);
  }

  static function read($options, $key, $callback=null) 
  {
    log::debug("KEY $key");
    $page_size = at($options, 'page_size');
    if (is_null($page_size))
      $page_size = 0;
    $page_num = at($options, 'page_num');
    $offset = is_null($page_num) ? 0 : $page_size * ($page_num - 1);
    global $db;
    $sql =  page::replace_sql($options['sql'], $options);
    if ($sql == '') return;
    $fields = datatable::get_sql_fields($sql);
    $sql = preg_replace('/^\s*(select )/i', '$1 SQL_CALC_FOUND_ROWS ', $sql, 1);
    datatable::filter($sql, $fields, $options);
    datatable::sort($sql, $fields, $options);
    if ($page_size == 0)
      $rows = $db->page_through_indices($sql, 1000, 0, $callback);
    else
      $rows = $db->page_indices($sql, $page_size, $offset, $callback);
    $total = $db->row_count();
    return array('rows' => $rows, 'total' => $total);
  }
  
  static function get_display_field($code)
  {
    $field = page::collapse($code);
    if ($field['hide'] || in_array($field['code'], array('attr','actions'), true)) return false;
    return $field;
  }
  
  static function get_display_name($field)
  {
      $name = at($field,'name');
      if (!is_null($name)) return $name;
      return ucwords(preg_replace('/[_\/]/', ' ',at($field,'code')));
  }
  
  static function export($options, $key) {
    ini_set('memory_limit', '512M');
    require_once '../PHPExcel/Classes/PHPExcel.php';

    $excel = new PHPExcel();
    $sheet = $excel->setActiveSheetIndex(0);
    $options['page_size'] = 0;
    $fields = $options['fields'];
    $data = datatable::read($options, $key, function($row_data, $pagenum, $index) use ($sheet, $fields) {
      $row = 2 + $pagenum * 1000 + $index;
      $col = 'A';
      $col_index = 0;
      foreach ($row_data as $cell) {
        if (!datatable::get_display_field($fields[$col_index++])) continue;
        $sheet->getStyle("$col")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("$col$row", $cell);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->getRowDimension($row)->setRowHeight(20);
        ++$col;
      }
      $sheet->setCellValue("$col$row", ''); // take care of PHPExcel bug which fails to remove the last column
      return true;
    });
    $col = 'A';
    foreach ($fields as $code) {
      $ref = $col . "1";
      if (!($field = datatable::get_display_field($code))) continue;
      $sheet->getStyle($ref)->getFont()->setBold(true);
      $name = datatable::get_display_name($field);
      $sheet->setCellValue($ref, $name);
      ++$col;
    }
    $heading = choose_value($options, 'report_title', 'name');
    global $session;
    $user = $session->user->first_name . " " . $session->user->last_name;
    $excel->getProperties()->setCreator($user)
      ->setLastModifiedBy($user)
      ->setTitle($heading)
      ->setSubject($heading)
      ->setDescription($heading)
      ->setKeywords($heading)
      ->setCategory($heading);
    // Redirect output to a client’s web browser (Excel5)
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment;filename=\"$heading.xls\"");
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
    $objWriter->save('php://output');
  }
  
  static function pdf($options, $key)
  {
    $pdf = new FPDF();
    $orientation = $options['report_orientation'];
    $page_width = 196;
    if ($orientation == 'landscape') {
      $pdf->AddPage('L');
      $page_width *= 4 / 3;
    }
    else 
      $pdf->AddPage ('P');
    
    if (file_exists($options['report_image'])) {
      $pdf->Image($options['report_image'], $page_width/2, 10, 35);
      $pdf->Ln(40);
    }
    $pdf->SetFont('Arial', 'B', 10);
    $options['page_size'] = 0;
    $flags = $options['flags'];
    $fields = $options['fields']; 
    $columns = array(array(),array()); // reserve space for heading and titles
    $data = datatable::read($options, $key, function($row_data, $pagenum, $index) use (&$columns, $page_width, $fields) {     
      $fill_color = $index % 2 === 0? '225,225,225': '255,255,255';
      $col = array();
      $index = 0;
      $col_index = 0;
      foreach ($row_data as $value) {
        if (!($field = datatable::get_display_field($fields[$col_index++]))) continue;
        $width = max(5,$page_width*(float)$field['width']/100);
        $col[] = array('text' => $value, 'width' =>  $width, 'height' => '5', 'align' => 'L', 'font_name' => 'Arial', 'font_size' => '7', 'font_style' => '', 'fillcolor' => "$fill_color", 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.1', 'linearea' => 'LTBR');
      }
      $columns[] = $col;
    });
       
    $titles = &$columns[1];
    $index = 0;
    $total_width = 0;
    foreach ($fields as $code) {
      if (!($field = datatable::get_display_field($code))) continue;
      $width = max(5,$page_width*(float)$field['width']/100);
      $name = datatable::get_display_name($field);
        $titles[] = array('text' => $name, 'width' => $width, 'height' => '5', 'align' => 'L', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '128,128,128', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.1', 'linearea' => 'LTBR');
    }
    
    $heading = &$columns[0];
    $now = new DateTime();
    $now->getTimestamp();
    $now->setTimezone(new DateTimeZone('Europe/London'));
    $now = $now->format('Y-m-d');
    $report_title=$options['report_title'];
    $heading[] = array('text' =>"$report_title for $now", 'width' =>  $total_width, 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '11', 'font_style' => 'B', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linearea' => '');
    $pdf->WriteTable($columns,80,10);
    $pdf->Output();
  }

}
