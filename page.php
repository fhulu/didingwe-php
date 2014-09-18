<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of form
 *
 * @author fhulu
 */

require_once 'session.php';
require_once 'db.php';
require_once 'validator.php';
require_once 'ref.php';

class page {
    
 
  static function read_field_options($field, $expand=true)
  {
    $db_fields = array();
    $scope = array();
    $options = page::decode_db_options($db_fields, $field, $scope);
    return $options;
  }
  
  
  static function decode_db_options(&$fields, $field, $scope)
  {
    if (isset($fields[$field])) return $fields[$field];

    global $db;
    $options =  $db->read_one(
      "select f.name, f.description 'desc', ifnull(f.html,ft.html) html, ft.options type_options, f.options field_options"
      . " from field f left join field_type ft on f.type = ft.code"
      . " where f.code = '$field'"
      . " union "
      . " select name, description 'desc', html, null type_options, options field_options"
      . " from field_type where code = '$field'"
      , MYSQLI_ASSOC);
    
    if (is_null($options)) {
      $fields[$field] = null;
      return null;
    }
    
    foreach($options as $key=>$value) {
      if (is_null($value)) unset ($options[$key]);
    }

    $scope = page::decode_options($fields, $options, at($options,'type_options'), $scope);
    $scope = page::decode_options($fields, $options, at($options,'field_options'), $scope);
    page::expand_variables($db_fields, $options, $scope);
    unset($options['type_options'], $options['field_options']);
    $fields[$field] = $options;
    return $options;
  }
  
  static function null_merge($array1, $array2) 
  {
    if (is_array($array1)) 
      return is_array($array2)? array_merge($array1, $array2): $array1;
    return $array2;
  }
  
  static function merge_to(&$array1, $array2)
  {
    $array1 = page::null_merge($array1, $array2);
  }
  
  
  static function compress_options($options)
  {
    $compressed = array();
    foreach($options as $key=>$value) {
      if (is_null($value))
        $compressed[] = $key;
      else
        $compressed[$key] = $value;
    }
    return $compressed;
  }
  
  static function replace_vars($str, $values)
  {
    foreach($values as $name=>$value) {
      $str = str_replace('$'.$name, $value, $str);
    }
    return $str;
  }
  static function decode_options(&$db_fields, &$parent, $encoded, $scope)
  {            
    if ($encoded == '') return $scope;
    $matches = array();
    $encoded = str_replace('~', ',', $encoded);
    $encoded = str_replace('\n', '', $encoded);
    $encoded = str_replace('\r', '', $encoded);
    if (!preg_match_all('/(\$?\w+)(?::\s*("[^"]*"|\[[^\]]*\]|{[^}]*}|[^,]*))?/', $encoded, $matches, PREG_SET_ORDER)) {
      log::error("Invalid JSON string $encoded");
      return $scope;
    }
    $index = 0;
    foreach($matches as $match) {
      $name = $match[1];
      if ($name[0]=='$') $name = REQUEST(substr($name,1));

      $parent[$name] = at($scope,$name);
      $value = at($match,2);
      ++$index;
      if ($value == 'true' || $value == 'false') {
        $scope[$name] = $parent[$name] = $value == 'true';
        continue;
      }
      
      $prefix = at($value,0);
      if ($prefix == '"') {
        $value = substr($value,1,strlen($value)-2);
        $scope[$name] = $parent[$name] = page::replace_vars($value, $_REQUEST);
        continue;
      }

      $is_template = $name == 'template';
      if ($is_template && $prefix == '$') {
        $scope[$name] = $parent[$name] = page::replace_vars($value, $_REQUEST);
        continue;
      } 
            
      if (in_array($prefix, array('{', '['))) {
        $value = substr($value,1,strlen($value)-2);
      }
      else if (!$is_template && !is_null($value) && ($prefix != '$' || at($value,1) == '$')) {
        $scope[$name] = $parent[$name] = page::replace_vars($value, $_REQUEST);
        continue;
      }
      
      $options = page::decode_db_options($db_fields, $name, $scope);
      if (is_null($value)) {
        $parent[$name] = page::null_merge($options, at($parent,$name));
        continue;
      }
      
      $options = page::null_merge($parent[$name], $options);

      $scope = page::decode_options($db_fields, $options, $value, $scope);
      if ($prefix == '[') {
        $scope[$name] = $parent[$name] = page::compress_options($options);
        continue;
      }
      
      if ($prefix == '{') {
        $scope[$name] = $parent[$name] = $options;
        continue;
      }
      
      
      $db_opts = page::decode_db_options($db_fields, $value, $scope);

      $options[$value] = page::null_merge($db_opts, at($options,$value));
      $options[$value] = page::null_merge(at($options,$value), at($parent,$value));
      page::merge_to($options[$value], at($parent,$value));
      
      if ($is_template) 
        $parent[$name] = at($options[$value],'html');
      else
        $parent[$name] = $options;
      $scope[$name] = $parent[$name];
    }
    return $scope;
  }
  
    static function expand_variables(&$db_fields, &$data, $scope)
  {
    if (isset($data['has_data']) || !isset($data['html'])) return;
    $matches = array();
    if (!preg_match_all('/\$(\w+)/', $data['html'], $matches)) return;
    
    $vars = array_diff($matches[1], array('code','name','desc'));
    foreach($vars as $var) {
      $value = page::decode_db_options($db_fields, $var, $scope);
      if (is_null($value)) continue;
      page::merge_to($data[$var], $value);
    }
  }

