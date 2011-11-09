<?php

list($class, $method) = explode('::', $argv[1]);
if (!is_null($method)) {
  require_once($class.'.php');
  require_once('log.php');
  log::init($class, log::DEBUG);
}
foreach($_ENV as $key=>$env) $_SESSION[$key] = $env;
global $client_id;
$client_id = $_SESSION[client_id];
call_user_func_array($argv[1], array_slice($argv, 2));
?>
