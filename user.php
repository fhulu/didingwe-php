<?php
require_once('session.php');

require_once('db.php');
require_once('config.php');
require_once('table.php'); 

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
  var $roles;
  function __construct($data)
  {
    list($this->id, $this->partner_id, $this->email, $this->first_name, $this->last_name, $this->cellphone) = $data;
    $this->load_roles();
    $this->load_functions();
  }

  static function default_functions()
  {
    global $db;
    
    return $db->read_column("select distinct function_code from mukonin_audit.role_function
    where role_code in ('base', 'unreg')");
  }
  
  function load_roles()
  {
    global $db;
    $assigned_roles = $db->read_column("select role_code from mukonin_audit.user_role where user_id = $this->id");
    $this->roles = array();
    foreach ($assigned_roles as $role) {
      $roles = array($role);
      $db->lineage($roles, "code", "base_code", "mukonin_audit.role");
      $this->roles = array_merge($this->roles, $roles);
    }
  }
  
  function load_functions()
  {
    if (sizeof($this->roles) < 1) return;
    $roles = "'". implode("','", $this->roles) . "'";
    global $db;
    $this->functions = $db->read_column("select distinct function_code from mukonin_audit.role_function
    where role_code in($roles)");
  }
  
 
  function assign_role($role)
  {
    if (is_array($this->roles)) 
      $this->roles = array_merge($this->roles, array($role));
    else
      $this->roles = array($role);
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
    $sql = "select id, partner_id, email_address, first_name, last_name from mukonin_audit.user
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
  
  static function create($email, $password, $first_name, $last_name, $cellphone, $otp)
  {
    $program_id = config::$program_id;
    global $session;
    if (is_null($session) || is_null($session->user)) 
      $partner_id = 0;
    else 
      $partner_id = $session->user->partner_id;
   
    $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password, first_name,last_name, cellphone, otp, otp_time)
      values($program_id,$partner_id, '$email',password('$password'), '$first_name','$last_name','$cellphone', '$otp', now())";
    
    global $db;
    $id = $db->insert($sql);
    if ($id == null) return false;
    $sql = "insert into mukonin_audit.user_role(user_id,role_code)
      values($id,'unreg')";
	$db->insert($sql);
    return new user(array($id, $partner_id, $email, $first_name, $last_name, $cellphone, $otp, $partner_id));
  }
  
  static function register($request)
  {
    $first_name = $request[first_name];
    $last_name = $request[last_name];
    $email = $request[email];
    $password = $request[password];
    $cellphone = $request[cellphone];
    $otp = rand(23671,99999);
    $program_id = config::$program_id;
    
    // First check if email already exists
    global $db;
    if (user::exists($email, 0)) {
      $sql = "update mukonin_audit.user set password=password('$password'), first_name = '$first_name',last_name= '$last_name', cellphone='$cellphone',
        otp=$otp, otp_time = now() where email_address='$email' and program_id = $program_id";
      $db->exec($sql);
      $id = $db->read_one_value("select id from mukonin_audit.user where email_address = '$email' and program_id = $program_id");
      $user = new user(array($id,0, $email, $first_name, $last_name, $cellphone, $otp));
    }
    else {
      $user = user::create($email, $password, $first_name, $last_name, $cellphone, $otp);
    }

    $user->partner_id = $request[partner_id];
    $db->exec("update mukonin_audit.user set partner_id = $user->partner_id where id = $user->id");
    session::register($user);
    
    //todo: send email and/or sms

    $message = "Your One Time Password is $otp";
    $subject = "One Time Password";
    $headers = "from: donotreply@gct.fpb.gov.za";
    $mail_sent = mail($email, $subject, $message, $headers);
    
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
    global $db, $session;
    $id = $session->user->id;
    $db->exec("update mukonin_audit.user set active = 1 where id = $id");
  }
  
  static function deactivate()
  {
    global $db;
    $id = $_REQUEST['id'];
    $sql = "delete from mukonin_audit.user_role where user_id=$id";
    $db->exec($sql);
    
    $sql = "update  mukonin_audit.user set active=0 where id=$id";
    $db->exec($sql);
   
   $emails = $db->read_column("select email_address 
                                from mukonin_audit.user 
                                where id =  $id and partner_id=$partner_id");  
                   
    $username = $db->read_one_value("select Concat( first_name, ' ', last_name ) AS contact_person from mukonin_audit.user where id = $id ");
    $admin = "$user->first_name $user->last_name <$user->email>";
    
      
     //todo: send email and/or sms
    foreach($emails as $email) {
        $message = "Dear $username <br> Administrator would like to inform you that your application have been rejected";
        $subject = "Approve Application";
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $headers .= "from:  $admin";
        $mail_sent = mail($email, $subject, $message, $headers);
        log::debug("Sending email to $email");  
    }
  
      
  }
  static function update($request)
  {
    foreach($request as $key=>$value) {
      if ($key != 'PHPSESSID')
        $values .= ", $key = '$value'";
    }
    global $db, $session;
    $id = $session->user->id;
    $sql = "update mukonin_audit.user set ". substr($values,1). " where id = $id";
    $db->exec($sql);
  }

  
  
  static function update_role($request)
  {
   
    global $db, $session;
    $user = &$session->user;
    $id = $_REQUEST['id'];
    $role = $_REQUEST['role'];
    $partner_id = $user->partner_id;
    $sql = "update mukonin_audit.user_role set role_code='$role' where user_id = $id";
    $db->exec($sql);
    
      $emails = $db->read_column("select email_address 
                                from mukonin_audit.user 
                                where id =  $id and partner_id=$partner_id");  
                   

    $username = $db->read_one_value("select Concat( first_name, ' ', last_name ) AS contact_person from mukonin_audit.user where id = $id ");
    $admin = "$user->first_name $user->last_name <$user->email>";
    $user_role = $db->read_one_value("select name from mukonin_audit.role where code = '$role'"); 
      
     //todo: send email and/or sms
    foreach($emails as $email) {
        $message = "Dear $username, <br> Administrator would like to inform you that you have been registed and you role is $user_role.";
        $subject = "Approve Application";
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $headers .= "from:  $admin";
        $mail_sent = mail($email, $subject, $message, $headers);
         log::debug("Sending email  from $admin to $email"); 
      
    }
  }
  
  static function start_approval($request)
  {     
    //todo: email main user

    global $db, $session;
    $user = &$session->user;
   
    $partner_id = $user->partner_id;
    $requestor = "$user->first_name $user->last_name <$user->email>";


    $emails = $db->read_column("select email_address 
            from mukonin_audit.user u, mukonin_audit.user_role ur
            where u.id = ur.user_id and partner_id = $partner_id and role_code = 'admin' ");
    
    foreach($emails as $email) {
      $link = "http://". $_SERVER['SERVER_NAME'] ."/?c=user_view"; //todo: get right http address for production
      $message = "$requestor would like to register as the user. Please click <a href=\"$link\">here</a> to give access to user.";
      $subject = "Approve Registration";
      $headers  = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from: donotreply@submit.fpb.org.za";
      log::debug("Sending email for $requestor to $email");
      $mail_sent = mail($email, $subject, $message, $headers);
      
    }
    
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