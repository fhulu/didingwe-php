<?php 
require_once('log.php');

$function = $_GET['a'];
$pos = strpos($function, '/');
if ($pos === false) {  // this is a function
  require_once('db.php');
  global $db, $session;
  if (!is_null($session)) {
    require_once('session.php');
    $user_id = &$session->user;
    $db->exec("insert into mukonin_audit.trx(user_id, function_code) values ($user_id, '$function')");
  }
  $url = $_GET['u'];
  if ($url == '') $url = "/?c=$function";
  echo "<script language='javascript'>location.replace('$url');</script>\n";
  return;
}

//
// this may be method call
//
$page = substr($function, $pos-1);
$function = substr($function, pos+1);
$pos = strpos($page, '.php');
if($pos === false) {
  $class = $page;
  $source_file = "$class.php";
}
else {
  $class = substr($source_file, 0, $pos);
  $source_file = $page;
}

require_once($source_file);
if ($function == '') return;

//
// this is definately a method call
//
$function = $class. '::' . $function;
$_SESSION['function'] = $function;

log::init($class, log::DEBUG);
try {
  scall_user_func($function);
}
catch (Exception $e) {
  log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
}
?>
