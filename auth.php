<?php
class auth {
  var $page;
  var $roles;
  var $sid;

  function __construct(&$page)
  {
    $this->page = $page;
  }

  function login($sid, $user, $role, $other_roles, $groups, $type)
  {
    if (!is_array($other_roles)) $other_roles = explode(',', $other_roles);
    $roles = array_merge($other_roles,[$role, 'auth']);
    $_SESSION["auth"] = ["id"=>$sid, 'user'=>$user,'roles'=>$roles, 'groups'=>$groups, 'partner_type'=>$type];
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

  function get_partner_type()
  {
    $session = $_SESSION["auth"];
    return isset($session)? $session['partner_type']: null;
  }

  function authorized($roles, $partner_types)
  {

    if (!is_array($roles)) $roles = explode (',', $roles);
    if (sizeof($roles) > 0 && sizeof(array_intersect($this->get_roles(), $roles)) == 0) return false;

    if (empty($partner_types)) return true;
    if (!is_array($partner_types)) $partner_types = explode (',', $partner_types);
    return in_array($this->get_partner_type(), $partner_types, true);
  }

  function unauthorized($role, $partner_types)
  {
    return !$this->authorized([$role], $partner_types);
  }

  function logoff()
  {
    session_destroy();
    $_SESSION = [];
  }

}
