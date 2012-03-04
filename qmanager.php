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
 
/* 
  static function get_handle($is_new = false)
  {
    $file = @fopen('.qmanager.pid', 'rb');
    if ($is_new ) {
      if ($file !== false)
        throw new qmanager_exception("Another instance of qmanager may already be running");
      $file = fopen('.qmanager.pid', 'wb');
      $pid = getmypid();
      fwrite($file, $pid);
    }
    else if ($file === false) 
      throw new qmanager_exception("PID of qmanager not found");
    else
      $pid = (int)fgets($file);
    fclose($file);  
    return msg_get_queue($pid, 0660);
 }
*/
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
  
  static function restart($log_level)
  {
    qmanager::q('restart', $log_level);
  }
  
  static function register($type, $capacity)
  {
    qmanager::q('register', array($type,getmypid(), $capacity));
  }

  static function work($type, $load )
  {
    qmanager::q('work', func_get_args());
  }
  
  static function complete($type, $pid, $load)
  {
    qmanager::q('complete', func_get_args());
  }
  
 
  
  function listen()
  {
    while(1) {
      log::debug("Waiting for a message handle=$this->handle");
      $msg = new qmessage();
      $type = 1;
      $error_code = 0;
      if (!msg_receive($this->handle, 1, &$type, 2048, &$msg, true, 0, &$error_code)) {
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
      else {
        $pid = pcntl_fork();
        if ($pid == -1) 
          log::error("Could not fork");
        else if ($pid == 0)  {
          global $db;
          if (is_null($db)) $db->disconnect();
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
    global $logger;
    log::init($class, $logger->level);
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
    list($option, $filter) = $msg->arguments;
    $sql = "select w.id, p.id, w.type, w.name, p.program from mukonin_process.worker w, mukonin_process.provider p"
          . " where w.provider_id = p.id and w.enabled = 1 and p.enabled = 1";
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
    list($option, $filter) = $msg->arguments;
    db::connect_default();
    global $db;
    $sql = qmanager::get_control_sql($msg);
    
    $self = $this;
    $db->each($sql, function($index, $row) use (&$self) {
      $info = new qworker_info;
      list($info->id, $info->provider_id, $info->type, $info->name, $program) = $row;
      if (array_search($info->id, $self->worker_ids) !== false) {
        log::warn("Worker $info->name($program) already started");
        return;
      }
      $self->worker_ids[] = $info->id;
      $info->msg_handle = msg_get_queue($info->id, 0660);
      $self->workers[$type][$info->id] = $info;
      log::info("Starting $program $info->name $info->id $info->provider_id");
      exec("$program $info->name $info->id $info->provider_id >/dev/null 2>&1 &");
    });    
  }
  
    function stopped($msg)
  {
    list($option, $filter) = $msg->arguments;
    db::connect_default();
    global $db;
    $sql = qmanager::get_control_sql($msg);
    $self = &$this;
    $db->each($sql, function($index, $row) use (&$self, $msg) {
      $info = new qworker_info;
      list($info->id, $info->provider_id, $info->type, $info->name, $program) = $row;
      if (array_search($info->id, $self->worker_ids) === false) return;
     
      log::info("Stopping $program $info->name");
      $info->msg_handle = msg_get_queue($info->id, 0660);
      unset($self->worker_ids[$info->id]);
      unset($self->workers[$info->type][$info->id]);
      if (!msg_send($info->msg_handle, 1, $msg))
        log::error("Unable to send message to $info->name");
    });
  }

  
  function registered($msg)
  {
    list($pid, $type, $capacity) = $msg->arguments;
    $this->workes[$type][$pid] = array($capacity, 0);
  } 

  function distribute($msg)
  {
    list($type, $required, $arguments) = $msg->arguments;
    do {
      $most_available = 0;
      $best_id = 0;
      foreach($this->workers[$type] as $id=>$stats) {
        list($capacity, $load) = $stats;
        $available = $capacity - $load;
        if ($available >= $most_available) {
          $best_id = $id;
          $most_available = $available;
        }
      }
      
      if ($best_id == 0) {
        log::error("No worker of type $type available to process the work, please dispatch additional workers");
      }
      else {
        $this->workers[$type][$best_id][1] = $required;
        $load = min($required, $most_available);
        $handle = msg_get_queue($best_id, 0660);
        msg_send($best_id, 1, $msg);
      }
      $required -= load;
    } while ($required > 0);
  }
  
  function completed($msg)
  {
    list($type, $pid, $load) = $msg->arguments;
    $this->workers[$type][$pid][1] -= $load;
  }
  
}

?>
