<?php 
session_start();

require_once('log.php');
require_once('db.php');
require_once('config.php');
require_once('user.php');


class session_exception extends Exception {};


class session 
{
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
  
  static function validate($fallback_url)
  {
    global $session;
    if (!is_null($session) && !is_null($session->user)) return $sesion;
  
    $_SESSION['referrer'] = $_SERVER[REQUEST_URI];
    session::redirect($fallback_url);
    exit(0);
  }

  static function ensure_logged_in()
  {
    session::validate('/?c=login');
  }
  static function ensure_not_expired()
  {
    session::validate('/?c=home');
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
       
 
  static function do_login($email, $passwd, $is_paswd_plain=true) 
  {
    log::debug("LOGIN: $email PROGRAM: $session->program_id REFERRER: $session->referrer");

    if (!user::verify_internal($_REQUEST)) return; 

    $user = user::restore($_REQUEST['email'], $_REQUEST['password']);
    if (!$user) {
      $v = new validator($_REQUEST);
      return $v->report("email", "!Invalid username/password for '$email'");
    } 
    if ($_REQUEST['a'] == 'session/login' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      global $session;
      $session->restore();
    }
  }
  
  static function login()
  {
    $email = $_REQUEST['email'];
    $passwd = $_REQUEST['password'];
    session::do_login($email, $passwd);
  } 
  
  static function check_login()
  {
    $email = $_REQUEST['email'];
    $passwd = $_REQUEST['password'];
    log::debug("LOGIN: $email PROGRAM: $session->program_id REFERRER: $session->referrer");

    $user = user::restore($_REQUEST['email'], $_REQUEST['password']);
    $v = new validator($_REQUEST);
    if (!$user) {
      return $v->report("email", "!Invalid username/password for '$email'");
    } 
  }

  
  function restore()
  {
    $_SESSION[last_error] = '';
    if ($this->referrer == '') $this->referrer = '/?c=home';
    $this->redirect($this->referrer);
  }
  
  static function redirect($url)
  {
    log::debug("REDIRECT: $url");
     echo "<meta http-equiv=\"refresh\" content=\"0;url=$url\">";
  }
    
 
  static function logout()
  {
    global $db, $session;
    if (isset($db)) {
      $sql = "update mukonin_audit.session set status='C', end_time=now() where id = '$session->id'";
      $db->send($sql);
    }

    session_destroy();
  }
  
}

if (!$daemon_mode) {
  global $session;
  $session = unserialize($_SESSION['instance']);
}

?>
