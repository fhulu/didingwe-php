<?php
require_once('log.php');
$daemon_mode = true;
require_once('session.php');
require_once('db.php');

class qmessage
{
  var $session;
  var $method;
  var $env;
  var $options;
  var $arguments;
}

class qworker_info
{
  var $name;
  var $type;
  var $id;
  var $provider_id;
  var $msg_handle;
  var $capacity;
  var $load;
  var $completed;
}

class qmanager_exception extends Exception {};
 
class qmanager
{
  var $handle;
  var $workers;
  var $worker_ids;
  function __construct($log_level)
  {
    log::init("qmanager", $log_level);
    $this->handle = qmanager::get_handle(true);
    msg_remove_queue($this->handle);
    $this->handle = qmanager::get_handle();
    $this->workers = array();
    $this->worker_ids = array();
  }

  static function start_daemon()
  {
    global $db;
    $db_was_connected = !is_null($db) && $db->connected();
    if ($db_was_connected) $db->disconnect();
    $pid = pcntl_fork();
    if ($pid != 0) exit(0);
    if ($db_was_connected) db::connect_default();
  }
 
  static function get_handle()
  {
    return msg_get_queue(1000,0666);
  }
  static function init($log_level = log::INFO)
  {
    qmanager::start_daemon();
    $qmanager = new qmanager($log_level);  
    $msg = $qmanager->listen();
    
    $qmanager->dispatch($msg);
  }
  
  static function send($method, $arguments, $options='q')
  {
    $msg = new qmessage;
    global $session;
    $msg->session = serialize($session);
    $msg->method = $method;
    $msg->options = $options;
    log::debug("Queing $method(" . implode(',',$arguments) . ')');
    $msg->arguments = $arguments;
    if (!msg_send(qmanager::get_handle(), 1, $msg))
      throw new qmanager_exception("Unable to queue message. qmanager daemon may not be running.");
    log::info("Queued $method(" . implode(',',$arguments) . ')');
  }
  
  static function q($method)
  {  
    qmanager::send($method, array_slice(func_get_args(), 1), 'q');
  }
  
  static function start($options, $filter='')
  {
    qmanager::send('start', func_get_args(), 'q');
  }

  static function stop($options, $filter='')
  {
    qmanager::send('stop', func_get_args(), 'q');
  }
  
  static function restart($options, $filter='')
  {
    qmanager::send('stop', func_get_args(), 'q'); 
    sleep(5);
    qmanager::send('start', func_get_args(), 'q');
  }
  
