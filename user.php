<?php

require_once('log.php');

class user_exception extends Exception {};
class user
{
  var $id;
  var $partner_id;
  var $role_id;
  
  var $email;
  var $first_name;
  var $last_name;
  function __construct($data)
  {
  
   list($this->id, $this->partner_id, $this->email, $this->first_name, $this->last_name, $this->role_id) = $data;
    log::debug("User $this->email $this->first_name $this->last_name");
   }
}

?>