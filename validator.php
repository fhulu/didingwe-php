<?php
require_once('db.php');

class validator
{
  const UNIQUE = 0x0001;
  const EXISTS = 0x0002;
  const OPTIONAL = 0x0004;
  
  var $table;
  var $request;
  var $db;
  function __construct($table, $request=null, $conn=null)
  {
    $this->table = $table;
    $this->request = is_null($request)? $_REQUEST: $request;
    global $db;
    $this->db = is_null($conn)? $db: $conn;
  }
  
  function unique($name, $title, $regex=null, $options=0, $alt_name=null)
  {
    return $this->valid($name, $title, $regex, $options | self::UNIQUE, $alt_name);
  }
  
  function exists($name, $title, $regex=null, $options=0, $alt_name=null)
  {
    return $this->valid($name, $title, $regex, $alt_name, $options | self::EXISTS);
  }
  
  function valid($name, $title, $regex, $options, $alt_name)
  {
    $val = $this->request[$name];
    log::debug("VALIDATE $name=$val $title $regex $options $alt_name");
    if ($val == '') {
      if ($options & self::OPTIONAL) return true;
      if (is_null($alt_name)) {
        echo "!You must supply $title.";
        return false;
      }
      $val = $this->request[$alt_name];
    }
    else if (!is_null($regex) && !preg_match($regex, $val)) {
      echo "!Invalid $title.";
      return false;
    }
    
    if ($options & (self::UNIQUE | self::EXISTS)) {
      $db = $this->db;
      $exists = $db->exists("select * from $this->table where $name = '$val'");
      if ($exists && $options & self::UNIQUE ) {
         echo "!$title already exist.";
         return false;
      }
      if (!$exists && $options & self::EXISTS ) {
        echo "!No such $title found.";
        return false;
      }
    }
    return true;      
  }
}
?>