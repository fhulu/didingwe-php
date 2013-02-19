<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of error_reporter
 *
 * @author Mampo
 */
class errors {
  var $list;
  var $reported;
  var $how;
  function __construct($how='errors::json_out')
  {
    $this->reported = false;
    $this->list = array();
    $this->how = $how;
  }
  
  function __destruct()
  {
    $this->report();
  }
 
  function add($name, $error)
  {
    $this->list[$name] = $error;
    return false;
  }
  
  function json()
  {
    return json_encode($this->list);
  }
  
  static function json_out($list)
  {
    if (sizeof($list) == 0) 
      echo json_encode($list);
    else
      echo json_encode(array("errors"=>$list));
  }
  
  function report($how = null)
  {
    if ($how == null) $how = $this->how;
    call_user_func($how, $this->list);
  }
  
  static function init()
  {
    global $errors;
    if (is_null($errors)) $errors = new errors();
  }
}

$errors = null;
?>
