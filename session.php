<?php session_start();

require_once('log.php');
require_once('db.php');
require_once('config.php');
require_once('user.php');


class session_exception extends Exception {};


class session {
  var $schema_prefix;
  var $default_schema;
  var $vars;
  var $db;
  var $id;
  var $user;
  var $program_id;
  var $referrer;
  var $last_error;
  function __construct()
  {
    $this->vars = array();
    $this->default_schema = config::$db_schema;
    $this->schema_prefix = config::$schema_prefix;
  }
  
  static function ensure_logged_in()
  {
    if (strstr($_REQUEST['a'], 'session/login')===false) {
      $_SESSION['referrer'] = $_SERVER[REQUEST_URI];
      session::redirect('/?c=login');
    }
  }

  static function login()
  {
    try { 
      global $session;
      $session = new session();
      $session->program_id = $_REQUEST['program'];
      $session->referrer = $_SESSION['referrer'];
        
      $email = $_REQUEST['email'];
      $passwd = $_REQUEST['password'];
      log::debug("LOGIN: $email PROGRAM: $session->program_id REFERRER: $session->referrer");
      $sql = "select id, partner_id, email_address, first_name, last_name, role_id from mukonin_audit.user
         where email_address='$email' and password=password('$passwd') and program_id = $session->program_id";
         
      global $db;
      if (!$db->exists($sql))
        throw new session_exception("Invalid username/password for '$email'");

      $user = new user($db->row);
      $session->user = $user;
      
      $session->id = sprintf("%08x%04x%04x%08x",rand(0,0xffffffff),$user->partner_id,$user->id,time());
      $sql = "insert mukonin_audit.session (id, user_id) values ('$session->id','$user->id')";
      $db->insert($sql);
      $_SESSION['instance'] = serialize($session);
      if ($session->referrer == '') $session->referrer = '/';
      session::redirect($session->referrer);
    }
    catch (Exception $e) {
      $_SESSION[last_error] = $e->getMessage();
      log::error("EXCEPTION: ". $e->getMessage());
    }
  }
  
  static function redirect($url)
  {
    log::debug("REDIRECT: $url");
    echo "<script language='javascript'>location.replace('$url');</script>\n";
  }
    

  static function logout()
  {
    global $db;
    $session_id = $_SESSION['instance'];
    if (isset($db)) {
      $sql = "update mukonin_audit.session set status='C', end_time=now() where id = '$session_id'";
      $db->send($sql);
    }
    session_destroy();
  }
}

$session = unserialize($_SESSION['instance']);
if (is_null($session)) session::ensure_logged_in();

?>
