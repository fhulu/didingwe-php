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
    $this->roles = merge_options($other_roles,[$role, 'auth']);
    $_SESSION["auth"] = ["id"=>$sid, 'roles' => $this->roles];
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

  function authorized($roles)
  {
    if (!is_array($roles)) $roles = explode (',', $roles);
    return sizeof(array_intersect($this->roles, $roles)) > 0;
  }

  function unauthorized($role)
  {
    return !$this->authorized([$role]);
  }

  function logoff()
  {
    session_destroy();
    $_SESSION = [];
  }

}
