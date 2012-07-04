<?php
require_once('session.php');

require_once('db.php');
require_once('config.php');
require_once('table.php'); 
require_once('select.php');

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
  var $functions;
  function __construct($data)
  {
    list($this->id, $this->partner_id, $this->email, $this->first_name, $this->last_name, $this->cellphone) = $data;
    $this->reload();
  }

  static function default_functions()
  {
    global $db;
    
    return $db->read_column("select distinct function_code from mukonin_audit.role_function
    where role_code in ('base', 'unreg')");
  }
  
  function reload()
  {
    $this->load_roles();
    $this->load_functions();
    session::register($this);
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
    $roles = implode("','", $this->roles);
    global $db;
    
    $groups = $db->read_column("select group_code from mukonin_audit.group_partner where partner_id = $this->partner_id");
    $db->lineage($groups, "code", "parent_code", "mukonin_audit.partner_group", "and program_id=".config::$program_id);
    $groups = implode("','", $groups);
    $functions = $db->read_column(
      "select distinct function_code from mukonin_audit.role_function where role_code in('$roles') 
          and function_code in 
          (select distinct function_code from mukonin_audit.partner_group_function where group_code in ('$groups'))");
    $base_functions = $db->read_column("select distinct function_code from mukonin_audit.role_function where role_code = 'base'"); 
    $this->functions = array_merge($functions, $base_functions);
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
    if (!user::verify_internal($request)) return false;
    $email = $request['email'];
    if (user::exists($email)) {
      echo "!The email address already exists";
      return false;
    }
    return true;
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
  
  static function create($partner_id, $email, $password, $first_name, $last_name, $cellphone, $otp)
  {
    $program_id = config::$program_id;   
    $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password, first_name,last_name, cellphone, otp, otp_time)
      values($program_id,$partner_id, '$email',password('$password'), '$first_name','$last_name','$cellphone', '$otp', now())";
    
    global $db;
    $id = $db->insert($sql);
    $sql = "insert into mukonin_audit.user_role(user_id,role_code)
      values($id,'reg')";
    $db->exec($sql);
    return new user(array($id, $partner_id, $email, $first_name, $last_name, $cellphone, $otp, $partner_id));
  }
  
  static function register($request)
  {    
    if (!user::verify_internal($request)) return;

    if (!user::check($request)) return;
    
    $first_name = $request[first_name];
    $last_name = $request[last_name];
    $email = $request[email];
    $password = $request[password];
    $cellphone = $request[cellphone];
    $otp = rand(13671,99999);
    $program_id = config::$program_id;
    $partner_id = (int)$request['partner_id'];
    
    // First check if email already exists
    global $db;
    if (user::exists($email, 0)) {
      $sql = "update mukonin_audit.user set password=password('$password'), first_name = '$first_name',last_name= '$last_name', cellphone='$cellphone',
        otp=$otp, otp_time = now(), partner_id = $partner_id where email_address='$email' and program_id = $program_id";
      $db->exec($sql);
      $id = $db->read_one_value("select id from mukonin_audit.user where email_address = '$email' and program_id = $program_id");
      $db->exec("delete from mukonin_audit.user_role where user_id = $id");
      $db->exec("insert into mukonin_audit.user_role(user_id,role_code) values($id,'reg')");
      $user = new user(array($id, $partner_id, $email, $first_name, $last_name, $cellphone, $otp));
    }
    else {
      $user = user::create($partner_id, $email, $password, $first_name, $last_name, $cellphone, $otp);
    }

    //todo: send email and/or sms
    $message = "Your One Time Password is $otp";
    $subject = "One Time Password";
    $headers = "from: donotreply@gct.fpb.gov.za";
    $mail_sent = mail($email, $subject, $message, $headers);
      
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
    $fields = array('email_address','first_name', 'last_name', 'otp');
    $values = '';
    foreach($request as $key=>$value) {
      if (in_array($key, $fields))
        $values .= ", $key = '$value'";
    }
    global $db, $session;
    $id = $request['id'];
    if ($id == $user_id)
      $function = 'update_own_details';
    else
      $function = 'update_details';
    user::audit($function, $id, $values);
    
    $sql = "update mukonin_audit.user set ". substr($values,1). " where id = $id";
    $db->exec($sql);
    if (!is_null($request['role']))
      user::update_role($request);
  }

  static function verify($function, $private=true)
  {
    if ($private) session::ensure_logged_in();
    global $session;
    $user = $session->user;
    if (is_null($session) || is_null($user) || $user->partner_id == 0) {
      $email = 'public';
      $functions = user::default_functions();
    } 
    else {
      $email = $user->email; 
      $functions = &$user->functions;
    }
    if (!in_array($function, $functions)) 
      throw new user_exception("Unauthorised access to function $function from $email");
  }
  
  static function audit($function, $object='', $detail='')
  {
    user::verify($function);
    global $db, $session;
    $user = $session->user;
    log::info("FUNCTION: $function USER: $user->id OBJECT: $object DETAIL: $detail");
    if (is_numeric($object)) {
      $object_id = $object; 
      $object_code = 'null';
    }
    else {
      $object_code = "'$object'";
      $object_id = 'null';
    }
    $detail = addslashes($detail);
    $db->insert("insert into mukonin_audit.trx(user_id, function_code, object_id, object_code, detail)
      values($user->id, '$function', $object_id, $object_code, '$detail')");
  }
  
  static function update_role($request)
  {
    $id = $request['id'];
    $role = $request['role'];
    user::audit('update_role', id, $role);
    
    global $db, $session;
    $user = &$session->user;

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
        $message = "Dear $username, <br> Administrator would like to inform you that you have been registerd and you role is $user_role.";
        $subject = "Approve Application";
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $headers .= "from:  $admin";
        $mail_sent = mail($email, $subject, $message, $headers);
         log::debug("Sending email  from $admin to $email"); 
      
    }
  }
  
  static function verify_internal($request)
  {
    $email = $request[email];
    if (config::$program_id == 3 && !preg_match('/@(fpb\.(org|gov)\.za|mukoni\.co\.za)/i', $email)) {
      echo "!Application not yet released to the public. An announcement will be made soon.";
      return false;
    }
    return true;
  }
  
  static function verify_hacker($request)
  {
    return user::verify_internal($request) && user::check_otp($request); 
  }
  
  static function start_approval($request)
  {     
    if (!user::check_otp($request)) return;
    
    global $db, $session;
    $user = &$session->user;
   
    $partner_id = $user->partner_id;
    if ($partner_id == 0) throw new user_exception("Trying to approve a user without a partner id");
    
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
      $headers .= "from: $requestor";
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
     
  static function roles($request)
  {
    echo select::add_db("select code, name from mukonin_audit.role where code not in('unreg','base')");
  }
  
  static function manage($request)
  {  
    user::verify('manage_users');
    
    global $session;
    $partner_id = $session->user->partner_id;
    $sql = "select id, u.create_time, email_address, first_name, last_name, r.name role  
      from mukonin_audit.user u, mukonin_audit.user_role ur, mukonin_audit.role r
      where u.id=ur.user_id and r.code = ur.role_code 
      and partner_id = $partner_id and r.program_id = ". config::$program_id;    
            
    $titles = array('#id','~Time', '~Email Address|edit','~First Name|edit','~Last Name|edit','Role|edit=list:user/roles');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE);
    $table->set_heading("Manage Users");
    $table->set_key('id');
    $table->set_saver("/?a=user/update");
    $table->set_options($request);
    $table->show($sql);
  }
}
?>
