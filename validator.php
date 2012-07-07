<?php
require_once('db.php');

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
  function __construct($request=null, $table=null, $conn=null, $error_cb='validator::cout')
  {
    $this->error_cb = $error_cb;
    $this->table = $table;
    $this->request = is_null($request)? $_REQUEST: $request;
    
    global $db;
    $this->db = is_null($conn)? $db: $conn;
  }
  
  function error($msg)
  {
    if (is_null($this->error_cb)) return false;
    if ($this->title[0] == '!') $msg = $this->title;
    log::warn($msg);
    return call_user_func($this->error_cb, $msg);
  }
  
  static function cout($msg)
  {
    echo $msg;
    return false;
  }

  function regex($regex, $title=null)
  {
    if (preg_match($regex, $this->value)) return true;
    if ($title == '') $title = "!Invalid $this->title.";
    return $this->error($title);
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
    return $this->regex('/^(19|20)[0-9]{2}\/?[0-9]{6}\/?[0-9]{2}$/');
  }
  
  function za_tax_ref()
  {
    return $this->regex('/^\d{9}$/');
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

  function exist()
  {
    if ($this->table == '') 
      throw new validator_exception('checking for existance with no db table supplied');
   
    $db = $this->db;
    if ($db->exists("select * from $this->table where $this->name = '$this->value'")) return true;
    return $this->error("!No such $this->title found.");
  }

  function unique()
  {
    $cb = $this->error_cb;
    $this->error_cb = null;
    if (!$this->exist()) {
      $this->error_cb = $cb;
      return true;
    }
    $this->error_cb = $cb;
    return $this->error("!$this->title already exist.");
  }
  
  function is($name, $title)
  {
    $this->name = $name;
    $this->title = $title;
    $this->value = $this->request[$name];
    $funcs = array_slice(func_get_args(), 2);
    log::debug("VALIDATE $name=$this->value $title ".implode(',',$funcs));
    if ($this->value == '') {
      if (in_array('optional', $funcs)) return true;      
      return $this->error("!$title must be provided.");
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
        throw new validator_exception('Invalid validator expression $func!');

      $func = $matches[1];
      $arg = $matches[2];
     
      if (!method_exists($this, $func)) 
        throw new validator_exception('validator method $func does not exists!');
        
      if (!$this->{$func}($arg)) return false;
    }
    return true;
  }
}
?>