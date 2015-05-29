<?php
require_once('db.php');
require_once 'curl.php';

class validator_exception extends Exception {};
class validator
{
  
  var $table;
  var $request;
  var $db;
  var $name;
  var $last_name;
  var $title;
  var $value;
  var $error_cb;
  var $optional;
  var $has_errors;
  function __construct($request=null, $table=null, $conn=null)
  {
    $this->table = $table;
    $this->request = is_null($request)? $_REQUEST: $request;
    $this->optional = false;
    $this->has_errors = false;
    $this->output_errors = true;
    global $db;
    $this->db = is_null($conn)? $db: $conn;
  }
 
  function error($msg)
  {
    $this->has_errors = true;
    if ($this->title[0] == '!') $msg = substr($this->title,1);
    page::error($this->name, $msg);
}
  

  function regex($regex, $title=null)
  {
    if (preg_match($regex, $this->value)) return true;
    return ($title == null)? $title = "!Invalid $this->title.": $title;
  }
  
  function country_code()
  { 
    return $this->in('mukonin_contact.country.code');
  }
  
  function name()
  {
    return $this->regex('/^\w{2}[\w\s]*$/i');
  }

  function za_code()
  {
    return $this->regex('/^\d{4}$/');
  }
  
  function za_company_reg()
  {
    $year = date('Y');
    if ((int)$this->value > $year) return $this->error("!Invalid $this->title."); 
    return $this->regex('/^(19|20)[0-9]{2}\/?[0-9]{6}\/?[0-9]{2}$/');
  }
  
  function za_tax_ref()
  {
    return $this->regex('/^\d{10}$/');
  }
  
  function za_vat()
  {
    return $this->regex('/^\d{10}$/');
  }

  function za_id()
  {
    return $this->regex('/^\d{2}((0[1-9])|(1[0-2]))((0[1-9])|([12][0-9])|(3[01]))\d{7}$/');
  }
  
  function email()
  {
    return $this->regex('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i');
  }

   function url($option)
  {
    $result = $this->regex('/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/');
    if ($result !== true) return $result;
    
    if ($option == 'visitable') {
      $curl = new curl();
      if (!$curl->read($this->value,512)) return $this->error("$this->title $this->value is not accessible.");
    }
    return true;
  }
  
  function int_tel()
  {
    return $this->at_least(12,'digits') && $this->regex('/^\+\d+$/',"!Please use international format for $this->title, e.g. +27821234567");
  }
  
  function national_tel()
  {
    return $this->at_least(10,'digits') && $this->regex('/^0\d+$/', "!Please use national format for $this->title, e.g. 0821234567");
  }

  function telephone()
  {
    return $this->at_least(10, 'digits') && $this->regex('/^[\+0]\d+$/');
  }
  
  function provided()
  {
    if ($this->value != '') return true;
    return "$this->title must be provided.";
  }
  
  function optional($option, $option_title)
  {
    if ($this->value != '') return true;
    if ($option == '') return true;
    if ($this->request[$option] != '') return true;
    if ($option_title == '') $option_title = validator::title($option);
    return "!Either $this->title or $option_title must be provided";
  }
  
  function at_least($length, $units='characters')
  {
    if (strlen($this->value) >= $length) return true;
    return "$this->title must contain at least $length $units.";
  }
  
  function digits()
  {
    return $this->regex('/^\d+$/', "$this->title must only contain digits");
  }
  function numeric()
  {
    return $this->regex('/^-?\d+(\.\d+)?$/', "$this->title must be numeric");
  }
  function decimal()
  {
    return $this->regex('/^-?\d+\.\d+$/', "$this->title must be decimal");
  }
  function alphabetic()
  {
    return $this->regex('/^[a-zA-Z]+$/', "$this->title must be alphabetic");
  }
  
  function proc()
  {
    return $this->regex('/^([a-z_]+)(?:\((.*)\))*$/');
  }
  
  function money()
  {
    return $this->regex('/^\d+(\.\d+)?$/');
  }
  
  function match($name)
  {
    if ($this->value == $this->request[$name]) return true;
    return "$this->title do not match.";
  }
  
  function matches($name)
  {
    return $this->match($name);
  }
  
  function equals($value, $title=null) 
  {
    $value = trim($value);
    if ($this->value == $value || is_numeric($value) && (int)$this->value == $value) return true;
    return $title==null?"$this->title must equal $value":$title;
  }
  function less($name, $title=null)
  {
    $value = $this->request[$name];
    if (is_numeric($name)) $value = $title = $name; 
    if ($this->value < $value) return true;
    if (is_null($title)) $title = $this->title($name);
    return "$this->title must be less than $title ($value)";
  }
  
