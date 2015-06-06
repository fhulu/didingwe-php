<?php
require_once('db.php');
require_once 'curl.php';

class validator_exception extends Exception {};
class validator
{  
  var $request;
  var $db;
  var $name;
  var $prev_name;
  var $value;
  var $predicates;
  var $error;
  var $fields;
  function __construct($request, $fields, $predicates=null, $db_conn=null)
  {
    $this->request = $request;
    $this->fields = $fields;
    $this->predicates = $predicates;
    $this->optional = false;
    $this->error = null;
    global $db;
    $this->db = is_null($db_conn)? $db: $db_conn;
  }
 
  function regex($regex)
  {
    return preg_match($regex, $this->value) != 0;
  }
  
  function url($option)
  {
    $result = $this->regex('/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/');
    if ($result !== true) return $result;
    
    if ($option == 'visitable') {
      $curl = new curl();
      return $curl->read($this->value,512) != 0;
    }
    return true;
  }
 
  function less($name)
  {
    return $this->value < $this->request[$name];
  }
  
  function less_equal($name)
  {
    return $this->value <= $this->request[$name];
  }
  
  function greater($name)
  {
    return $this->value > $this->request[$name];
  }
  
  function greater_equal($name)
  {
    return $this->value >= $this->request[$name];
  }
  
  
  function find_in($table)
  {
    if ($table == '') {
      $table = $this->table;
      if ($table == '')
        throw new validator_exception('checking for existence with no db table supplied');
      $field = $this->name;
    }
    else {
      list($dbname, $table, $field) = explode('.', $table);
      if (!isset($table)) {
        $table = $dbname;
        $field = $this->name;
      }
      else if (!isset($field)) {
        $field = $table;
        $table = $dbname;
      }
      else {
        $table = "$dbname.$table";
      }
    }
    $value = addslashes($this->value);
    return $this->sql("select $field from $table where $field = '$value'");
  }

  function in($table=null)
  {
    return $this->find_in($table);
  }
  
  function exist($table=null)
  {
    return $this->in($table);
  }
  
  function exists($table=null)
  {
    return $this->in($table);
  }

  function either()
  {
    return in_array($this->value, func_get_args());
  }
  
  function depends($field, $arg)
  {
    if ($field == 'this') $field = $this->name;
    $validator = new validator($this->request, $this->fields, $this->predicates, $this->db);
    log::debug("DEPENDS $field $arg");
    if ($validator->check($field)->is($arg)) return true;
    $this->error = null;
    return false;
  }
  
  function call($function)
  {
    $params = array_slice(func_get_args(), 1);
    log::debug_json("VALIDATE CALL $function PARAMS:", $params);
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
    
    if (!is_callable($function)) {
      log::warn("Uncallable function $function");
      return;
    }
    

    foreach($params as &$param) {
      $param = trim($param);
      if ($param=='this') $param = $this->name;
      if (array_key_exists($param, $this->request)) $param = $this->request[$param];
      $param = replace_vars ($param, $this->request);
    }
    return call_user_func_array($function, $params);
  }
  
  function title($code, $field=null)
  {
    if (is_array($field)) $name = $field['name'];
    if ($name == '') $name = ucwords(str_replace ('_', ' ', $code));
    return $name;
  }
  
  static function is_static_method($name)
  {
    return strpos($name, '::') !== false;
  }
  
  function check($name)
  {
    $this->name = $name;
    $this->value = trim(at($this->request,$name));
    if ($this->prev_name != $name)
      $this->optional = false;
    $this->prev_name = $name;
    $this->error = null;
    return $this;
  }
  
  
  function is($funcs)
  {
    if (!is_array($funcs)) $funcs = array($funcs);
    log::debug("VALIDATE $this->name=$this->value FUNCTIONS: $func", $funcs);
    
    if ($funcs[0] == 'optional') {
      if  ($this->value === '') return true;
      array_shift($funcs);
    }
    
    foreach($funcs as $func) {
      $args = array($func);
      if ($func[0] == '/') 
        $func = 'regex';
      else {
        $matches = array(); 
        log::debug("VALIDATE FUNC $func");
        if (!preg_match('/^([^\(]+)(?:\((.*)\))?/', $func, $matches)) 
          throw new validator_exception("Invalid validator expression $func!");

        $func = $matches[1];
        $args = array();
        preg_match_all('/\(.*\)|[^,]+/', $matches[2], $args);
        $args = $args[0];
      }

      $is_method = method_exists($this, $func);
      if (validator::is_static_method($func)) {
        array_unshift($args, $func);
        $func = 'call';
      }
      else if (!$is_method) {
        if (!array_key_exists($func, $this->predicates)) 
          throw new validator_exception("validator method $func does not exists!");
        array_unshift($args, $func);
        $func = 'custom';
      }
      $result = call_user_func_array(array($this, $func), $args);
      if (!$this->update_error($func, $args, $result)) return false;
    }
    return true;
  }
 
