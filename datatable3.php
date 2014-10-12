<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../common/page3.php';
require('pdf/fpdf.php');

class datatable3 
{
  static function get_sql_fields($sql) {
    $matches = array();

    preg_match_all('/([^,]+),?/', substr(ltrim($sql), 7), $matches, PREG_SET_ORDER);
    $fields = array();
    foreach ($matches as $match) {
      $fields[] = trim($match[1]);
    }
    return $fields;
  }

  static function field_named($fields, $name) {
    foreach ($fields as $field) {
      $props = db::parse_column_name($field);
      if ($props['alias'] == $name)
        return $props['spec'];
    }
    return null;
  }

  static function sort(&$sql, $fields, $options) {
    $sort_field = at($options, 'sort');
    if (is_null($sort_field))
      return;
    $sort_order = at($options, 'sort_order');
    $sql .= " order by " . datatable3::field_named($fields, $sort_field) . " $sort_order";
  }

  static function filter(&$sql, $fields, $options) {
    $filter = at($options, 'filtered');
    if (is_null($filter))
      return;

    $index = -1;
    if (!in_array('show_key', $options['flags']))
      ++$index;

    $where = '';
    foreach (explode('|', $filter) as $value) {
      ++$index;
      if (trim($value) === '')
        continue;
      list($field) = explode(' ', $fields[$index]);
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

  static function read_db($page, $callback) 
  {
    $options = null_merge($page->result['fields'], $page->request, false);
    $page_size = at($options, 'page_size');
    if (is_null($page_size))
      $page_size = 0;
    $page_num = at($options, 'page_num');
    $offset = is_null($page_num) ? 0 : $page_size * ($page_num - 1);
    global $db;
    $options = array_merge($options, $options['row']);
    $sql = $options['sql'];
    $fields = datatable3::get_sql_fields($sql);
    $sql = preg_replace('/^\s*select /i', 'select SQL_CALC_FOUND_ROWS ', $sql);
    datatable3::filter($sql, $fields, $options);
    datatable3::sort($sql, $fields, $options);
    if ($page_size == 0)
      $rows = $db->page_through_indices($sql, 1000, 0, $callback);
    else
      $rows = $db->page_indices($sql, $page_size, $offset, $callback);
    $fields = array();
    $actions = array();
    foreach ($db->field_names as $name) {
      $field = array('code'=>$name);
      if ($name !== 'actions') 
        $field = null_merge($field, page3::get_field($options, $name));
      $fields[] = $field;
    }
    $total = $db->row_count();
    $result = array('fields' => $fields, 'rows' => $rows, 'total' => $total);
    return $result;
  }
  
  static function read_data($request, $callback = null) 
  {
    $request['path'] = 'fields/'.$request['path'];
    $page = new page3(false,$request);
    return datatable3::read_db($page, $callback);
  }

  static function read($request, $echo = true) 
  {
    $data = datatable3::read_data($request);
    
    if ($echo) echo json_encode($data);
  }

  static function start_export($request) {
    log::debug("START EXPORT " . json_encode($request));
    $request['field'] = $request['_page'];
    unset($request['a'], $request['_page'], $request['_field']);
    $url = '/?a=datatable/export';
    array_walk($request, function($value, $key) use (&$url) {
      $url .= "&$key=" . urlencode($value);
    });
    page::redirect($url);
  }

  static function export($request) {
    ini_set('memory_limit', '512M');
    require_once '../PHPExcel/Classes/PHPExcel.php';

    $excel = new PHPExcel();
    $sheet = $excel->setActiveSheetIndex(0);
    $request['page_size'] = 0;
    $data = datatable::read_data($request, function($row_data, $pagenum, $index) use ($sheet) {
        $row = 2 + $pagenum * 1000 + $index;
        $col = 'A';
        foreach ($row_data as $cell) {
          $sheet->setCellValue("$col$row", $cell);
          $sheet->getColumnDimension($col)->setAutoSize(true);
          $sheet->getRowDimension($row)->setRowHeight(20);
          ++$col;
        }
        $sheet->setCellValue("$col$row", ''); // take care of PHPExcel bug which fails to remove the last column
        return true;
      });
    $col = 'A';
    foreach ($data['fields'] as $field) {
      $ref = $col . "1";
      $sheet->getStyle($ref)->getFont()->setBold(true);
      if ($field['code'] === 'actions')
        $sheet->removeColumn($col);
      else
        $sheet->setCellValue($ref, $field['name']);
      ++$col;
    }
    $heading = $data['options']['name'];
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
  
  static function start_print($request) {
    log::debug("START PRINT " . json_encode($request));
    $request['field'] = $request['_page'];
    unset($request['a'], $request['_page'], $request['_field']);
    $url = '/?a=datatable/pdf';
    array_walk($request, function($value, $key) use (&$url) {
      $url .= "&$key=" . urlencode($value);
    });
    page::redirect($url);
  }
  
  function pdf($request)
  {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->Image('ethekwini.png', 80, 10, 35);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Footer('Council for Scientific and Industrial Research (CSIR)');
    $pdf->Ln(40);
    $request['page_size'] = 0;
    //$options = page::read_field_options(at($request, 'field'));
    $page = new page($request);
    $options = $page->read_request('field');
    $widths = $options['widths'];
    $flags = $options['flags'];
    $show_key = in_array('show_key', $flags, true);
    $columns = array(array(),array()); // reserve space for heading and titles
    $data = datatable::read_data($request, function($row_data, $pagenum, $index) use (&$columns, $widths, $show_key) {     
      $fill_color = $index % 2 === 0? '216,216,216': '255,255,255';
      $col = array();
      $index = 0;
      foreach ($row_data as $value) {
       if ($index++ == 0 && !$show_key ) continue;
       $pos = each($widths);
       if ($pos === false) break;
       $width = max(18,$pos[1]/7);
       $col[] = array('text' => $value, 'width' =>  $width, 'height' => '5', 'align' => 'L', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => "$fill_color", 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
      }
      $columns[] = $col;
    });
       
    $titles = &$columns[1];
    $index = 0;
    $total_width = 0;
    foreach ($data['fields'] as $field) {
      if ($index++ == 0 && !$show_key ) continue;
      $pos = each($widths);
      if ($pos === false) break;
      $width = max(18,$pos[1]/7);
      $total_width += $width;
      $titles[] = array('text' => $field['name'], 'width' => $width, 'height' => '5', 'align' => 'L', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '192,192,192', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
    }
    
    $heading = &$columns[0];
    $now = new DateTime();
    $now->getTimestamp();
    $now->setTimezone(new DateTimeZone('Europe/London'));
    $now = $now->format('Y-m-d');
    $report_title=$options['report_title'];
    $heading[] = array('text' =>"$report_title for $now", 'width' =>  $total_width, 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '11', 'font_style' => 'B', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linearea' => 'LTBR');
    $pdf->WriteTable($columns,80,10);
    $pdf->Output();
  }

}
