<?php

require_once('log.php');
require_once('curl.php');

class iweb_exception extends exception {};

class iweb 
{
  var $session_id;
  var $curl;
  var $path;
  var $user_name;
  var $password;

  function __construct($path, $user_name, $password, $port=80)
  {
    $this->curl = new curl();
    $this->path = $path;
    $this->user_name = $user_name;
    $this->password = $password;
    $this->port = $port;
    $this->login();
  }
  
 
  function __destruct()
  {
    $this->logout();
  }
 
  function login()
  {
    if ($this->is_connected()) $this->logout();
    
    $url = "$this->path/Logon?UserId=$this->user_name&Password=$this->password";
    log::info("Login $url");
    $result = $this->curl->read($url);
    log::debug("Login Response: $result");
    if (!strstr($result, "Success"))
      throw new iweb_exception("Unable to connect to iweb: $result");
/*
Success&SessionID=b5749584541ff110fb72
*/
    $this->session_id = trim(substr($result,18));  
    log::trace("Session ID: $this->session_id");
  }
  
  function is_connected() { return !is_null($this->session_id); }
  function logout()
  {
    $url = "$this->path/Logoff?SessionID=$this->session_id";
    $this->curl->read($url);
    log::info("Logout $url");
    $session_id = null;
  } 

  function submit($msisdn, $reference, $message, $try_count=1, $encode=true)
  {
    if ($encode) $message=urlencode($message);
    $url = "$this->path/Submit?SessionID=$this->session_id&PhoneNumber=$msisdn&Reference=$reference&MessageText=$message";
    $results = $this->curl->read($url);
    log::info("Submit $url");
    $results = explode('&', $results);
    
    //Error&ErrorCode=7&ErrorDescription=Invalid destination&PhoneNumber=888888
    //Success&MessageReference=110013461443071&PhoneNumber=+27828992177&Credits=1048 
    foreach($results as $result) {
      $value_pair = explode('=', $result);
      if (sizeof($value_pair) == 1)
        $values[] = $value_pair[0];
      else 
        $values[$value_pair[0]] = $value_pair[1];
    }
    if ($values[0] == 'Success') {
      $values[0] = 1;
      $values[1] = $values['MessageReference'];
    }
    else {
      $values[0] = 0;
      $error_code = $values['ErrorCode'];
      if ($try_count < 2) {
        $this->login();
        return $this->submit($msisdn, $reference, $message, ++$try_count, false);
      }
      else {
        $values[1] = $error_code;
      }
    }
    $values[2] = $try_count;
    return $values;
  }
} 
?>  
