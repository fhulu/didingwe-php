<?php 
session_start();

require_once('log.php');
require_once('db.php');
require_once('config.php');
require_once('user.php');


class session_exception extends Exception {};


class session {
  var $schema_prefix;
  var $default_schema;
  var $vars;
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
    $request = $_REQUEST['a'];
    $path = $_SERVER['PATH_INFO'];
    log::debug("Check login: PATH: $path REQUEST $request"); 
    if (strstr($request, 'session/log') === false 
      && strstr($path, 'user/check') === false 
      && strstr($path, 'user/register') === false
      && request != 'menu') {
      $_SESSION['referrer'] = $_SERVER[REQUEST_URI];
      session::redirect('/?c=login');
    }
  }

  
  static function register($user)
  {
      global $session;
      $session = new session();
      $session->referrer = $_SESSION['referrer'];
    $session->user = $user;
    $session->id = sprintf("%08x%04x%04x%08x",rand(0,0xffffffff),$user->partner_id,$user->id,time());
    $sql = "insert mukonin_audit.session (id, user_id) values ('$session->id','$user->id')";
    
    global $db;
    $db->insert($sql);
    $_SESSION['instance'] = serialize($session);
  }
        
  static function login()
  {
    try {        
      $email = $_REQUEST['email'];
      $passwd = $_REQUEST['password'];
      log::debug("LOGIN: $email PROGRAM: $session->program_id REFERRER: $session->referrer");
         
      $user = user::restore($_REQUEST['email'], $_REQUEST['password']);
      session::register($user);
      if (!$user)
        throw new session_exception("Invalid username/password for ". $_REQUEST[email]);

      global $session;
      if ($session->referrer == '') $session->referrer = '/?c=home';
      session::redirect($session->referrer);
    }
    catch (Exception $e) {
      $_SESSION[last_error] = $e->getMessage();
      log::error("EXCEPTION: ". $e->getMessage());
      session::redirect("/?c=login");
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
    session::redirect("/?c=home");
  }
  
}

if (!$daemon_mode) {
  $session = unserialize($_SESSION['instance']);
  if (is_null($session) || is_null($session->user)) session::ensure_logged_in();
}

?>
