<?php
require_once('db.php');
require_once 'errors.php';

class validator_exception extends Exception {};
class validator
{
  
  var $table;
  var $request;
  var $db;
  var $name;
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
    global $db;
    $this->db = is_null($conn)? $db: $conn;
    errors::init();
  }
 
  function error($msg)
  {
    $this->has_errors = true;
    if ($this->title[0] == '!') $msg = $this->title;
    log::warn($msg);
    global $errors;
    return $errors->add($this->name, substr($msg, 1));
  }
  

  function regex($regex, $title=null)
  {
    if (preg_match($regex, $this->value)) return true;
    if ($title == '') $title = "!Invalid $this->title.";
    return $this->error($title);
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
    return $this->regex('/^\d{2}((0[1-9])|(1[0-2]))(([012][1-9])|(3[01]))\d{7}$/');
  }
  
  function email()
  {
    return $this->regex('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i');
  }

  function int_tel()
  {
    return $this->at_least(10) && $this->regex('/^\+\d+$/',"!Please use international format for $this->title, e.g. +27821234567");
  }
  
  function national_tel()
  {
    return $this->at_least(8) && $this->regex('/^0\d+$/', "!Please use national format for $this->title, e.g. 0821234567");
  }

  function at_least($length)
  {
    if (strlen($this->value) >= $length) return true;
    return $this->error("!$this->title must contain at least $length characters.");
  }
  
  function numeric()
  {
    return $this->regex('/^\d+$/', "!$this->title must be numeric.");
  }
  
  function alphabetic()
  {
    return $this->regex('/^[a-zA-Z]+$/', "!$this->title must be alphabetic.");
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
    return $this->error("!$this->title do not match.");
  }
  
  function password($min_length)
  {
    if ($min_length == 0) $min_length=6;
    $title = "!$this->title must contain at least:
      <li>one upper case letter
      <li>one lower case letter
      <li>one number or special character
      <li>at least $min_length characters";
    return $this->regex('/(?=^.{'.$min_length.',}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/', $title);
  }

  function extension($ext)
  {
    return $this->regex('/\.'.$ext.'/i', "!$this->title must be of type $ext");
  }
  
  function find_in($table)
  {
    if ($table == '') {
      $table = $this->table;
      if ($table == '')
        throw new validator_exception('checking for existence with no db table supplied');
    }
   
    $pos = strrpos($table, '.');
    if ($pos === false) {
      $field = $this->name;
    }
    else {
      $field = substr($table,$pos+1);
      $table = substr($table,0, $pos);
    }
    $db = $this->db;
    $value = addslashes($this->value);
    return $db->exists("select $field from $table where $field = '$value'");
  }

  function in($table=null)
  {
    if (!$this->find_in($table))
      return $this->error("!No such $this->title found.");
    return true; 
  }
  
  function exist($table=null)
  {
    return $this->in();
  }

  function unique($table=null)
  {
    if ($this->find_in($table))
      return $this->error("!$this->title already exist.");
    return true; 
  }
  
  function check($name, $title=null)
  {
    if (is_null($title)) {
      $title = str_replace('_', ' ', $name);
      $title = ucwords($title);
    }
    $this->name = $name;
    $this->title = $title;
    $this->value = $this->request[$name];
    return $this;
  }
  
  function is()
  {
    $funcs = func_get_args();
    log::debug("VALIDATE $this->name=$this->value ".implode(',',$funcs));
    if ($this->value == '') {
      if (in_array('optional', $funcs)) return true;      
      return $this->error("!$this->title must be provided.");
    }
    foreach($funcs as $func) {
      if ($func == 'optional') continue;
      if (is_numeric($func)) {
        if (!$this->at_least($func)) return false;
        continue;
      }
      if ($func[0] == '/') {
        if (!$this->regex($func)) return false;
        continue;
      } 
      $matches = array();
      if (!preg_match('/^([a-z_]+)(?:\((.*)\))*$/i', $func, $matches)) 
        throw new validator_exception("Invalid validator expression $func!");

      $func = $matches[1];
      $arg = $matches[2];
     
      if (!method_exists($this, $func)) 
        throw new validator_exception("validator method $func does not exists!");
        
      if (!$this->{$func}($arg)) return false;
    }
    return true;
  }
  
  function date($type)
  {
    if (!$this->regex('/^\d{4}([-\/])(0\d|1[0-2])\1([0-2][0-9]|3[01])$/')) return false;
    $today = Date('Y-m-d');
    if ($type == 'future' && $this->value < $today)
      return $this->error("!$this->title must be in the future");
      
    if ($type == 'past' && $this->value >= $today)
      return $this->error("!$this->title must be in the past");    
    return true;      
  }
  
  
  function valid()
  {
    return !$this->has_errors;
  }
   
};

?>
