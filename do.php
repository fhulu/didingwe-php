<?php
require_once("session.php");
require_once("log.php");

$path_info = explode('/', $_SERVER[PATH_INFO]);
if (sizeof($path_info) < 3) {
  echo "Not enough arguments supplied for path info";
  return;
}

$source_file = $path_info[1];
$pos = strpos($source_file, '.php');
if($pos === false) {
  $class = $source_file;
  $source_file .= '.php';
}
else {
  $class = substr($source_file, 0, $pos);
}

require_once($source_file);

$function = $path_info[2];
$class_function = $class. '::' . $function;
if (is_callable($class_function)) {
  //log::init($class, log::DEBUG);
  $function = $class_function;
}
call_user_func($function);
?>
