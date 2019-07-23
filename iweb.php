<?php

require_once('../didi/qworker.php');
require_once('../didi/log.php');
require_once('../didi/curl.php');

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

  function submit($id, $msisdn, $message, $attempts=1, $encode=true)
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
      $status = 'submitted';
      $value = $values['MessageReference'];
    }
    else {
      ++$this->errors;
      $value = $values['ErrorCode'];
      if ($attempts < 2) {
        $this->login();
        return $this->submit($id, $msisdn, $message, ++$attempts, false);
      }
      else {
        $status = 'error';
      }
    }

    global $db;
    $db->exec("update mukonin_sms.outq set status='$status',attempts=$attempts,esme_reference='$value' where id = $id");
    return $status;
  }

  function start()
  {
    $this->login();

    //todo: process pending item on queue before listening

    global $db;
//    $this->db_read = $db;
 //   $this->db_update = $db->dup();
    $self = &$this;
    parent::listen(function($provider_id, $type, $load, $callback, $key_name, $key_value) use (&$self)  {
      if ($key_name == '' && $key_value == '') {
        log::error("No keys given for iweb work");
        return;
      }
      global $db;
      $sql = "select id,msisdn, message, intref2 from mukonin_sms.outq
        where $key_name = $key_value and status = 'pnd' limit $load";
      $rows = $db->read($sql);
//      $self->db_read->each($sql, function($index, $row) use (&$self) {
      foreach($rows as $row) {
        list($id, $msisdn, $message,$intref) = $row;
        $status = $self->submit($id, $msisdn, $message);
        qmanager::call($callback,array($key_value, $intref, $status));
      };
    });
  }
}

$iweb = new iweb();
$iweb->start();
