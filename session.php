<?php session_start();

require_once('log.php');

class session_exception extends Exception {};

log::init('session', log::DEBUG);

$client_id = $_SESSION['client_id'];
if ($client_id=='' && strstr($_SERVER[PATH_INFO], 'session/login')===false) {
  header("Location: login.php");
  return;
}

require_once('db.php');
require_once('config.php');

function login()
{
  try {
    global $db;
    global $client_id, $program_id, $user_id;
    
    $program_id = $_REQUEST['program'];
    $email = $_REQUEST['email'];
    $passwd = $_REQUEST['password'];
    $referrer = $_SESSION['referrer'];
    log::debug("LOGIN: $email PROGRAM: $program_id REFERRER: $referrer");
    $sql = "select id, client_id, first_name, last_name, role_id from mukonin_audit.user
       where email_address='$email' and password=password('$passwd') and program_id = $program_id";
    if (!$db->exists($sql))
      throw new session_exception("Invalid username/password for '$email'");

    list($user_id, $client_id, $first_name, $last_name, $role_id) = $db->row;
    $session_id = sprintf("%08x%04x%04x%08x",rand(0,0xffffffff),$client_id,$user_id,time());
    $sql = "insert mukonin_audit.session (id, user_id) values ('$session_id','$user_id')";
    $db->insert($sql);
    $_SESSION['session_id'] = $session_id;
    $_SESSION['client_id'] = $client_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['program_id'] = $program_id;
    $_SESSION['user_id'] = $user_id;

    unset($_SESSION['login_error']);

    if ($referrer == '') $referrer = $_REQUEST[next];
    if ($referrer == '') $referrer = '/';
    header("Location: $referrer");
  }
  catch (Exception $e) {
    $_SESSION['login_error'] = $e->getMessage();
    header("Location: login.php");
  }
}

function logout()
{
  global $db;
  $session_id = $_SESSION['session_id'];
  if (isset($db)) {
    $sql = "update mukonin_audit.session set status='C', end_time=now() where id = '$session_id'";
    $db->send($sql);
  }
  session_destroy();
}

function confirm0($msg, $yes_url="", $no_url="")
{
  echo <<<EOT
  <script type="text/javascript">
    if (confirm("$msg")) {
  	  if ("$yes_url"!="") {
  	    window.location.href="$yes_url";
  	    die();
  	  }
    }
    else {
      if ("$no_url"!="") {
        window.location.href="$no_url";
        die();
      }
    }
  </script>
EOT;
}

function confirm($msg, $yes="Yes|this.form.submit()", $no="No", $option3=null)
{
  $yes = explode('|', $yes);
  $yes_caption = $yes[0];
  $yes_action = $yes[1];
  echo <<<EOT
	<p>$msg</p>
	<div class=line>
	  <input type="button" value="$yes_caption" onclick="$yes_action;"/>
	  <input type="button" value="$no"/>
	</div>
EOT;
}


function alert($msg, $url="")
{
  echo <<<EOT
  <script type="text/javascript">
    alert("$msg");
    if ("$url" != "") {
      window.location.href="$url";
      die();
    }
  </script>
EOT;
}

function set_on_empty($var, $value)
{
  if (isset($var) || $var == "") $var = $value;
}

?>