  function less_equal($name, $title=null)
  {
    $value = $this->request[$name];
    if (is_numeric($name)) $value = $title = $name; 
    if ($this->value <= $value) return true;
    if (is_null($title)) $title = $this->title($name);
    return "$this->title must not be greater than $title ($value)";
  }
  
  function greater($name, $title=null)
  {
    $value = $this->request[$name];
    if (is_numeric($name)) $value = $title = $name; 
    if ($this->value > $value) return true;
    if (is_null($title)) $title = $this->title($name);
    return "$this->title must be greater than $title ($value)";
  }
  
  function greater_equal($name, $title=null)
  {
    $value = $this->request[$name];
    if (is_numeric($name)) $value = $title = $name; 
    if ($this->value >= $value) return true;
    if (is_null($title)) $title = $this->title($name);
    return "$this->title must not be less than $title ($value)";
  }
  
  function password($min_length=6)
  {
    $title = "$this->title must contain at least:
      <li>one upper case letter
      <li>one lower case letter
      <li>one number or special character
      <li>at least $min_length characters";
    return $this->regex('/(?=^.{'.$min_length.',}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/', $title);
  }

  function extension($ext)
  {
    return $this->regex('/\.'.$ext.'/i', "$this->title must be of type $ext");
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
    $db = $this->db;
    $value = addslashes($this->value);
    $exists = $db->exists("select $field from $table where $field = '$value'");
    if ($exists) return true;
    return "No such $this->title found.";
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


  function unique($table=null)
  {
    if ($this->in($table) !== true) return true;
    return "$this->title already exists.";
  }
  
  function either()
  {
    $args = func_get_args();
    if (in_array($this->value, $args)) return true;
    return "$this->title must be either one of ".implode(',', $args);
  }
  
  function depends($field, $arg)
  {
    if ($field == 'this') $field = $this->name;
    $validator = new validator($this->request, $this->table, $this->db);
    $validator->output_errors = false;
    log::debug("DEPENDS $field $arg");
    $validator->check($field);
    list($method) = explode('(',$arg);
    if (method_exists($this,$method))
      return $validator->is($arg);
    return $validator->equals($arg);
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
  
  static function title($name)
  {
    return ucwords(str_replace('_', ' ', $name));
  }
  
  static function is_static_method($name)
  {
    return strpos($name, '::') !== false;
  }
  
  function check($name, $title=null)
  {
    if (is_null($title)) $title = validator::title($name);
    $this->name = $name;
    $this->title = $title;
    $this->value = trim(at($this->request,$name));
    if ($this->last_name != $name)
      $this->optional = false;
    $this->last_name = $name;
    return $this;
  }
  
  function titled($title)
  {
    $this->title = $title;
    return $this;
  }
  
  function is()
  {
    $funcs = func_get_args();
    log::debug("VALIDATE $this->name=$this->value FUNCTIONS:".implode(',',$funcs));
  
   // validate each argument
    $result = true;
    $this->optional |= in_array('optional', $funcs);
    if ($this->optional && $this->value == '') return true;
    foreach($funcs as $func) {
      $args = array($func);
      if ($func == 'optional') continue;
      if (is_numeric($func)) 
        $func = 'at_least';
      else if ($func[0] == '/') 
        $func = 'regex';
      else {
        $matches = array(); 
        log::debug("VALIDATE FUNC $func");
        if (!preg_match('/^([^\(]+)(?:\((.*)\))?/', $func, $matches)) 
          throw new validator_exception("Invalid validator expression $func!");

        $func = $matches[1];
        $args = array();
        preg_match_all('/[^,]+\(.*\)|[^,]+/', $matches[2], $args);
        $args = $args[0];
      }
      
      if (validator::is_static_method($func)) {
        array_unshift($args, $func);
        $func = 'call';
      }
      else if (!method_exists($this, $func)) 
        throw new validator_exception("validator method $func does not exists!");
      
      if ($func != 'depends') $result = $this->provided();
      if ($result === true)
        $result = call_user_func_array(array($this, $func), $args); 
      if ($result === true) continue;
      if ($func == 'depends' || $result === false) return false;
      return $this->output_errors?$this->error($result):false;
    }
    return true;
  }
 
  function relate_time($now, $relation)
  {
    if ($relation == 'future' && $this->value <= $now)
      return "$this->title must be in the future";

    if ($relation == 'past' && $this->value >= $now)
      return "$this->title must be in the past";
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
  
  function same_month($field, $title=null)
  {
    if (substr($this->value,0,8) == substr($this->request[$field], 0, 8)) return true;
    if (is_null($title)) $title = validator::title($field);
    return "$this->title must be in the same month as $title";
  }

  function valid()
  {
    return !$this->has_errors;
  }
   
  function report($name, $message)
  {
    $this->check($name);
    return $this->error($message);
  }
};

?>
