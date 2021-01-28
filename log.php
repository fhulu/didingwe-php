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
    global $config;
    $log = $config['log'];
    if (!$config || !$log) return;
    $log_dir = $log['dir'];
    $log_file = $log['path'];
    $message = str_replace("\n", " ", $message);
    $message = str_replace("\r", " ", $message);

    if (!is_null($this->instance)) {
      $file_name = realpath($this->instance);
      if (file_exists($file_name)) {
        $today = date('Y-m-d');
        $file_date = date('Y-m-d', filemtime($file_name));
        if ($file_date != $today) {
          $old_name = dirname($file_name)."/$file_date-". basename($file_name);
          rename($file_name, $old_name);
        }
      }
      if (!$file_name) return;
      $file = fopen($file_name,'a+');
    }
    else $file = 'STDOUT';
    $pid = getmypid();
    fputs($file, date('Y-m-d H:i:s')." $this->instance($pid) ".log::$subject[$level]. ": $message\n");
    if ($file != 'STDOUT') fclose($file);
  }

  static function log($level, $message)
  {
    global $logger;
    if (is_null($logger)) return;
    $logger->write($level, $message);
  }

  static function stack($exception)
  {
    $index = 0;
    $stack = array_reverse($exception->getTrace());
    foreach($stack as $trace) {
      log::error("TRACE $index. ".$trace['file']." line ".$trace['line']." function ".$trace['class'] ."::".$trace['function'] ."(".json_encode($trace['args']).')');
      ++$index;
    }
  }

  static function debug_json($name, $value)
  {
    log::debug("$name ".json_encode($value));
  }

  static function info($message) { log::log(self::INFO, $message); }
  static function warn($message) { log::log(self::WARNING, $message); }
  static function error($message) { log::log(self::ERROR, $message); }
  static function debug($message) { log::log(self::DEBUG, $message); }
  static function trace($message) { log::log(self::TRACE, $message); }
}
