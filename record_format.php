<?php

require_once("field_format.php");

class record_format_exception extends Exception {};

class record_format extends format_delimited
{ 
  var $quote;
  function __construct($delimiter, $format_string, $quote='')
  {
    $this->delimiter = $delimiter;
    $this->quote = $quote;
    $formats = is_array($format_string)? $format_string: explode("|", $format_string);
    record_format::parse($formats, $this->fields, $this->positions);
  }
  
  

  static function parse(&$formats, &$fields, &$positions)
  {
    $column_count = sizeof($formats);
    for($column_idx = 0; $column_idx < $column_count; ++$column_idx) {
      $params = explode(".", $formats[$column_idx]);
      
      $position = new format_position;
      $position->name = $params[0];
      $param_count = sizeof($params);

      if ($param_count > 1 && $params[1] == "prev") {
        $use_previous = true;
        array_splice($params, 1, 1);
        --$param_count;
      }
      else $use_previous = false;


      if ($param_count == 1 || is_numeric($params[1])) {
        $field = new format_unit;
        if ($position->name == '') $field->type = FORMAT_IGNORE;
        $repeat_count = $param_count>1? $params[1]:1;
      }
      else if ($params[1] == "composite") {
        if (!is_numeric($params[2]))
          throw new record_format_exception("Invalid composite field format for $position->name at column $column_idx");

        $field = new format_composite;
        $field->num_fields = (int)$params[2];
        $field->combiner = $params[3];        
        $repeat_count = $param_count>4? $params[4]:1;
      }
      else if ($params[1] == "record") {  // name.record.field_count[.repeat_count]
        if (!is_numeric($params[2]))
          throw new record_format_exception("Invalid record_format field format for $position->name at column $column_idx");

        $field = new format_record;
        $field_count = (int)$params[2];
        $record_formats = array_slice($formats, $column_idx+1, $field_count);
        record_format::parse($record_formats, $field->fields, $field->positions);
        $column_idx += $field_count;
        $repeat_count = $param_count>3? $params[3]:1;
      }
      else if ($params[1] == "delimited") { // name.delimited.delimiter
        if (sizeof($params) < 3)
          throw new record_format_exception("No delimiter supplied for delimited field format for $position->name at column $column_idx");

        $field = new format_delimited();
        $field->delimiter = $params[2][0];
        $field_formats = $params[2] . "." . implode(".", array_slice($params, 3));
        $field_formats = explode($field->delimiter, $field_formats);
        array_shift($field_formats);
        record_format::parse($field_formats, $field->fields, $field->positions);
        $repeat_count = 1;
      }
      else {
        echo "$formats[$column_idx]\n";
        print_r($params);
        print_r($position);
        throw new record_format_exception("Invalid field format for $position->name at column $column_idx");
      }
      
      if (is_numeric($repeat_count)) 
        $repeat_count = (int)$repeat_count;
      else
        throw new record_format_exception("Invalid repeat count($repeat_count) for '$position->name' at column $column_idx");

      if ($repeat_count==0 && $use_previous) 
         throw new record_format_exception("Cannot use infinite repeat count and use previous field together on field $name at column $column_idx");	
      
      $position->repeat_count = $repeat_count;
      $positions[] = $position;
      
     // if ($position->name != "") {
        $field->use_previous = $use_previous;
      	$fields[$position->name] = $field;
      //}     
   }
  }

  static function read_composite($field, &$line, &$line_idx)
  {
    if ($field->num_fields  > 0)  {
      $last_idx = $line_idx + $field->num_fields;
      if ($last_idx > sizeof($line)) $last_idx = sizeof(line);
    }
    else  {
       $last_idx = sizeof($line) - $line_idx;
    }
       
    $value = trim($line[$line_idx++]);
    for ($i=$line_idx; $i < $last_idx; ++$i) {       
      $part = trim($line[$i]);
      if ($part != "") $value .= $field->combiner . $part;
    }
    $line_idx = $last_idx;    
    return $value;
  }