  static function get_provider($user_id, $type)
  {
    global $db;
    db::connect_default();
    $provider_id  = $db->read_one_value(
      "select pr.id from mukonin_process.provider pr, mukonin_process.group_provider gp
        ,mukonin_audit.partner pa,mukonin_audit.user u 
      where pr.id = gp.provider_id and  (pa.routing_group_id = gp.group_id or gp.group_id = 0) and gp.type = '$type' 
        and u.partner_id = pa.id and u.id = $user_id order by gp.group_id desc");
    if (is_null($provider_id)) 
      log::error("Unable to route work of type $type for user $user_id");
    return $provider_id;
  }

  static function daemon_work($user_id, $type, $load, $parameters )
  {
    $provider_id  = qmanager::get_provider($user_id, $type);  
    if (is_null($provider_id)) return false;
    $arguments = func_get_args();
    $arguments[0] = $provider_id;
    qmanager::send('work', $arguments, 'q');
    return true;
  }
  
  static function work($type, $load, $parameters )
  {
    global $session;
    $provider_id  = qmanager::get_provider($session->user->id, $type);  
    if (is_null($provider_id)) return false;
    $arguments = array_merge(array($provider_id),func_get_args());
    qmanager::send('work', $arguments, 'q');
    return true;
    
  }
  
  // e.g. schedule_time, options, 'sms', size, etc)//
  static function schedule($schedule_time, $options, $type, $size, $etc )
  {
    global $db, $session;
    $user = $session->user;
    call_user_func_array('qmanager::work', array_merge(array('schedule', 1, $user->id), func_get_args()));
  }
  
  static function complete($type, $load, $parameters)
  {
    qmanager::send('complete', func_get_args(), 'q');
  }
  
  function listen()
  {
    while(1) {
      log::debug("Waiting for a message handle=$this->handle");
      $msg = new qmessage();
      $type = 1;
      $error_code = 0;
      $wait_status = 0;
      pcntl_wait($status, WNOHANG|WUNTRACED);
      if (!msg_receive($this->handle, 1, $type, 10240, $msg, true, 0, $error_code)) {
        log::error("Failed to receive message with error $error_code");
        continue;
      }

      if ($msg->method == 'start') {
        $this->started($msg);
      }
      else if ($msg->method == 'register') {
        $this->registered($msg);
      }
      else if ($msg->method == 'work') {
        $this->distribute($msg);
      }
      else if ($msg->method == 'stop') {
        $this->stopped($msg);
      }
      else if ($msg->method == 'complete') {
        $this->completed($msg);
      }
      else {
        global $db;
        if (!is_null($db)) $db->disconnect();
        $pid = pcntl_fork();
        if ($pid == -1) 
          log::error("Could not fork");
        else if ($pid == 0)  {
          break;
        }
      }
    } 
    return $msg;
  }
  
  
  function dispatch($msg)
  {
    global $session;
    $session = unserialize($msg->session);
    $user = $session->user;
    log::debug("DISPATCH: method: $msg->method user: $user->id partner: $user->partner_id");      
    db::connect_default();
    try {
     qmanager::call($msg->method, $msg->arguments);
    } 
    catch (Exception $e) {
      log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
    } 
  } 

  static function call($function, $arguments)
  {
    list($class, $method) = explode('::', $function);
    require_once($class.'.php');
    if (!is_callable($function)) throw new qmanager_exception("Unable to call method $function");
    call_user_func_array($function, $arguments);
  }
  
  static function get_control_sql($msg)
  {
    list($option, $filter) = $msg->arguments;
    $sql = "select w.id, p.id, w.type, w.name, p.program, w.allocation 
          from mukonin_process.worker w, mukonin_process.provider p
          where w.provider_id = p.id and w.enabled = 1 and p.enabled = 1";
    if ($option == '-p')
      $sql .= " and p.name like '%$filter%'";
    else if ($option == '-t')
      $sql .= " and t.type like '%$filter%'";
    else if ($option != 'all' || $option == '-n' )
      $sql .= " and w.name like '%$option%'";
    return $sql;
  }
  
  function started($msg)
  {
    //todo: allow for multiple number of the same worker type
    //todo: check that we don't exceed number of instances allowed
    list($option, $filter) = $msg->arguments;
    db::connect_default();
    global $db;   
    $sql = qmanager::get_control_sql($msg);   
    
    $self = $this;
    $db->each($sql, function($index, $row) use (&$self) {
      $info = new qworker_info;
      list($info->id, $info->provider_id, $info->type, $info->name, $program, $info->capacity) = $row;
      if (isset($self->workers[$info->type][$info->id])) {
        log::warn("Worker $info->name($program) already started");
        return;
      }
        $info->msg_handle = msg_get_queue($info->id, 0660);
      $self->workers[$info->type][$info->id] = $info;
      log::info("Starting $program $info->name $info->id $info->provider_id $info->capacity");
      exec("$program $info->name $info->id $info->provider_id >/dev/null 2>&1 &");
      //todo: use signal handler to test when executed program dies
    });    
    
  }
  
  function registered($msg)
  {
  }
  
  function stopped($msg)
  {
    list($option, $filter) = $msg->arguments;
    db::connect_default();
    global $db;
    $sql = qmanager::get_control_sql($msg);
    $self = $this;
    $db->each($sql, function($index, $row) use (&$self, $msg) {
      $info = new qworker_info;
      list($info->id, $info->provider_id, $info->type, $info->name, $program) = $row;
      if (!isset($self->workers[$info->type][$info->id])) return;
     
      log::info("Stopping $program $info->name");
      unset($self->workers[$info->type][$info->id]);
      $info->msg_handle = msg_get_queue($info->id, 0660);
      if (!msg_send($info->msg_handle, 1, $msg))
        log::error("Unable to send message to $info->name");
    });
  
    if ($option == 'all') exit(0);
  }

  function distribute($msg)
  {
    list($provider_id, $type, $required, $arguments) = $msg->arguments;
    do {
      $most_available = 0;
      $best_info = null;
      foreach($this->workers[$type] as $info) {
        if ($info->provider_id != $provider_id ) continue;
        $available = $info->capacity - $info->load;
        if ($available >= $most_available) {
          $best_info = $info;
          $most_available = $available;
        }
      }
      
      if ($best_info === null) {
        //todo: process unprocessed items when worker becomes available
        log::error("No worker of type $type available to process the work, please dispatch additional workers");
        break;
      }
      $load = min($required, $most_available);
      $msg->arguments[2] = $load;
      $handle = msg_get_queue($best_info->id, 0660);
      msg_send($handle, 1, $msg);
      $required -= $load;
    } while ($required > 0);
  }
  
  function completed($msg)
  {
    list($id, $type, $load, $callback) = $msg->arguments;
    log::debug("Completed TYPE $type ID $id LOAD $load CALLBACK $callback: Arguments $msg->arguments");
    qmanager::q($callback, array_slice($msg->arguments,4));
    
  }
  
}