  function validate($code, $field)
  {
    log::debug_json("VALIDATE $code", $field);
    $valid = $field['valid'];
    if (!is_array($valid)) $valid = array($valid);

    $this->check($code);
    
    $first = $valid[0];  
    if ($first != 'provided' && $first !== 'optional' && !preg_match('/^depends\(/', $first))
      array_unshift($valid, 'provided');
    
    $this->error = $field['error'];
    foreach($valid as $check) {
      if ($this->is($check) || !$this->error) continue;
      $name = $this->title($code, $field);
      $this->error = str_replace('$name', $name, $this->error);
      page::error($code, $this->error);
      return false;
    }
    return true;
  }
  
  function custom($func)
  {
    $args = array_slice(func_get_args(), 1);
    
    $predicate = $this->predicates[$func];
    log::debug_json("VALIDATE CUSTOM $func",$args);
    
    if (is_array($predicate)) {
      replace_field_indices($predicate, $args);
      $valid = $predicate['valid'];
      if (!is_array($valid)) {
        $this->replace_args($valid, $args);
        $valid = replace_vars($valid, $predicate);
      }
    }
    else
      $valid = $this->replace_args($predicate, $args);
    if ($this->is($valid)) return true;
    $this->update_error($func, $args);
  }
  
  function replace_args(&$str, $args)
  {
    $i = 1;
    foreach($args as $arg) {
      $field = $this->fields[$arg];
      $name = isset($field)?$this->title($arg, $field): $arg;
      $str = str_replace('$'.$i, $name, $str);
      if (is_array($field))
        $str = str_replace('$v'.$i, $this->request[$arg], $str);
      ++$i;
    }
    return $str;
  }
  
  function update_error($func, $args, $error=null)
  {
    if ($error) return true;
    $predicate = &$this->predicates[$func];
    if (!is_array($predicate)) return false;
    if (!is_string($error)) $error = $predicate['error'];
    if (!isset($error)) return false;
    
    $error = str_replace('$value', $this->value, $error);
    $error = replace_vars($error, $predicate);
    $this->replace_args($error, $args);
    $this->error = replace_vars($error, $this->request);
    return false;
}
  
  function relate_time($now, $relation)
  {
    if ($relation == 'future' && $this->value <= $now)
      return false;

    if ($relation == 'past' && $this->value >= $now)
      return false;
    return true;
  } 

  function date($relation=null)
  {
    $result = $this->regex('/^\d{4}([-\/])(0\d|1[0-2])\1([0-2][0-9]|3[01])$/');
    if ($result !== true) return $result;
    return $this->relate_time(Date('Y-m-d'), $relation);
  }

  function time($relation=null)
  {
    $result = $this->regex('/^\d\d:\d\d(:\d\d)?$/');
    if ($result !== true) return $result;
    return $this->relate_time(Date('H:i'), $relation);
  }
  
  
  function datetime($relation)
  {
    $result = $this->regex('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(:(\d{2}))?/');
    if ($result !== true) return $result;
    return $this->relate_time(Date('Y-m-d H:i:s'), $relation);
  }
  
  function same_month($field)
  {
    return substr($this->value,0,8) == substr($this->request[$field], 0, 8);
  }

  function valid()
  {
    return !$this->error;
  }
   
  function sql()
  {
    $sql = implode(',', func_get_args());
    return $this->db->exists(replace_vars($sql, $this->request));
  }
    
}
