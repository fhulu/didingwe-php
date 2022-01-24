<?php

$logger=null;

class log
{
  var $level;
  var $instance;
  var $path;

  const OFF = 0;
  const ERROR = 1;
  const WARNING = 2;
  const INFO = 3;
  const DEBUG = 4;
  const TRACE = 5;

  static $subject = array(
    self::OFF => 'OFF',
    self::ERROR => 'ERROR',
    self::WARNING => 'WARNING',
    self::INFO => 'INFO',
    self::DEBUG => 'DEBUG',
    self::TRACE => 'TRACE'
  );

  function __construct ($path=null, $level=self::INFO) {
    if (!is_null($path)) {
      $this->instance = basename($path);
      $this->path = realpath(dirname($path)) . "/$this->instance";
    }
    else {
      $this->instance = $this->path = null;
    }
    if (is_string($level) && !is_numeric($level)) $level = array_search($level, log::$subject);
    $this->level = (int)$level;
    $this->write(self::DEBUG, 'Started '.getmypid());
  }

  function __destruct()  {
    $this->write(self::DEBUG, 'Completed '.getmypid());
  }

  static function init($path, $level=self::INFO) {
    global $logger;

    if (is_null($logger)) $logger = new log($path, $level);
  }


  static function replace_hidden_patterns($message) {
    global $config;
    if (!$config) return $message;  
    $patterns = $config['log_hidden_patterns']??null;
    if (!$patterns || !is_array($patterns)) return $message;
    foreach($patterns as $pattern) {
      [$regex,$replacement] = assoc_element($pattern);
      $message = preg_replace("/$regex/",$replacement, $message);
    }
    return $message;
  }

  function write($level, $message)
  {
    if ($this->level < $level) return;
    $message = str_replace("\n", " ", $message);
    $message = str_replace("\r", " ", $message);
    if (!is_null($this->instance)) {
      $file_name = $this->path;
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
    $message = log::replace_hidden_patterns($message);
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
    foreach($stack as $trace) {
      $message = "TRACE $index. ".$trace['file']." line ".$trace['line']." function ". at($trace, 'class', "") ."::".$trace['function'] ."(".json_encode(at($trace, 'args', [])).')';
      $messages[] = log::error($message);
      ++$index;
    }
    return $messages;
  }

  static function debug_json($name, $value)
  {
    return log::debug("$name ".json_encode($value));
  }

  static function debug_if($condition, $message) {
    if ($condition) log::debug($message);
  }

  static function debug_json_if($condition, $name, $value) {
    if ($condition) log::debug_json($name, $value);
  }

  static function info($message) { return log::log(self::INFO, $message); }
  static function warn($message) { return log::log(self::WARNING, $message); }
  static function error($message) { return log::log(self::ERROR, $message); }
  static function debug($message) { return log::log(self::DEBUG, $message); }
  static function trace($message) { return log::log(self::TRACE, $message); }
}
