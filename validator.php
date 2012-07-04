<?php
require_once('db.php');

class validator_exception extends Exception {};
class validator
{
  const UNIQUE = 0x0001;
  const EXISTS = 0x0002;
  const OPTIONAL = 0x0004;
  
  var $table;
  var $request;
  var $db;
  var $name;
  var $title;
  var $min_length;
  var $value;
  function __construct($request=null, $table=null, $conn=null)
  {
    $this->table = $table;
    $this->request = is_null($request)? $_REQUEST: $request;
    global $db;
    $this->db = is_null($conn)? $db: $conn;
  }
  

  function regex($regex)
  {
    if (preg_match($regex, $this->value)) return true;
    echo $this->title[0] == '!'? $this->title: "!Invalid $this->title.";
    return false;
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
    if ($this->title != '') $this->title = "!Please use international format for $this->title, e.g. +27821234567";
    return $this->regex('/^\+\d{10,}$/');
  }
  
  function local_tel()
  {
    if ($this->title != '') $this->title = "!Please use local format for $this->title, e.g. +0821234567";
    return $this->regex('/^0\d{9,}$/');
  }

  function at_least()
  {
    if (strlen($this->value) >= $this->min_length) return true;
    if ($this->title != '') $this->title = "!$this->title must contain at least $this->min_length characters." ;
    echo $this->title;
    return false;
    
    return $this->regex('/^.{'.$this->min_length.',}/');
  }
  
  function numeric()
  {
    if ($this->title != '') $this->title == "!$title must be numeric." ;
    return $this->regex('/^\d+$/');
  }
  
  function alphabetic()
  {
    if ($this->title != '') $this->title == "!$title must be numeric." ;
    return $this->regex('/^\w+$/');
  }
  
  function match($name1, $name2, $title)
  {
    $val = $this->request[$name1];
    if ($val == '' && $allow_blank || $val == $this->request[$name2]) return true;
    echo $title[0] == '!'? $title: "!$title do not match.";
    return false;
  }
  
  function not_match($name1, $name2, $title1, $title2)
  {
    if ($this->request[$name1] != $this->request[$name2]) return true;
    echo $title1[0] == '!'? $title1: "!$title1 cannot be the same as $title2.";
    return false;
  }
  
  function password()
  {
    if ($this->title=='') {
      $$this->title = "!Passwords must contain at least:
        <li>one upper case letter
        <li>one lower case letter
        <li>one number or special character
        <li>$this->min_length characters";
    }
    return $this->regex('/((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/');
  }

  function exist($report=true)
  {
    if ($this->table == '') 
      throw new validator_exception('checking for existance with no db table supplied');
   
    $db = $this->db;
    if ($db->exists("select * from $this->table where $name = '$val'")) return true;
    if (!$report) return false;
    echo $this->title[0] == '!'? $this->title:"!No such $title found.";
    return false;
  }

  function unique()
  {
    if (!exist(false)) return true;
    echo $title[0] == '!'? $title: "!$title already exist.";
    return false;
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
      echo $title[0] == '!'? $title: "!$title must be provided.";
    }
    foreach($funcs as $func) {
      if ($func == 'optional') continue;
      if (is_numeric($func)) {
        $this->min_length = $func;
        if (!$this->at_least($func)) return false;
      }
      else if (method_exists($this, $func)) {
        if (!$this->{$func}()) return false;
      }
      else 
        throw new validator_exception('validator method $func does not exists!');
    }
    return true;
  }
}
?>