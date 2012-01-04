<?php 
require_once('log.php');

{
  
  list($page,$function) = explode('/', $_GET['a']);
  if ($page=='') {
    echo "No page supplied";
    return;
  }
  $pos = strpos($page, '.php');
  if($pos === false) {
    list($class,$params) = explode('?',$page);
    $source_file = "$class.php";
  }
  else {
    $class = substr($source_file, 0, $pos);
    $source_file = $page;
  }

  if ($function!='') {

    $class_function = $class. '::' . $function;
    $_SESSION['function'] = $class_function;

    log::init($class, log::DEBUG);
    try {
      require_once($source_file);
      if (is_callable($class_function)) $function = $class_function;
      if (is_callable($function)) call_user_func($function);
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
