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
  }

  static function ensure_logged_in()
  {
    session::validate('index.php?c=login');
  }
  static function ensure_not_expired()
  {
    session::validate('index.php?c=home');
  }

  
  static function register($user)
  {
    global $session;
    $session = new session();
    $session->referrer = $_SESSION['referrer'];
    $session->user = $user;
    $session->id = sprintf("%08x%04x%04x%08x",rand(0,0xffffffff),$user->partner_id,$user->id,time());
    $sql = "insert session (id, user_id) values ('$session->id','$user->id')";
    
    global $db;
    $db->insert($sql);
    $_SESSION['instance'] = serialize($session);
  }
       
 
  static function do_login($email, $passwd, $is_paswd_plain=true) 
  {
    log::debug("LOGIN: $email PROGRAM: $session->program_id REFERRER: $session->referrer");

    if (!user::verify_internal($_REQUEST)) return; 

    errors::init();
    $v = new validator();
    if (!$v->check('email')->is('email')) return false;
    $user = user::restore($email, $passwd);
    if (!$user) 
      $v->report("email", "!Invalid username/password for '$email'");
    else
      session::redirect('home.html');    
  }
  
  static function login()
  {
    $email = $_REQUEST['email'];
    $passwd = $_REQUEST['password'];
    session::do_login($email, $passwd);
  } 
  
  static function check_login()
  {
    session::login();
  }

  
  function restore()
  {
    $_SESSION[last_error] = '';
    if ($this->referrer == '') $this->referrer = 'home.html';
    session::redirect($this->referrer);
  }
  
  static function redirect($url)
  {
    log::debug("REDIRECT: $url");
    global $json;
    $json["script"] = "location.href='$url';";
  }

  static function force_logout($user_id)
  {
    global $db;
    $sql = "update session set status='C', end_time=now() where user_id = $user_id";
    $db->exec($sql);
  }
  
 
  static function logout()
  {
    global $db, $session;
    if (isset($db)) {
      $sql = "update session set status='C', end_time=now() where id = '$session->id'";
      $db->send($sql);
    }

    session_destroy();
  }
  
  static function verify()
  {
    global $session,$db;
    if (is_null($session) || $session->id == '') return false;
    
    $status = $db->read_one_value("select status from session where id = '$session->id' ");
    if ($status != 'C') return true;
    session::logout ();
    return false;
  }
}

if (!$daemon_mode) {
  global $session;
  $session = unserialize($_SESSION['instance']);
}

?>
