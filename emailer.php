<?php

require_once 'module.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

class emailer extends module {
  function __construct($page) {
    parent::__construct($page);
  }

  function send($options) {
    $this->page->merge_context('emailer.send', $options);
    log::debug_json("EMAIL OPTIONS", $options);
    $mail = new PHPMailer(true);
    static $aliases = ["to"=>"address", "message"=>"body"];
    foreach ($options as $name=>$value) {
      $alias = at($aliases, $name);
      if ($alias) $name = $alias;
      $capitalized = false;
      $capital = ucwords($name);
      $setter = "set$capital";
      $adder = "add$capital";
      if ( method_exists($mail, $name) || $capitalized = method_exists($mail, $capital)) {
        if ($capitalized) $name = $capital;
        if (!is_array($value)) $value = [$value] ;
        log::debug_json("PHPMAIL.$name", $value);
        call_user_func_array([$mail, $name], $value);
      }
      else if ( method_exists($mail, $setter)) {
        if (!is_array($value)) $value = [$value] ;
        log::debug_json("PHPMAIL.$setter", $value);
        call_user_func_array([$mail, $setter], $value);
      }
      else if ( method_exists($mail, $adder)) {
        if (!is_array($value))
          $value = [ [$value] ];
        else if (!is_array($value[0]))
          $value = [ $value ];
        foreach($value as $args) {
          log::debug_json("PHPMAIL.$adder", $args);
          call_user_func_array([$mail, $adder], $args);
        }
      }
      else if ( property_exists($mail, $name) || $capitalized = property_exists($mail, $capital)) {
        if ($capitalized) $name = $capital;
        log::debug_json("PHPMAIL.$name", $value);
        $mail->$name = $value;
      }
    }
    $mail->send();
  }
}
