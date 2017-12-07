#!/usr/bin/php

<?php

list($class, $method) = explode('::', $argv[1]);
if (!is_null($method)) {
  require_once($class.'.php');
  require_once('log.php');
  log::init($class, log::DEBUG);
}
foreach($_ENV as $key=>$env) $_SESSION[$key] = $env;
try {
  echo call_user_func_array($argv[1], array_slice($argv, 2));
}
catch (Exception $e) {
  log::error("UNCAUGHT EXCEPTION: " . $e->getMessage() );
}

?>