  static function expand_values(&$row, $exclusions=array())
  {
    if (!is_array($row)) return;
    foreach($row as $key1=>&$value1) {
      if (is_array($value1) || in_array($key1, $exclusions)) continue;
      foreach ($row as $key2=>$value2) {
        if (!is_array($value2))
          $value1 = preg_replace('/\$'.$key2.'([^\w]*)/', "$value2\$1", $value1);
      }
    }
  }
  
  static function empty_fields(&$options, $fields)
  {
    foreach($options as $key=>&$option)
    {
      if (is_numeric($key)) continue;
      if (in_array($key, $fields, true)) 
        $option = "";
      else if (is_array($option))
        page::empty_fields($option, $fields);
      else if ($key == 'action' && strpos($option, 'dialog:') !== 0 && strpos($option, 'url:') !== 0)
        $option = "";
    }
  }
  static function read($request)
  {
    log::debug("page::read ".json_encode($request));
    $data = page::read_field_options(at($request,'page'));
    page::empty_fields($data, array('data'));
    $data['program'] = config::$program_name;
    echo json_encode($data);
  }
   
  static function validate(&$validator, $options)
  {
    foreach($options as $code=>$values) {
      if (!is_array($values)) continue;
      $valid = trim(at($values,'valid'));
      if ($value == '') continue;
      if (is_null($valid)) {
        page::validate ($validator, $values);
        continue;
      }
      $matches = array();
      if (!preg_match_all('/([^,]+),?/', $valid, $matches, PREG_SET_ORDER)) 
        throw new Exception("Invalid validators $valid");

      $name = at($values, 'name');
      foreach($matches as $match) {
        $valid = $match[1];
        if ($valid == 'optional' && !$validator->check($code, $name)->provided()) continue;
        $validator->check($code, $name)->is($valid);
      }
    }
    
    return $validator->valid();
  }

  static function read_page_field_options($request, &$page_options=null)
  {
    $db_fields = array();
    $scope = array();
    $page_options = page::decode_db_options($db_fields, at($request,'_page'), $scope);
    $field = $request['_field'];
    $options = page::decode_db_options($db_fields, $field, $scope);

    return page::null_merge(at($page_options,$field), $options);
  }
  
  static function data($request)
  {
    log::debug('page::data page='.$request['_page']. ', field='.$request['_field']);
    $row = page::read_page_field_options($request);    
    page::expand_values($row, array('template','html'));    
    $data = $row['data'];
    log::debug("REQUEST: ". json_encode($request));
    log::debug("DATA: ". $data);
    $template = $row['template'];
    log::debug("TEMPLATE: ". $template);
    
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/s', $data, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'inline') {
      $values = explode('|', $list);
      $rows = array();
      foreach($values as $pair) {
        list($code) = explode(',', $pair);
        $value['item_code'] = $code;
        $value['item_name'] = substr($pair, strlen($code)+1);
        $rows[] = $value;
      }
    }
    else if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_ASSOC);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]+)\)/', $data, $matches) ) {
      page::call($request, $matches[1], $matches[2]);
    }

    if (isset($template)) $rows['template'] = $template;  
    echo json_encode($rows);
  }
  
  static function call($request, $function, $params)
  {
    log::debug("FUNCTION $function PARAMS:".$params);
    list($class, $method) = explode('::', $function);
    $file = "$class.php";
    if (isset($method)) {
      if (file_exists($file)) 
        require_once("$class.php");
      else if (file_exists("../common/$file"))
        require_once("$class.php");
      else {
        log::error("No such file $file");
        return;
      }
    }
    call_user_func($function, $request, $params);
  }
  
  static function action($request)
  {
    $page_options = array();
    $options = page::read_page_field_options($request, $page_options);
    page::expand_values($options);
    page::expand_values($page_options);
    
   
    if (array_key_exists('validate', $options)) {
      $validator = new validator($request);
      if (!page::validate($validator, $page_options)) return;
    }
     
    $action = at($options,'action');
    if (is_null($action)) return;
    
    $rows = array();
    $matches = array();
    if (!preg_match('/^([^:]+): ?(.+)/s', $action, $matches)) {
      throw new Exception("Invalid action spec $action");
    }
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $rows = $db->read($list, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $action, $matches) ) {
      page::call($request, $matches[1], $matches[2]);
    }
  }

  static function check_field($options, $field)
  {
    $value = $options[$field];
    if (isset($value)) return $value;
    
    log::warn("No $field parameter provided");
    return false;
  }
  static function load($request)   
  {  
    $options = page::read_field_options($request['page']);
    page::expand_values($options);

    if (!($key=page::check_field($request, 'key'))
      || !($load=page::check_field($options, 'load'))) return;
   
    log::debug("key=$key, $options=".json_encode($options));
    $rows = array();
    $matches = array();
    preg_match('/^([^:]+): ?(.+)/s', $load, $matches);
    $type = $matches[1];
    $list = $matches[2];
    if ($type == 'sql') {
      global $db;
      $sql = str_replace('$key', addslashes($key), $list);
      $rows = $db->read_one($sql, MYSQLI_ASSOC);
      echo json_encode($rows);
    }
    else if (preg_match('/^([^\(]+)\(([^\)]*)\)/', $action, $matches) ) {
      page::call($request, $matches[1], $matches[2]);
    }
  }
    
  static function send_json($field, $value=null)
  {
    global $json;
    $json[$field] = is_null($value)?'':$value;
    log::debug(json_encode($json));
  }
  
  static function alert($message)
  {
    page::send_json('alert', $message);
  }
  
  static function redirect($url)
  {
    page::send_json('url', $url);
  }
  
  static function close_dialog($message=null)
  {
    if (!is_null($message)) page::alert($message);
    page::send_json('close_dialog');
  }
}
