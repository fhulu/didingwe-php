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
  
  var $email;
  var $first_name;
  var $last_name;
  var $cellphone;
  var $otp;
  function __construct($data)
  {
    list($this->id, $this->partner_id, $this->email, $this->first_name, $this->last_name, $this->cellphone) = $data;
  }
  
  static function remind($email=null)
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
  
  static function exists($email, $active = 1)
  {
    $program_id = config::$program_id;
    global $db;
    return $db->exists("select id from mukonin_audit.user where email_address = '$email' 
      and program_id = $program_id and active = $active");
  }
  
  static function check($request)
  {
    $program_id = config::$program_id;
    if (user::exists($request[email]))
      echo "!The email address already exists";
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
  
  static function create($email, $password, $first_name, $last_name, $cellphone)
  {
    $program_id = config::$program_id;
    global $session;
    if (is_null($session) || is_null($session->user)) 
      $partner_id = 0;
    else 
      $partner_id = $session->user->partner_id;
   
    $otp = rand(23671,99999);
    $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password, first_name,last_name, cellphone, otp, otp_time)
      values($program_id,$partner_id, '$email', password('$password'), '$first_name','$last_name','$cellphone', '$otp', now())";
    
    global $db;
    $id = $db->insert($sql);
    if ($id == null) return false;
     
    return new user(array($id, $partner_id, $email, $first_name, $last_name, $cellphone, $otp));
  }
  
  static function register($request)
  {
    $fname = $request[first_name];
    $lname = $request[last_name];
    $email = $request[email];
    $password = $request[password];
    $cellphone = $request[cellphone];
    $otp = rand(23671,99999);
    $program_id = config::$program_id;
    
    // First check if email already exists
    if (user::exists($email, 0)) {
      global $db;
      $sql = "update mukonin_audit.user set password=password('$password'), first_name = '$fname',last_name='$lname', cellphone='$cellphone',
        otp=$otp, otp_time = now() where email_address='$email' and program_id = $program_id";
      $db->exec($sql);
      $id = $db->read_one_value("select id from mukonin_audit.user where email_address = '$email' and program_id = $program_id");
      $user = new user(array($id,0, $email, $fname, $name, $cellphone, $otp));
    }
    else {
      $user = user::create($email, $password, $fname, $lname, $cellphone);
    }

    session::register($user);
    global $session;
    $user_id = $session->user->id;
    log::debug("logged in user session id is $user_id");
      
  }
    
  static function check_otp($request)
  {

    $otp = $request['otp'];
    
    global $db, $session;
    $id = $session->user->id;
    if (!$db->exists("select id from mukonin_audit.user 
      where id = $id and otp='$otp' and timestampdiff(minute, otp_time, now()) <= 10")) {
        echo "!Invalid OTP or OTP has expired";
        return false;
    }
    return true;
  }
  
  static function activate($request)
  {
    if (!user::check_otp($request)) return false;
    global $db;
    global $db, $session;
    $id = $session->user->id;
    $db->exec("update mukonin_audit.user set active = 1 where id = $id");
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