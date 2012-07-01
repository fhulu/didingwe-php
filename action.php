<?php 
require_once('log.php');

//
// this may be method call
//
list($page, $function) = explode('/', $_GET['a']);
if ($page=='') {
  echo "No page supplied";
  return;
}
 
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
  call_user_func($function, $_REQUEST);
}
catch (user_exception $exception) {
  log::error("UNCAUGHT EXCEPTION: " . $exception->getMessage() );
  session::redirect('/?c=breach');
}
catch (Exception $exception)
{
  log::error("UNCAUGHT EXCEPTION: " . $exception->getMessage() );
  require_once('error.php');
}
?>
