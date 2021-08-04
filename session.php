<?php
require_once('utils.php');
require_once('log.php');
$action = at($_REQUEST, 'action');
$path = at($_REQUEST, 'path');
$config = array_merge(['session_timeout'=>15*60], $config);
$lifetime = $config['session_timeout'];
ini_set('session.gc_maxlifetime', $lifetime);
ini_set('session.cookie_lifetime', $lifetime);
session_set_cookie_params($lifetime);
session_start(['cookie_lifetime' => $lifetime]);

class session_exception extends Exception {};


class session
{
  var $schema_prefix;
  var $vars;
  var $id;
  var $user;
  var $program_id;
  var $referrer;
  var $last_error;
  function __construct()
  {
    $this->vars = array();
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

    require_once('db.php');
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
    require_once('db.php');
    global $db;
    $sql = "update \$audit_db.session set status='C', end_time=now() where user_id = $user_id";
    $db->exec($sql);
  }


  static function logout()
  {
    require_once('db.php');
    global $db, $session;
    if (isset($db)) {
      $sql = "update \$audit_db.session set status='C', end_time=now() where id = '$session->id'";
      $db->send($sql);
    }

    session_destroy();
  }

  static function verify()
  {
    require_once('db.php');
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

log::debug_json("USER is", $_SESSION['uid']);

