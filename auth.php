<?php
class auth {
  var $page;

  function __construct(&$page)
  {
    $this->page = $page;
  }

  function login($sid, $partner, $user, $role, $other_roles, $groups, $type)
  {
    if (!is_array($other_roles)) $other_roles = explode(',', $other_roles);
    $roles = array_merge($other_roles,[$role, 'auth']);
    $_SESSION["auth"] = ["id"=>$sid, 'partner'=>$partner, 'user'=>$user,'roles'=>$roles, 'groups'=>$groups, 'partner_type'=>$type];
  }

  function get_session_id()
  {
    $session =  $_SESSION['auth'];
    return isset($session)? $session['id']: null;
  }

  function get_partner()
  {
    $session =  $_SESSION['auth'];
    return isset($session)? $session['partner']: 0;
  }

  function get_user()
  {
    $session =  $_SESSION['auth'];
    return isset($session)? $session['user']: 0;
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
    return isset($session)? $session['partner_type']: 'public';
  }

  private function verify($object_rights, $user_rights)
  {
    if ($object_rights == '') return true;
    if (!is_array($object_rights)) $object_rights = explode (',', $object_rights);
    return sizeof($object_rights) == 0 || sizeof(array_intersect($user_rights, $object_rights)) > 0;
  }

  function authorized($roles, $partner_types='')
  {
    return $this->verify($roles, $this->get_roles()) && $this->verify($partner_types, [$this->get_partner_type()]);
  }

  function unauthorized($role, $partner_types='')
  {
    return !$this->authorized([$role], $partner_types);
  }

  function logoff()
  {
    session_destroy();
    $_SESSION = [];
  }

}
