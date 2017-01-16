<?php
class auth {
  var $page;
  var $roles;
  var $sid;

  function __construct(&$page)
  {
    $this->page = $page;
  }

  function login($sid, $user, $role, $other_roles, $groups)
  {
    $roles = merge_options($other_roles,[$role, 'auth']);
    $_SESSION["auth"] = ["id"=>$sid, 'user'=>$user,'roles'=>$roles, 'groups'=>$groups];
  }

  function get_session_id()
  {
    $session =  $_SESSION['auth'];
    return isset($session)? $session['id']: null;
  }

  function get_roles()
  {
    $session = $_SESSION["auth"];
    return isset($session)? $session['roles']: ['public'];
  }

  function get_groups()
  {
    $session = $_SESSION["auth"];
    return isset($session)? $session['groups']: [];
  }

  function authorized($roles)
  {
    if (!is_array($roles)) $roles = explode (',', $roles);
    return sizeof(array_intersect($this->get_roles(), $roles)) > 0;
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
