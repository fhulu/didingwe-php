<?php
class auth {
  var $page;
  var $roles;
  var $sid;

  function __construct(&$page)
  {
    $this->page = $page;
  }

  function login($sid, $role, $other_roles)
  {
    $_SESSION["auth"] =
      ["id"=>$sid, 'roles' => merge_options($other_roles,[$role, 'auth'])];
  }

  function get_session_id()
  {
    $session =  $_SESSION['auth'];
    return isset($session)? $session['id']: null;
  }

  function get_roles($reload = false)
  {
    $session = $_SESSION["auth"];
    if (!isset($session)) return $this->roles = ['public'];
    if (!$reload && $this->roles) return $this->roles;
    return $this->roles = $session['roles'];
  }

  function logoff()
  {
    session_destroy();
    $_SESSION = [];
  }

}
