<?php

require_once('db.php');
require_once('config.php');
require_once('../common/session.php');
require_once('../common/table.php'); 

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
      $email = $_GET['forgot_email'];
      $ajax = true;
    }
    
    $sql = "select id from mukonin_audit.user where email_address='$email' and program_id = " . config::$program_id; 
    global $db;
    if ($db->exists($sql)) return true;
    
    if ($ajax)
      echo "!We do not have a user with email address '$email' on our system";
  }
  
  static function authenticate($email, $passwd)
  {
    $sql = "select id, partner_id, email_address, first_name, last_name, role_id from mukonin_audit.user
     where email_address='$email' and password=password('$passwd') and program_id = ". config::$program_id;         
    
    global $db;
    return $db->exists($sql)? $db->row: false;
  }
  
  static function restore($email, $password)
  {
    if (($data = user::authenticate($email, $password)) !== false)
      return new user($data);
    return false;
  }
  
  static function create($email, $password, $first_name, $last_name, $role_id=0)
  {
    global $session;
    if (is_null($session))
      throw user_exception("Must be logged on to create a user");

    $partner_id = $session->user->partner_id;
    $program_id = config::$program_id;
    $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password, first_name,last_name, role_id)
    values($program_id,$partner_id, '$email', password('$password'), '$first_name','$last_name',$role_id)";
    
    global $db;
    $id = $db->insert($sql);
    if ($id == null) return false;
     
    return new user(array($id, $partner_id, $email, $first_name, $last_name, $role_id));
  }
  
    static function ajax_add()
    {
      $fname = $_GET[first_name];
      $lname = $_GET[last_name];
      $email = $_GET[email];
      $password = $_GET[password];
      $role = $_GET[role];
      
      $user = user::create($email, $password, $fname, $lname, $role);
      echo $user->id;
    }
    
    static function changePassword()
    {
      $currpassword = $_POST['currpassword'];
      $newpassword = $_POST["newpassword"];
      
      global $session, $db;

      $curr_user_id = $session->user->id;
      
      $db->exec("update mukonin_audit.user
                 set password = password('$newpassword')
                 where id = $curr_user_id 
                 and password = password('$currpassword')");
      
      if ($db->affected_rows() == 0) 
      { 
        echo "Invalid password"; 
        return;
      }       
      
      header("Location: /?c=password_changed");
     
    }
    
  static function userManagement()
  {   
    
    $titles = array('#id', 'Username', 'First Name', 'Last Name', 'Create Time','');
    
    $sql = "select id, email_address, first_name, last_name, create_time
            from mukonin_audit.user
            order by create_time desc"; 
              
    table::display($sql, $titles, table::TITLES | table::ALTROWS, 'qmsg', 430,
    
      function (&$user_data, &$row_data, $row_num, &$attr) 
      {
        $attr .= " id=b" . $row_data['id'];
        $row_data[''] = "<img src='edit16.png' onclick='editUser(this)'> <img  src='remove16.png';/>";

        return true;
      });
  }
}
?>