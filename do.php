<?php 
require_once('log.php');

{
  $path_info = explode('/', $_SERVER['PATH_INFO']);
  if (sizeof($path_info) < 2) {
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

  if (sizeof($path_info) > 2) {
    $function = $path_info[2];

    $class_function = $class. '::' . $function;
    $_SESSION['function'] = $class_function;

  log::init($class, log::DEBUG);
    log::debug("PATH_INFO ". implode('/', $path_info));
    try {
      require_once($source_file);
      if (is_callable($class_function)) $function = $class_function;
      if (is_callable($function)) call_user_func($function, $_REQUEST);
    }
    catch (Exception $e) {
      log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
    }
  }
  else {
    require_once($source_file);
  }
} 
?>
