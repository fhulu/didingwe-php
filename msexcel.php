<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'module.php';

class msexcel extends module
{
  function __construct($page) {
    parent::__construct($page);
  }
  
  function export() {
    ini_set('memory_limit', '512M');
    require_once 'PHPExcel/Classes/PHPExcel.php';
    $excel = new PHPExcel();
    $sheet = $excel->setActiveSheetIndex(0);
    $page = $this->page;
    $db = $page->get_module('db');

    // retrive options
    $page_options = $page->fields;
    $page_types = $page->types;
    $page_excel_options = at($page_options, 'excel');
    $invoker_options = $page->context;
    $invoker_excel_options = at($invoker_options, 'excel');
    $fields = choose_from_arrays('fields', $invoker_excel_options, $page_excel_options, $invoker_options, $page_options);

    $default_props = merge_options($page_excel_options, $invoker_excel_options);
    if (isset($default_props['name']))
      unset($default_props['name']);

    // get populate data
    $sql = choose_from_arrays('full_db_reader', $invoker_excel_options, $page_excel_options, $invoker_options, $page_options);
    $db->update_custom_filters($sql);

    // if no fields given, run query for one row to get the field names
    $auto_fields = $fields === 'auto';
    if ($auto_fields) {
      $row = $db->read_one($sql, MYSQLI_ASSOC);

      $fields = array_map(function($col_alias) use($invoker_options, $page) { 
        $field = $page->field_at($col_alias);
        return [$col_alias=>$field];
      }, array_keys($row));
    }

    // Column heading and styles
    $col = 'A';
    foreach ($fields as $field) {
      if (!$auto_fields && (!page::is_data($field) || !page::is_display($field))) continue;
      [$id, $props] = assoc_element($field);
      $props  = null_merge($default_props, at($props, 'excel'));
      $sheet->getStyle($col)->getAlignment()->setWrapText(at($props, 'wrap'));

      // set column data format
      $format = at($props, 'format');
      if ($format !== null) {
        $sheet->getStyle($col)->getNumberFormat()->setFormatCode($format);
      }

      $auto_size = at($props, 'auto_size');
      if ($auto_size !== null) {
        $sheet->getColumnDimension($col)->setAutoSize($auto_size);
      }
      
      // set heading text and style
      $ref = $col . "1";
      $sheet->getStyle($ref)->getFont()->setBold(true);
      $name = page::get_display_name($field);
      $sheet->setCellValue($ref, $name);
      ++$col;
    }

    $fetch_size = 1000;
    $page_num = 0;
    $data = $db->page_indices($sql, $fetch_size, 0, function($row_data, $index) 
        use (&$sheet, &$fields, $page_num, $fetch_size) {
      $row = 2 + $fetch_size*$page_num + $index;
      $col = 'A';
      $data_idx = -1;
      foreach($fields as $field) {
        if (!$auto_fields && !page::is_data($field)) continue;
        ++$data_idx;
        [$id, $props] = assoc_element($field);
        if (!$auto_fields && !page::is_display($field)) continue;
  

        $cell = $row_data[$data_idx];
        log::debug_json("$id $row $col $cell", $props);
        $sheet->setCellValue("$col$row", $cell);
        $sheet->getRowDimension($row)->setRowHeight(20);
        
        ++$col;
      }

      // $sheet->setCellValue("$col$row", ''); // take care of PHPExcel bug which fails to remove the last column
      return true;
    });

    if ($options['auto_filter']) {
      // $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
    }
    // File properties
    $heading = choose_from_arrays('name', $page_excel_options, $invoker_excel_options, $page_options, $invoker_options);
    $creator = choose_from_arrays('creator', $page_excel_options, $invoker_excel_options, $page_options, $invoker_options);
    $excel->getProperties()->setCreator($creator)
      ->setLastModifiedBy($creator)
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

    return false;
  }
}