<?php
require_once('session.php');

require_once('db.php');
require_once('config.php');
require_once('table.php'); 
require_once('select.php');
require_once('validator.php');
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
  
  
  static function start_reset_pw($request)
  {    
    if (!user::verify_internal($request)) return false;
    $validator = new validator($request);
    if (!$validator->check('email')->is('email')) return false;
    $otp = rand(10042,99999);
    $email = $request['email'];
    $sql = "select attempts from mukonin_audit.user where email_address='$email' and program_id = " . config::$program_id; 
    global $db;
    if (!$db->exists($sql)) {
      echo "!We do not have a user with email address '$email' registered on the system";
      return false;
    }
    
    $attempts = $db->row[0];
    
    if($attempts>3){
      echo "!Account locked. Please contact FPB on (012)345-6789";
      return false;
    }
    
    $db->exec("update mukonin_audit.user set otp = '$otp', otp_time = now()
      where email_address='$email' and program_id = " . config::$program_id);
    $message = "You are currently trying to reset your password. 
      If you have not requested this, please inform the System Adminstrator.<br><br>
      Your One Time Password is : <br><b>$otp</b>. ";
    $subject = "One Time Password";
    $headers = "from: donotreply@fpb.org.za\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    mail($email, $subject, $message, $headers);
  }
  
  static function reset_pw($request)
  {
    if (!user::verify_internal($request)) return false;
    $validator = new validator($request);
    if (!$validator->check('email')->is('email')
      || !$validator->check('password')->is('password', 'match(password2)')) return false;

    $email = $request['email'];
    $otp = $request['otp'];
        
    $sql = "select id, partner_id, email_address, first_name, last_name from mukonin_audit.user
     where email_address='$email' and otp = $otp and active=1 and program_id = ". config::$program_id; 
    global $db;
    if (!$db->exists($sql)) {
      echo '!Invalid OTP or OTP has expired';
      return false;
    }
    $user = new user($db->row); 
    
    $password = addslashes($request['password']);
    $db->exec("update mukonin_audit.user set password = password('$password')
    where email_address='$email' and program_id = " . config::$program_id);
    
    session::redirect('/?c=home');
    global $session;
    $session->restore();
}

  
  static function exists($email, $active = 1, $echo=true)
  {
    $program_id = config::$program_id;
    global $db;
    if (!$db->exists("select id from mukonin_audit.user where email_address = '$email' 
        and program_id = $program_id and active = $active")) return false;
    if ($echo) echo "!The email address already exists"; 
    return true;
  }
  
  static function check($request, $check_email=true)
  {
    if (!user::verify_internal($request)) return false;
    $v = new validator($request); 
    return $v->check('first_name')->is(2)
      && $v->check('last_name')->is(2)
      && $v->check('email')->is('email')
      && (!$check_email || !user::exists($request['email'], 1))
      && $v->check('password', 'Passwords')->is('match(password2)', 'password(6)')
      && $v->check('cellphone')->is('int_tel');
  }

  static function authenticate($email, $passwd)
  {
    $passwd = addslashes($passwd);
    $sql = "select id, partner_id, email_address, first_name, last_name,attempts from mukonin_audit.user
     where email_address='$email' and password=password('$passwd') and active=1 and program_id = ". config::$program_id;         
    
    global $db;
    $success = $db->exists($sql);
    $tries = $db->row[5];
    if($tries >3){
      echo "!Account locked. Please contact FPB on (012)345-6789";
    }
    
    $attempts = $success? '0': 'attempts+1';
    $db->exec("update mukonin_audit.user set attempts = $attempts
     where email_address='$email' and active=1 and program_id = ". config::$program_id);
      
    return $success? $db->row: false;
  }
  
  static function restore($email, $password)
  {
    if (($data = user::authenticate($email, $password)) !== false)
      return new user($data);
    return false;
  }
  
  static function create($partner_id, $email, $password,$title, $first_name, $last_name, $cellphone, $otp)
  {
    $program_id = config::$program_id;   
    $password = addslashes($password);
    $first_name = addslashes($first_name);
    $last_name = addslashes($last_name);
    $title = addslashes($title);
    $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password,title, first_name,last_name, cellphone, otp, otp_time)
      values($program_id,$partner_id, '$email',password('$password'),'$title', '$first_name','$last_name','$cellphone', '$otp', now())";
    
    global $db;
    $id = $db->insert($sql);
    $sql = "insert into mukonin_audit.user_role(user_id,role_code)
      values($id,'reg')";
    $db->exec($sql);
    $password = stripslashes($password);
    $first_name = stripslashes($first_name);
    $last_name = stripslashes($last_name);
    return new user(array($id, $partner_id, $email,$title, $first_name, $last_name, $cellphone, $otp, $partner_id));
  }
  
  static function register($request)
  {    
    if (!user::verify_internal($request)) return;

    if (!user::check($request)) return;
    $request = db::quote($request);
    $title = $request[title];
    $first_name = $request[first_name];
    $last_name = $request[last_name];
    $email = $request[email];
    $password = $request[password];
    $cellphone = $request[cellphone];
    $otp = rand(10042,99999);
    $program_id = config::$program_id;
    $partner_id = (int)$request['partner_id'];
    
    // First check if email already exists
    global $db;
    if (user::exists($email, 0, false)) {
      $sql = "update mukonin_audit.user set password=password('$password'), first_name = '$first_name',last_name= '$last_name',title= '$title', cellphone='$cellphone',
        otp=$otp, otp_time = now(), partner_id = $partner_id where email_address='$email' and program_id = $program_id";
      $db->exec($sql);
      $id = $db->read_one_value("select id from mukonin_audit.user where email_address = '$email' and program_id = $program_id");
      $db->exec("delete from mukonin_audit.user_role where user_id = $id");
      $db->exec("insert into mukonin_audit.user_role(user_id,role_code) values($id,'reg')");
      $password = stripslashes($password);
      $first_name = stripslashes($first_name);
      $last_name = stripslashes($last_name);
      $user = new user(array($id, $partner_id, $email,$title, $first_name, $last_name, $cellphone, $otp));
    }
    else {
      $user = user::create($partner_id, $email, $password, $title,$first_name, $last_name, $cellphone, $otp);
    }

    $db->insert("insert into mukonin_audit.trx(user_id, function_code, object_id)
      values($user->id, 'register', $user->id)");
    //todo: send email and/or sms
    $message = "Good day <br><br>Below is your one time password, required to continue with your application.
				Your One Time Password is <b>$otp</b>.<br><br>
				Regards<br>
				Customer Operations";
    $subject = "One Time Password";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    $headers .= "from: donotreply@fpb.org.za";
    log::debug("Sending OTP email to $email");
    $mail_sent = mail($email, $subject, $message, $headers);
      
  }
    
  static function check_otp($request)
  {

    $otp = $request['otp'];
    
    global $db, $session;
    $id = $session->user->id;
    if (!$db->exists("select id from mukonin_audit.user 
      where id = $id and otp='$otp' and timestampdiff(minute, otp_time, now()) <= 30")) {
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
    $id = $_REQUEST['id'];
    list($email,$username) = $db->read_one_value("select email_address, Concat( first_name, ' ', last_name ) from mukonin_audit.user where id = $id ");
    user::audit('deactivate', $id, "$username($email)");
    global $db;
    $sql = "delete from mukonin_audit.user_role where user_id=$id";
    $db->exec($sql);
    
    $sql = "update  mukonin_audit.user set active=0 where id=$id";
    $db->exec($sql);
   
    global $session;
    $user = $session->user;
    $admin = "$user->first_name $user->last_name <$user->email>";
      
    $message = "Dear $username <br> Administrator would like to inform you that you have been deactivated <br>For more information please log on to <a href='$proto://submit.fpb.org.za/'>submit.fpb.org.za</a> to track the status of your application or call 012 661 0051.<br><br>
        Regards<br>
        Customer Operations";
    $subject = "Rejected Application";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    $headers .= "from:  $admin";
    $mail_sent = mail($email, $subject, $message, $headers);
    log::debug("Sending email to $email: $mail_sent");  
  }
  
  static function update($request)
  {
    $request = table::remove_prefixes($request);
    $fields = array('email_address','first_name', 'last_name', 'otp');
    $values = '';
    foreach($request as $key=>$value) {
      if (in_array($key, $fields))
        $values .= ", $key = '$value'";
    }
    global $db;
    $id = $request['id'];
    if ($id == $user_id)
      $function = 'update_own_details';
    else
      $function = 'update_details';
    user::audit($function, $id, $values);
    
    $sql = "update mukonin_audit.user set ". substr($values,1). " where id = $id";
    $db->exec($sql);
    if (!is_null($request['name']))
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
  
  
  static function audit_trail($request)
  {
    user::verify('audit_trail');
    $headings = array('~Time','~Organisation','~First Name', '~Last Name', '~Email', '~Action', '~Detail');
    $table = new table($headings,table::TITLES | table::ALTROWS | table::FILTERABLE| table::EXPORTABLE);
    
    $table->set_heading("Audit Trail");
    $table->set_options($request);
    $table->show("select t.create_time, p.full_name, u.first_name, u.last_name,
      u.email_address, f.name, t.detail
      from mukonin_audit.trx t 
        join mukonin_audit.function f on t.function_code = f.code
        join mukonin_audit.user u on t.user_id = u.id
        join mukonin_audit.partner p on u.partner_id = p.id");
  }   
  static function update_role($request)
  {
    global $db, $session;
    $user = &$session->user;
    $requestor = "$user->first_name $user->last_name <$user->email>";
    $request = table::remove_prefixes($request);
    $id = $request['id'];
    $role = $request['name'];
    user::audit('update_role', $id, $role);
    
    global $db, $session;
    $user = &$session->user;

    $sql = "update mukonin_audit.user_role set role_code='$role' where user_id = $id";
    $db->exec($sql);
    
    $emails = $db->read_column("select email_address 
                from mukonin_audit.user 
                  where id = $id");  
                   

    $username = $db->read_one_value("select Concat( first_name, ' ', last_name ) AS contact_person from mukonin_audit.user where id = $id ");
    $admin = "$user->first_name $user->last_name <$user->email>";
    $user_role = $db->read_one_value("select name from mukonin_audit.role where code = '$role'"); 
      
     //todo: send email and/or sms
    foreach($emails as $email) {
      $message = "Dear $username <br><br> Administrator would like to inform you that you have been registered and you role is $user_role. <br>
      For more information please log on to <a href='$proto://submit.fpb.org.za/'>submit.fpb.org.za</a> or call 012 661 0051.<br><br>
        Regards<br>
        Administrator";
      $subject = "Approve Registration";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  $admin";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email  from $admin to $email: Status: $mail_sent");       
    }
  }
  
  static function verify_internal($request)
  {
    $email = $request[email];
    if (config::$program_id == 3 && !preg_match('/@(fpb\.(org|gov)\.za|mukoni\.co\.za)$/i', $email)) {
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

    $proto = isset($_SERVER['HTTPS'])?'https':'http';
    
    $emails = $db->read_column("select email_address 
            from mukonin_audit.user u, mukonin_audit.user_role ur
            where u.id = ur.user_id and partner_id = $partner_id and role_code = 'admin' ");
    $link = "$proto://". $_SERVER['SERVER_NAME'] ."/manage_users.html"; //todo: get right http address for production

    //todo: use program's main partner
    if (sizeof($emails) == 0) {
      $emails = $db->read_column("select email_address
        from mukonin_audit.user u, mukonin_audit.user_role r
        where u.partner_id = 3 and r.user_id = u.id and r.role_code = 'admin'");
      $link = "$proto://". $_SERVER['SERVER_NAME'] ."/manage_all_users.html"; //todo: get right http address for production
    }  
    
    
    
    foreach($emails as $email) {
      $message = "$requestor would like to register as a user of the Online Submission System. Please click <a href=\"$link\">here</a> to give access to user.";
      $subject = "Approve Registration";
      $headers  = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from: $requestor";
      log::debug("Sending email for $requestor to $email");
      $mail_sent = mail($email, $subject, $message, $headers);
      
    }
    $id = $session->user->id;
    $db->exec("update mukonin_audit.user set active = 1 where id = $id");
  
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
    $user_id = $session->user->id;
    $sql = "select id, u.create_time, u.email_address, u.first_name, u.last_name, r.name role,
              case u.id
              when $user_id then 'edit'
              else 'delete,edit' 
            end as actions
      from mukonin_audit.user u, mukonin_audit.user_role ur, mukonin_audit.role r
      where u.id=ur.user_id and r.code = ur.role_code 
      and partner_id = $partner_id and active=1 and r.program_id = ". config::$program_id;    
            
    $titles = array('#id','~Time', '~Email Address|edit','~First Name|edit','~Last Name|edit','Role|edit=list:user/roles','');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE);
    $table->set_heading("Manage Users");
    $table->set_key('id');
    $table->set_saver("/?a=user/update");
    $table->set_deleter('/?a=user/deactivate');
    $table->set_options($request);
    $table->show($sql);
  }

  static function manage_all($request)
  {  
    user::verify('manage_all_users');
    
    global $session;
    $partner_id = $session->user->partner_id;
    $user_id = $session->user->id;
    $sql = "select u.id, u.create_time, p.full_name, email_address, first_name, last_name, r.name role,
              case u.id
              when $user_id then 'edit'
              else 'delete,edit' 
            end as actions
      from mukonin_audit.user u, mukonin_audit.user_role ur, mukonin_audit.role r, mukonin_audit.partner p
      where u.id=ur.user_id and r.code = ur.role_code and u.partner_id = p.id
      and active=1 and r.program_id = ". config::$program_id;    
            
    $titles = array('#id','~Time', '~Company', '~Email Address|edit','~First Name|edit','~Last Name|edit','Role|edit=list:user/roles','');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage All Users");
    $table->set_key('id');
    $table->set_saver("/?a=user/update");
    $table->set_deleter('/?a=user/deactivate');
    $table->set_options($request);
    $table->show($sql);
  }
  
  static function track_reg($request)
  {
    user::verify('track_reg');
    global $session;
    $user_id = $session->user->id;
    $headings = array('~Time','~First Name', '~Last Name', '~Email', '~Action', '~Role');
    $table = new table($headings,table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    
    $table->set_heading("User Registration Status History");
    $table->set_options($request);
    $table->show("select t.create_time, u.first_name, u.last_name, u.email_address,f.name action, r.name role
      from mukonin_audit.trx t 
        join mukonin_audit.user o on t.object_id = o.id
        join mukonin_audit.function f on t.function_code = f.code
        join mukonin_audit.user u on t.user_id = u.id
        left join mukonin_audit.role r on r.code = t.detail
      where o.id = $user_id"); 
  }
}
?>
