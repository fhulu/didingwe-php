<?php

require_once('../common/qworker.php');
require_once('../common/log.php');
require_once('../common/curl.php');

class iweb_exception extends exception {};

class iweb extends qworker
{
  var $session_id;
  var $curl;
  var $root;
  var $user_name;
  var $password;
  var $db_read;
  var $db_update;

  function __construct()
  {
    parent::__construct();
    $this->curl = new curl();
    $this->root = $this->provider_params['url'];
    $this->user_name = $this->provider_params['user_name'];
    $this->password = $this->provider_params['password'];
    $this->port = $this->provider_params['port'];
  }
  
 
  function __destruct()
  {
    if ($this->is_connected()) $this->logout();
    parent::__destruct();
  }
 
  function login()
  {
    if ($this->is_connected()) $this->logout();
    $url = "$this->root/Logon?UserId=$this->user_name&Password=$this->password";
    log::info("Login $url");
    $result = $this->curl->read($url);
    log::debug("Login Response: $result");
    if (!strstr($result, "Success"))
      throw new iweb_exception("Unable to connect to iweb: $result");
/*
Success&SessionID=b5749584541ff110fb72
*/
    $this->session_id = trim(substr($result,18));  
    log::debug("Session ID: $this->session_id");
  }
  
  function is_connected() { return !is_null($this->session_id); }
  function logout()
  {
    $url = "$this->root/Logoff?SessionID=$this->session_id";
    log::info("Logout $url");
    $this->curl->read($url);
    $session_id = null;
  } 

  function submit($id, $msisdn, $message, $try_count=1, $encode=true)
  {
    if ($encode) $message=urlencode($message);
    $url = "$this->root/Submit?SessionID=$this->session_id&PhoneNumber=$msisdn&Reference=$id&MessageText=$message";
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
      ++$this->successes;
      $status = 'sub';
      $field = 'esme_reference';
      $value = $values['MessageReference'];
    }
    else {
      ++$this->errors;
      $value = $values['ErrorCode'];
      if ($try_count < 2) {
        $this->login();
        return $this->submit($id, $msisdn, $message, ++$try_count, false);
      }
      else {
        $status = 'sub';
        $field = 'error_code';
      }
    }
    $this->db_update->exec("update outq set status='$status',attempts=$attempts,$field='$value' where id = $id");
  }
 
  function start()
  {
    $this->login();
    global $db;
    $this->db_read = $db;
    $this->db_update = $db->dup();
    $self = &$this;
    parent::listen(function($schedule_id, $qid) use (&$self)  {
      if ($schedule_id == '' && $qid == '') {
        log::error("No schedule_id or qid given for iweb");
        return;
      }
      $sql = "select id,msisdn, message, reference1 from outq";
      if ($id != '')
        $sql .= " where id = $qid and status = 'pnd'";
      else 
        $sql .= " where schedule_id = $schedule_id and status = 'pnd' limit 0, $self->capacity";
      $self->db_read->each($sql, function($index, $row) {
        list($id, $msisdn, $message) = $row;
        $self->submit($id, $msisdn, $message);
      });
    });
  }
} 

$iweb = new iweb();
$iweb->start();
?>  
