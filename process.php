<?php
require_once('log.php');

$daemon_mode = true;
require_once('session.php');

class process_entry
{
  var $session;
  var $method;
  var $env;
  var $options;
  var $arguments;
}

class process_exception extends Exception {};
 
class process
{
  var $handle;
  var $reaping;
  function __construct($log_level)
  {
    log::init("process", $log_level);
    $this->handle = process::get_handle();
    msg_remove_queue($this->handle);
    $this->handle = process::get_handle();
    $this->reaping = false;
  }

  static function start_daemon()
  {
    // Run process as as daemon - ubuntu does not handle using '&' for daemons properly
    // To run a daemon properly one has to $ php process.php </dev/null &
    $pid = pcntl_fork();
    if ($pid != 0) exit(0);
  }
  
  static function init($log_level = log::INFO)
  {
    process::start_daemon();
      
    $process = new process($log_level);  
    $msg = $process->listen();
    $process->dispatch($msg);
  }
  
  static private function get_handle()
  {
    $key = ftok(__FILE__,'k');
    return msg_get_queue($key, 0660);
  }
  
  static function send($method, $arguments, $options)
  {
    $msg = new process_entry;
    global $session;
    $msg->session = serialize($session);
    $msg->method = $method;
    $msg->options = $options;
    log::init("process", log::TRACE);
    log::info("Queued $method");
    $msg->arguments = $arguments;
    if (!msg_send(process::get_handle(), 1, $msg))
      throw new process_exception("Unable to queue message. Process daemon may not be running.");
  }
  
  static function q($method)
  {  
     process::send($method, array_slice(func_get_args(), 1), 'q');
  }
  
  static function start($method)
  {
     process::send($method, array_slice(func_get_args(), 1), 'start');
  }
  
  static function restart($log_level)
  {
    process::q('restart', $log_level);
  }
  
  static function fork($method)
  {
    log::info("Received method $method");
    $pid = pcntl_fork();
    if ($pid != 0) {
      if ($pid == -1) log::error("Unable to fork");
      return;
    }
    list($class, $function) = explode('::', $method);      
    require_once($class.'.php');

   // try {
      call_user_func_array($method, array_slice(func_get_args(), 1));
   /* } 
    catch (Exception $e) {
      log::error("UNCAUGHT EXCEPTION: ". $e->getMessage() );
    } */ 
  }

  static function test() { echo "TEST this"; }
  
  static function call($method)
  {
    list($class, $function) = explode('::', $method);      
    require_once($class.'.php');

    return call_user_func_array($method, array_slice(func_get_args(), 1));
  }
 
  static function call_byref1($method, &$arg)
  {
    list($class, $function) = explode('::', $method);
    require_once($class.'.php');

    return call_user_func($method, $arg);
  }
 
  private static function zombie_reaper($signal)
  {
    log::debug("Reaped zombie child");
  }
  
  function listen()
  {
    while(1) {
      log::debug("Waiting for a message handle=$this->handle");
      $msg = new process_entry();
      $type = 1;
      $error_code = 0;
      if (!msg_receive($this->handle, 1, $type, 2048, $msg, true, 0, $error_code)) {
        log::error("Failed to receive message with error $error_code");
        continue;
      }
      log::info("Received $msg->method");
      $pid = pcntl_fork();
      if ($pid == -1) 
        log::error("Could not fork");
      else if ($msg->method == 'restart') {
        if ($pid != 0) {
          log::info("About to restart process");
          pcntl_exec('/usr/bin/php', array_merge(array('call.php', 'process::init'), $msg->arguments, $msg->env));
        }
        exit(0);
      }
      else if ($pid == 0)
        break;
      log::info("Forked child process with pid $pid");
      $pid = pcntl_fork();
      if ($pid == -1) 
        log::error("Could not fork");
      if ($pid != 0) {
        exit(0);
       }
      log::info("Now running with child process with pid " . getmypid());
    } 
    return $msg;
  }
  
  function dispatch($msg)
  {
    global $session;
    $session = unserialize($msg->session);
    $user = $session->user;
    log::debug("DISPATCH: method: $msg->method user: $user->id partner: $user->partner_id");      
    $method = $msg->method;
    list($class, $function) = explode('::', $method);
    db::connect_default();
    require_once($class.'.php');
    global $logger;
    //log::init($class, $logger->level);
    try {
      call_user_func_array($method, $msg->arguments);
    } 
    catch (Exception $e) {
      log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
    } 
  }  
}

?>
