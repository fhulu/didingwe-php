<?php

require_once('db.php');
require_once('qmanager.php');

class qworker extends qworker_info
{
  var $max_msg_size;
  var $errors;
  var $successes;
  var $params;
  function __construct($max_msg_size=2048) 
  {   
    global $argv;
    
    $this->name = $argv[1];
    $this->id = $argv[2];
    $this->provider_id = $argv[3];
    $this->max_msg_size = $max_msg_size;
 
    $this->msg_handle = msg_get_queue($this->id, 0660);
    global $db;
    list($log_level, $this->type, $this->capacity) 
      = $db->read_one("select log_level, type, allocation from mukonin_process.worker where id = $this->id");
    log::init($this->name, (int)$log_level);
    $this->params = qworker::read_params($this->id, 'worker');
    $this->provider_params = qworker::read_params($this->provider_id, 'provider');
  }
  
  
  function __destruct()
  {
    msg_remove_queue($this->msg_handle);
  }
  
  
  static function read_params($id, $type)
  {
    $sql = "select name, value from mukonin_process.parameter where type = '$type' and parent_id = $id";
    $values = array();
    global $db;
    $db->each($sql, function($index, $row) use (&$values) {
      list($name, $value) = $row;
      $values[$name] = $value;
    });
    return $values;
  }
  
  function listen($callback)
  {
    while(1) {
      log::debug("Waiting for a work");
      $error_code = 0;

      if (!msg_receive($this->msg_handle, 1, &$type, $this->max_msg_size, &$msg, true, 0, &$error_code)) {
        log::error("Failed to receive message with error $error_code");
        continue;
      }
      log::debug("Received $msg->method(" . implode(',',$msg->arguments) . ')');
  
      list($type, $load, $arguments) = $msg->arguments;
      if ($msg->method == 'restart')
        ;//todo: fork to restart
      else if ($msg->method == 'stop') {
        log::debug('Received stop');
        break;
      }
      else {
        call_user_function_array($callback, $msg->arguments);
        qmanager::completed($type, $this->id, $load);
      }
    } 
    
  }
}

if ($argc < 3) {
  log::error("Not enough arguments supplied for qworker");
  exit(0);
}


?>