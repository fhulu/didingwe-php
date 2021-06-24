<?php

$logger=null;

class log
{
  var $level;
  var $instance;

  const OFF = 0;
  const ERROR = 1;
  const WARNING = 2;
  const INFO = 3;
  const DEBUG = 4;
  const TRACE = 5;

  static $subject = array(
    self::ERROR => 'ERROR',
    self::WARNING => 'WARNING',
    self::INFO => 'INFO',
    self::DEBUG => 'DEBUG',
    self::TRACE => 'TRACE'
  );

  function __construct ($instance, $level=self::INFO)
  {
    $this->instance = $instance;
    $this->level = (int)$level;
    $this->write(self::DEBUG, 'Started '.getmypid());
  }


  function __destruct()
  {
    $this->write(self::DEBUG, 'Completed '.getmypid());
  }

  static function init($instance, $level=self::INFO)
  {
    global $logger;

    if (is_null($logger)) $logger = new log($instance, $level);
    $logger->instance = $instance;
    $logger->level = $level;
	  }


  function write($level, $message)
  {
    if ($this->level < $level) return;
    $message = str_replace("\n", " ", $message);
    $message = str_replace("\r", " ", $message);

    if (!is_null($this->instance))
    $file = fopen(dirname($_SERVER['SCRIPT_FILENAME']).'/../log/'.date('Y-m-d').'-'.$this->instance .'.log','a+');
    else $file = 'STDOUT';
    $pid = getmypid();
    $message = date('Y-m-d H:i:s')." $this->instance($pid) ".log::$subject[$level]. ": $message\n";
    fputs($file, $message);
    if ($file != 'STDOUT') fclose($file);
    return $message;
  }

  static function log($level, $message)
  {
    global $logger;
    if (is_null($logger)) return;
    return $logger->write($level, $message);
  }

  static function stack($exception)
  {
    $index = 0;
    $stack = array_reverse($exception->getTrace());
    $messages = [];
    global $config;
    $hidden_funcs = $config['log_hidden_functions'];
    $hidden_marker = $config['log_hidden_marker'];
    foreach($stack as $trace) {
      $message = "TRACE $index. ".$trace['file']." line ".$trace['line']." function ".$trace['class'] ."::".$trace['function'] ."(".json_encode($trace['args']).')';
      foreach($hidden_funcs as $func) {
        $message = preg_replace("/$func\([^)]*\)/","$func($hidden_marker)", $message);
      }
      $messages[]  = log::error($message);
      ++$index;
    }
    return $messages;
  }

  static function debug_json($name, $value)
  {
    return log::debug("$name ".json_encode($value));
  }

  static function info($message) { return log::log(self::INFO, $message); }
  static function warn($message) { return log::log(self::WARNING, $message); }
  static function error($message) { return log::log(self::ERROR, $message); }
  static function debug($message) { return log::log(self::DEBUG, $message); }
  static function trace($message) { return log::log(self::TRACE, $message); }
}
