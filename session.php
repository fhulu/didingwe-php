<?php 
session_start();

require_once('utils.php');
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
    page::redirect($fallback_url);
  }

  static function ensure_logged_in()
  {
    session::validate('/login');
  }
  static function ensure_not_expired()
  {
    session::validate('/home');
  }

  
  static function register($user)
  {
    global $session;
    $session = new session();
    $session->referrer = SESSION('referrer');
    $session->user = $user;
    $session->roles = $user->roles;
    $session->id = sprintf("%08x%04x%04x%08x",rand(),$user->partner_id,$user->id,time());
    $sql = "insert \$audit_db.session (id, user_id) values ('$session->id','$user->id')";
    log::debug_json("ROLES", $user->roles);
    
    global $db;
    $db->insert($sql);
    $_SESSION['instance'] = serialize($session);
  }
       
 
  static function do_login($email, $passwd) 
  {
    $user = user::restore($email, $passwd);
    if (!$user) 
      page::error("email", "Invalid username/password for '$email'");
    else {
      $page = SESSION('content');
      if (is_null($page)) $page = 'home';
      page::close_dialog();
      page::redirect("/$page");
    }
  }
  
  static function login()
  {
    $email = REQUEST('email');
    $passwd = REQUEST('password');
    session::do_login($email, $passwd);
  } 
  
  static function check_login()
  {
    session::login();
  }

  
  function restore()
  {
    $_SESSION[last_error] = '';
    if ($this->referrer == '') $this->referrer = '/home';
    session::redirect($this->referrer);
  }
  
  static function redirect($url)
  {
    page::redirect($url);
  }

  static function force_logout($user_id)
  {
    global $db;
    $sql = "update \$audit_db.session set status='C', end_time=now() where user_id = $user_id";
    $db->exec($sql);
  }
  
 
  static function logout()
  {
    global $db, $session;
    if (isset($db)) {
      $sql = "update \$audit_db.session set status='C', end_time=now() where id = '$session->id'";
      $db->send($sql);
    }

    session_destroy();
  }
  
  static function verify()
  {
    global $session,$db;
    if (is_null($session) || $session->id == '') return false;
    
    $status = $db->read_one_value("select status from \$audit_db.session where id = '$session->id' ");
    if ($status != 'C') return true;
    session::logout ();
    return false;
  }
}
  
if (!isset($daemon_mode)) {
  global $session;
  if (isset($_SESSION['instance']))
    $session = unserialize($_SESSION['instance']);
  else
    $session = null;
}
