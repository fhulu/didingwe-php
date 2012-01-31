<?php

require_once('db.php')
require_once('config.php');

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
  }
  
  static function check($email=null)
  {
    if (is_null($email)) {
      $email = $_GET[email];
      $ajax = true;
    }
  
    $sql = "select id from mukonin_audit.user where email_address='$email' and program_id = $session->program_id"; 
    
    global $db;
    if ($db->exists($sql)) return true;
    
    if ($ajax)
      echo "!We do not have a user with email address '$email' on our system";
  }
  
  static function is_authentic($email, $password)
  {
    $sql = "select id, partner_id, email_address, first_name, last_name, role_id from mukonin_audit.user
     where email_address='$email' and password=password('$passwd') and program_id = ". config::program_id;         
    
    global $db;
    return $db->exists($sql)? $db->row: false;
  }
  
  static function restore($email, $password)
  {
    if (($data = user::verify($email, $password)) !== false)
      return new user($data);
    return false;
  }
  
  static function create($partner_id, $email, $first_name, $last_name, $role_id=0)
  {
    $sql = "insert into mukonin_audit.user(program_id, partner_id,first_name,last_name, role_id)
    values($program_id,$partner_id,'$first_name','$last_name',$role_id)";
    
    global $db;
    $id = $db->insert($sql);
    if ($id == null) return false;
     
    return new user(array($id, $program_id, $partner_id, $email, $first_name, $last_name, $role_id));
  }
  
}
?>