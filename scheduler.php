<?php

require_once('../common/qworker.php');
require_once('../common/log.php');


class schedule_item
{
  var $id;
  var $time;
  var $rate;
  var $seconds;
  var $status;
  var $type;
  var $size;
  var $user_id;
  var $arguments;
}

class scheduler_mgr_exception extends exception {};

class schedule_mgr extends qworker
{
  var $schedules;
  function __construct()
  {
    parent::__construct();
    $this->interval = $this->params['interval'];
    $this->schedules = array();
  }  
 
  function __destruct()
  {
    parent::__destruct();
  }
 
 
  function load()
  {
    global $db;
    $sql = "select id, status, schedule_time, user_id, type, size, arguments from mukonin_process.schedule
      where status in ('pend', 'busy') order by schedule_time, priority desc";
    $db->each($sql, function($row_index, $data) use (&$db) {
      $item = new schedule_item();
      $item->status = 'pend';
      list($item->id, $item->status, $item->time, $item->user_id, $item->type, $item->size, $item->arguments) = $data;
      $this->schedules[$item->id] = $item;
    });
  }
  
  function schedule($item)
  {
    global $db;
    $db->disconnect();
    db::connect_default();
    $db->exec("update mukonin_process.schedule set status = 'busy' where id = $id");
  
    $manger_args = array($item->user_id, $item->type, $item->size);
    $work_args = explode(' ', $item->arguments);
    array_walk($work_args, function(&$value, $index) { $value = urldecode($value); });
    $arguments = array_merge($manager_args, $work_args);
    $left = $item->size;
    $rate = $item->rate;
    $microseconds = $item->seconds * 1000000;
    do {
      $arguments[2] = min($left, $rate);
      call_user_func_array('qmanager::daemon_work', $arguments);
      $left -= $rate;
      usleep($microseconds);
      $db->exec("update mukonin_process.schedule set done = done - $rate where id = $id");
    } while ($left > 0);
   
    $db->exec("update mukonin_process.schedule set status = 'done' where id = $id");
    exit(0);
  }
  
  function dispatch()
  {
    $now = date('Y-m-d H:i:s');
    foreach ($this->schedules as $id=>$item) { 
      if ($item->time > $now) continue;
      $pid = pcntl_fork();
      switch ($pid) {
        case -1: 
          log::error("Could not fork!");
          break;
        case 0:
          unset($this->schedules[$id]);
          break;
        default:
          $this->schedule($item);
      }
     }
  }
  
  function start($type)
  {
    $this->load();
    
    $self = &$this;
    pcntl_alarm($this->interval);
    pcntl_signal(SIGALRM, function($signo) use (&$self){
     // if ($signo != SIGALRM) return; //should not be checking this: anyway just in case
      $self->dispatch();
      pcntl_alarm($self->interval);
    });
    
    parent::listen(function($user_id, $type, $start, $load, $options, $work_type, $size, $etc) use (&$self)  {
      $item = new schedule_item();
      $item->type = $work_type;
      $item->size = $size;
      $item->time = $options['time'];
      $item->rate = isset($options['rate'])? $options['rate']: $size;
      $item->seconds = isset($options['seconds'])? $options['seconds']: 1;
      $arguments = array_slice(func_get_args(),6);
      array_walk($arguments, function(&$value, $index) { $value = urlencode($value); });
      $item->arguments = implode(' ',$arguments);
      $sql = "insert into mukonin_process.schedule(user_id, schedule_time, type, size, arguments)
          values('$user_id', $schedule_time, '$type', $size, '$arguments')";
      global $db;
      $item->id = $db->insert($sql);
      $this->schedules[$item->id] = $item;
    });
  }
} 

$scheduler = new scheduler();
$scheduler->start();
?>  
