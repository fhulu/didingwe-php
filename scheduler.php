<?php

//08:00-17:00`
require_once('../common/qworker.php');
require_once('../common/log.php');

class scheduler_exception extends exception {};

class scheduler extends qworker
{
  function __construct()
  {
    parent::__construct();
    $this->interval = $this->params['interval'];
  }
  
 
  function __destruct()
  {
    parent::__destruct();
  }
 
  function start()
  {
    $self = &$this;
    pcntl_alarm($this->interval);
    pcntl_signal(SIGALRM, function($signo) use (&$self){
      if ($signo != SIGALRM) return; //should not be checking this: anyway just in case
      global $db;
      $sql = "select type, size, arguments from mukonin_process.schedule
        where status = 'pend' and schedule_time <= now() order by priority desc";
      $db->each($sql, function($row_index, $data) {
        list($type, $size, $arguments) = $data;
        $arguments = explode(' ', $arguments);
        array_walk($arguments, function(&$value, $index) { $value = urldecode($value) });
        call_user_func_array('qmanager::work', $arguments);
      });
      pcntl_alarm($this->interval);
    });
    
    parent::listen(function($provider_id, $type, $load, $user_id, $schedule_time, $options, $type, $size, $etc) use (&$self)  {
      //todo: parse options
      $arguments = array_slice(func_get_args(),6);
      array_walk($arguments, function(&$value, $index) { $value = urlencode($value) });
      $arguments = implode(' ',$arguments);
      $sql = "insert into mukonin_process.schedule(user_id, schedule_time, type, size, arguments)
          values('$user_id', '$schedule_time', '$type', $size, '$arguments')";
      global $db;
      $db->exec($sql);
    });
  }
} 

$scheduler = new scheduler();
$scheduler->start();
?>  
