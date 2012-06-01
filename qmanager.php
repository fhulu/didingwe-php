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

class provider_info
{
  var $name;
  var $type;
  var $id;
  var $provider_id;
  var $msg_handle;
  var $capacity;
  var $workers;
}

class qworker_info extends provider_info
{
  var $pid;
  var $start_time;
  var $last_time;
  var $load;
  var $completed;
}


class qworkers
{
  var $type;
  var $msg_key;
  var $max_instances;
  var $workers;
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
    return msg_get_queue(1000,0660);
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
    log::info("Queued $method(" . implode(',',$arguments) . ')');
    $msg->arguments = $arguments;
    if (!msg_send(qmanager::get_handle(), 1, $msg))
      throw process_exception("Unable to queue message. qmanager daemon may not be running.");
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
    qmanager::send('work', $arguments, 'q');
    return true;
  }
  
  static function work($type, $load, $parameters )
  {
    global $session;
    $arguments = array_merge(array($session->user_id),func_get_args());
    qmanager::send('work', $arguments, 'q');
    return true;
    
  }
  
  // e.g. options, 'sms', size, etc)//
  static function schedule($options, $type, $size, $etc )
  {
    global $db, $session;
    $user = $session->user;
    call_user_func_array('qmanager::work', array_merge(array('schedule', 1, $user->id), func_get_args()));
  }
  
  static function daemon_schedule($user_id, $options, $type, $size, $etc )
  {
    call_user_func_array('qmanager::work', array_merge(array('schedule', 1, $user_id), func_get_args()));
  }

  static function report($type, $provider_id, $pid, $time, $load)
  {
    qmanager::send('report', func_get_args(), 'q');
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
      if (!msg_receive($this->handle, 1, &$type, 2048, &$msg, true, 0, &$error_code)) {
        log::error("Failed to receive message with error $error_code");
        continue;
      }

      if ($msg->method == 'start') {
        $this->started($msg);
      }
      else if ($msg->method == 'report') {
        $this->reported($msg);
      }
      else if ($msg->method == 'work') {
        $this->distribute($msg);
      }
      else if ($msg->method == 'stop') {
        $this->stopped($msg);
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
    $method = $msg->method;
    list($class, $function) = explode('::', $method);
    db::connect_default();
    try {
      require_once($class.'.php');
      if (!is_callable($method)) throw process_exception('Unable to call method $method');
      call_user_func_array($method, $msg->arguments);
    } 
    catch (Exception $e) {
      log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
    } 
  } 

  static function get_control_sql($msg)
  {
    db::connect_default();
    list($option, $filter) = $msg->arguments;
    $sql = "select w.id, p.id, w.type, w.name, p.program, w.allocation, p.max_instances 
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
    global $db;
    $sql = qmanager::get_control_sql($msg);
    
    $self = $this;
    $db->each($sql, function($index, $row) use (&$self) {
      list($id, $provider_id, $type, $name, $program, $capacity, $max_instances) = $row;
      if (isset($self->providers[$type] && isset($self->providers[$type][$provider_id] 
        && sizeof($self->providers[$type][$provider_id]->workers) >= $max_instances)) {
        log::warn("Cannot have more than $info->max_instances of type $info->type");
        return;
      }
      
      if (!isset($self->providers[$type])) $self->providers[$type] = array();
      if (!isset($self->providers[$type][$provider_id])) {
        $info = new provider_info;
        $info->provider_id = $provider_id;
        $info->capacity = $capacity;
        $info->id = $id;
        $info->type = $type;
        $info->name = $name;
        $info->max_instances = $max_instances;
        $info->msg_handle = msg_get_queue($info->id, 0660);
        $info->workers = array();
        $self->providers[$type][$provider_id] = $info;
      }
      log::info("Starting $info->program $info->name $info->id $provider_id $info->capacity");
      exec("$info->program $info->name $info->id $provider_id >/dev/null 2>&1 &");
      //todo: use signal handler to test when executed program dies
    });    
    
  }
  
  function reported($msg)
  {
    list($type, $provider_id, $pid, $time, $load) = $msg->arguments;
    $providers = &$this->workers[$type][$provider_id];
    if (!isset($providers->workers[$pid])) {  //todo: ensure we don't register more than max instances
      $worker = new qworker_info();
      $worker->start_time = $time;
      $workers[$pid] = $worker;
    }
   
    $worker = &$workers[$pid]; 
    $worker->load = $load;
    $worker->last_time = $time;
  }
  
  function stopped($msg)
  {
    list($option, $filter) = $msg->arguments;
    db::connect_default();
    global $db;
    $sql = qmanager::get_control_sql($msg);
    $self = &$this;
    $db->each($sql, function($index, $row) use (&$self, $msg) {
      list($id, $provider_id, $type, $name, $program, $capacity, $max_instances) = $row;
      if (!isset($self->providers[$type])) return;
      if (!isset($self->providers[$type][$provider_id])) return;
     
      $provider = $self->providers[$type][$provider_id];
      log::info("Stopping $program $name $id");
      unset($self->providers[$type][$provider_id]);
      if (!msg_send($provider->msg_handle, 1, $msg))
        log::error("Unable to send message to $info->name");
    });
  
    if ($option == 'all') exit(0);
  }

  function distribute($msg)
  {
    list($user_id, $type, $required, $arguments) = $msg->arguments;
    $provider_id = qmanager::get_provider($user_id);
    do {
      $most_available = 0;
      $best_info = null;
      $provider = &$this->providers[$type][$provider_id];
      
      // first find if there is a worker with no load
      $found_free = false;
      foreach($provider->workers as $worker) {
        if ($worker->load == 0) {
          $found_free = true;
          break;
        }
      }
      
      if (!$found_free) {
        if (sizeof($provider->workers) < $provider->max_instances)) {
          log::warn("No worker of type $type available to process the work, please reconfigure to allow for additional workers");
          //todo: schedule message to go after 5 minutes
          qmanager::schedule(
        }
        else {
          qmanager::start($provider->name);
          qmanager::work($msg);  // re-send msg to queue, there are more workers now
        }
        break;
      }
      $load = min($required, $most_available);
      msg_send($provider->msg_handle, 1, $msg);
      $required -= $load;
    } while ($required > 0);
  }
  
}

?>