  static function read_line($positions, &$fields, &$line, &$line_idx, &$values)
  {
    $line_col_count = sizeof($line);
    $position_count = sizeof($positions);
    $repeat_count = null;
    for($position_idx=0; $position_idx < $position_count; ++$position_idx) {
      $position = $positions[$position_idx];
      $key = $position->name;
      $field = &$fields[$key];
    
      if ($field->use_previous) --$line_idx;
      if ($line_idx >= $line_col_count) break;

      if ($position->repeat_count > 1) {
         if (is_null($repeat_count)) $repeat_count = $position->repeat_count;
         if ($repeat_count-- > 1)  --$position_idx; else $repeat_count = null;
      }
      else {
        $repeat_count = null;
        if ($position->repeat_count == 0) 
         --$position_idx;
      }
     
      if ($field->type == FORMAT_IGNORE) {
        $line_idx++;
        continue; 
      }
 
      $value = &$values[$key];
      
      if ($position->repeat_count != 1 || isset($value)) {
        if (!is_array($value)) 
           $value = array($value);
         else
           $value[] = "";
        $value = &$value[sizeof($value)-1];
      }
      switch($field->type) {
      case FORMAT_UNIT: 
        $value = trim($line[$line_idx++]);
        break;
      case FORMAT_COMPOSITE:
        $value = record_format::read_composite($field, $line, $line_idx);
        break;
      case FORMAT_RECORD:
        $value = array();
        record_format::read_line($field->positions, $field->fields, $line, $line_idx, $value);
        break;
      case FORMAT_DELIMITED:
        $value = array();
        $my_line = explode($field->delimiter, $line[$line_idx++]);
        record_format::read_line($field->positions, $field->fields, $my_line, 0, $value);
        break;
      }
    }    
  }

  function read($line, &$values)
  {
    if (!is_array($line)) {
      $delimiter = $this->delimiter==''?' ':$this->delimiter;
      if ($delimiter[0] == '/') {
        $line = preg_split($delimiter, trim($line));
      }
      else if ($this->quote != '') {
        $pattern = '/((?:(?:"[^"]+")|(?:[^'.$delimiter.']+))+)/';
        $matches = array();
        preg_match_all($pattern, $line, $matches);
        if (strpos($line, $delimiter) === 0)
          $line = array_merge(array(''), $matches[0]);
        else
          $line = $matches[0];
        foreach($line as &$value) {
          $value= str_replace($this->quote, '', $value);
        }
      }
      else 
        $line = explode($delimiter, trim($line));
    }
    $idx = 0;
    record_format::read_line($this->positions, $this->fields, $line, $idx, $values);

  }    

  static function load_delimited_file($file_path, $delimiter, $fields, $callback)
  {
    if (is_null($fields)) {
      list($file, $fields) = record_format::read_tag_line($file_path, $delimiter);
      $line_index = 1;
    }
    else {
      $file = fopen($file_path, "rb");
      if (!$file) 
        throw new record_format_exception("Unable to load file '$file_path'");

      $line_index = 0;
    }
    $format = new record_format($delimiter, $fields, '"');
    while(!feof($file)) {
      $line = fgets($file);
      if ($line == "") continue;
      $values = null;
      $format->read($line, $values);
      if ($callback($line, $line_index, $values) === false) break;
      ++$line_index;
    }
    
    return $line_index;
  }

  static function read_tag_line($file_path, $delim)
  {
   	$file = fopen($file_path, "rb");
    if (!$file) 
      throw new record_format_exception("Unable to load file '$file_path'");
    $line = fgets($file);
    if ($delim[0] == '/')
      $tags = preg_split($delim, trim($line));
    else 
      $tags = explode($delim, trim($line));
    foreach($tags as &$tag) {
      $tag = str_replace(' ', '_', strtolower($tag));
    }
    return array($file, $tags);
  }
}
?>
