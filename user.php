<?php
require_once('session.php');

require_once('db.php');
require_once('config.php');
require_once('table.php'); 
require_once('select.php');
require_once('validator.php');
require_once('select.php');
require_once('curl.php');
require_once('errors.php');


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
  var $title;
  var $functions;
  var $groups;
  function __construct($data)
  {
    list($this->id, $this->partner_id, $this->email,$this->title, $this->first_name, $this->last_name, $this->cellphone) = $data;
   
  }

  static function default_functions()
  {
    global $db;
    
    return $db->read_column("select distinct function_code from mukonin_audit.role_function
    where role_code in ('base', 'unreg') 
    and program_id=" .config::$program_id);
  }
  
  function reload()
  {
    $this->load_groups();
    $this->load_roles();
    $this->load_functions();
    session::register($this);
    $this->force_audit('login');
  }

  function load_groups()
  {
    global $db;
    $this->groups = $db->read_column("select group_id from mukonin_audit.group_users where user_id = $this->id");
    //todo: take care of group hierachy
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
    
    $program_id = config::$program_id;
    $groups = $db->read_column("select group_code from mukonin_audit.group_partner where partner_id = $this->partner_id and program_id=$program_id");
    $db->lineage($groups, "code", "parent_code", "mukonin_audit.partner_group", "and program_id=$program_id");
    $groups = implode("','", $groups);
    $functions = $db->read_column(
      "select distinct function_code from mukonin_audit.role_function where role_code in('$roles')
        and program_id = $program_id
        and function_code in 
        (select distinct function_code from mukonin_audit.partner_group_function where group_code in ('$groups') and program_id = $program_id)");
    $base_functions = $db->read_column("select distinct function_code from mukonin_audit.role_function where role_code = 'base'
      and program_id=$program_id"); 
    $this->functions = array_merge($functions, $base_functions);
  }
  
 
  function assign_role($role)
  {
    if (is_array($this->roles)) 
      $this->roles = array_merge($this->roles, array($role));
    else
      $this->roles = array($role);
  }
  
  
  static function change_email($request)
  {        
    global $db, $session,$errors;
    $email = addslashes($request[email]);
    if($email != $session->user->email 
       && $db->exists("select first_name from mukonin_audit.user where email_address= '$email'")) {
     echo "!This email address already exist. Please try another email address";
      return;
     
    }
     
    $validator = new validator($request);
    if (!$validator->check('email')->is('email')) return;
            
    $otp = rand(10042,99999);
    $email = $request['email'];
    
    $user_id = $session->user->id;
    $db->exec("update mukonin_audit.user set otp = '$otp', otp_time = now()
      where id='$user_id' and program_id = " . config::$program_id);
    $message = "Good day<br><br>You are currently trying to change your email address. 
                                                                If you have not requested this, please inform the System Adminstrator.<br><br>
                                                                Your One Time Password is : <b>$otp</b>. <br><br>
                                                                Regards<br>
                                                                Customer Operations";
    $subject = "One Time Password";
    $headers = "from: donotreply@fpb.org.za\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    mail($email, $subject, $message, $headers);
  }
  
  static function update_otp($email)
  {
    global $db;
    $otp = rand(10042,99999);
    $db->exec("update mukonin_audit.user set otp = '$otp', otp_time = now()
      where email_address='$email' and program_id = " . config::$program_id);
    
    return $otp;
  }
  static function send_otp($email, $otp)
  {
    if (in_array('sms', config::$otp_methods)) user::sms_otp($email,$otp);
    if (in_array('email', config::$otp_methods)) user::email_otp($email, $otp);
  }
  
  static function email_otp($email, $otp)
  {
    $program_name = config::$program_name;
    $message = "Good day<br><br>You are currently trying to reset your password for $program_name. 
                                                                If you have not requested this, please inform the System Adminstrator.<br><br>
                                                                Your One Time Pin is : <br><b>$otp</b> <br><br>
                                                                Regards<br>
                                                                Customer Operations";
    user::send_email($email, "$program_name: One Time Pin", $message);
  }
  
  static function update_attempts($email)
  {
    global $db;
    $db->exec("update mukonin_audit.user set attempts=attempts+1
    where email_address='$email' and program_id = " . config::$program_id);    
  }
  
  static function unlocked($email)
  {
    global $db;
    list($attempts, $contact_person, $contact_email) = $db->read_one("select attempts, contact_person, contact_tel"
            . " from mukonin_audit.partner p, mukonin_audit.user u "
            . " where u.partner_id = p.id and u.email_address = '$email' and u.program_id = ".config::$program_id);
    if ($attempts < 3) return true;
    if ($contact_person == '') $contact_person = config::$support_company;
    if ($contact_tel == '') $contact_tel = config::$support_tel;
    return errors::q('email', "!Account locked. Please contact $contact_person on $contact_tel.");
 
  }
  static function start_reset_pw($request)
  {    
    if (!user::verify_internal($request)) return false;
    $v = new validator($request);
    $v->check('email')->is('optional(cellphone)','email');
    $v->check('cellphone')->is('optional(email)','telephone');
    if (!$v->valid()) return false;
    
    $cellphone = addslashes($request['cellphone']);
    $email = addslashes($request['email']);
    global $db;
    $email = $db->read_one_value("select email_address, cellphone from mukonin_audit.user "
            . "where (email_address='$email' or cellphone = '$cellphone') and program_id = " . config::$program_id);
    if ($email == '') 
      return $v->report('email', "!We do not have a user with supplied details registered on the system");
        
    if (!user::unlocked($email)) return false;
    $otp = user::update_otp($email);
    user::send_otp($email, $otp);
  }
  
  static function reset_pw($request)
  {
    if (!user::verify_internal($request)) return false;
    $v = new validator($request);
    $v->check('otp')->titled('OTP')->is('numeric','at_least(5,digits)');
    $v->check('email')->is('email');
    $v->check('password')->is('password', 'match(password2)');
    if (!$v->valid()) return false;
    $email = addslashes($request['email']);
        
    if (!user::unlocked($email)) return false;

    global $db;
    list($email,$otp, $otp_time) = $db->read_one("select email_address, otp,timestampdiff(minute, otp_time, now()) from mukonin_audit.user
     where email_address='$email' and active=1 and program_id = ". config::$program_id);
    if ($email == '') 
      return errors::q('email', "!We do not have a user with supplied details registered on the system");
    
    if ($otp != $request['otp']) { 
      user::update_attempts($email);
      return errors::q('otp', 'Incorrect OTP');
    }   
    if ($otp_time < config::$otp_expiry)  
      return $errors->q('otp', 'OTP has expired. Please go back and request a new PIN');  
    
    $password = addslashes($request['password']);
    $db->exec("update mukonin_audit.user set password = password('$password'), attempts=0
    where email_address='$email' and program_id = " . config::$program_id);
}

  
  static function change_password($request)
  {
    global $session,$db;
    $user = &$session->user;
    $user_id = $user->id; 
    
    
    $validator = new validator($request);
    $validator->check('email')->is('email');
    $validator->check('password')->is('password', 'match(password2)');
    if ($validator->valid()) return false;
    
    $password = $request['password'];
    log::debug('Password is'.$password);
    $db->exec("update mukonin_audit.user set password = password('$password')
    where id='$user_id' and program_id = " . config::$program_id);
    
  }
  static function exists($email, $active = 1, $echo=true)
  {
    $program_id = config::$program_id;
    global $db, $errors;
    if (!$db->exists("select id from mukonin_audit.user where email_address = '$email' 
        and program_id = $program_id and active = $active")) return false;
    if ($echo) $errors->add('email', "The email address already exists"); 
    return true;
  }
  
  static function check($request, $check_email=true)
  {
    user::verify_internal($request);
    $v = new validator($request); 
    $v->check('first_name')->is(2);
    $v->check('last_name')->is(2);
    $v->check('email')->is('email');
    if($check_email && user::exists($request['email'], 1))
      $v->report('email', '!Email address already exists');
    
    $v->check('password', 'Passwords')->is('match(password2)', 'password(6)');
    $v->check('cellphone')->is('int_tel');
    return $v->valid();
  }
  static function authenticate($email, $passwd, $is_passwd_plain=true)
  {
    if ($is_passwd_plain)
      $passwd = "password('".addslashes($passwd)."')";
    else
      $passwd = "'$passwd'";
    $email = addslashes($email);
    $sql = "select id, partner_id, email_address,title, first_name, last_name,cellphone,attempts from mukonin_audit.user
     where email_address='$email' and password=$passwd and active=1 and program_id = ". config::$program_id;         
    
    global $db;
    $success = $db->exists($sql);
    $tries = $db->row[6];
    if ($success) 
      $attempts = 0;
    else {
      if($tries >3) echo "!Account locked. ";
      $attempts = 'attempts+1';
    }
    $db->exec("update mukonin_audit.user set attempts = $attempts
     where email_address='$email' and active=1 and program_id = ". config::$program_id);
  
    return $success? $db->row: false;
  }
  
  static function restore($email, $password, $is_password_plain=true)
  {
    if (($data = user::authenticate($email, $password)) === false) return false;
    
    $user = new user($data);
    $user->reload();
    return $user;
  }
  
  static function sms($cellphone,$partner_id, $user_id, $message)
  {
    $program_name = config::$program_name;
    $sms  = urlencode("$message");
    $reference = "$program_name-$partner_id-$user_id";
    $url = "http://iweb.itouchnet.co.za/Submit?UserId=MUKONIHTTP&Password=SDMRWRKC&PhoneNumber=$cellphone&Reference=$reference&MessageText=$sms";
    $curl = new curl();
    $result = $curl->read($url);  
    log::debug("CURL RESULT: $result");
  }

  static function sms_otp($email, $otp)
  {
    $program_name = config::$program_name;
    global $db;
    list($cellphone, $parnter_id, $user_id) = $db->read_one("select cellphone, partner_id, id"
            . " from mukonin_audit.user where email_address = '$email')");
    user::sms($cellphone, $partner_id, $user_id, "Your One Time Pin for $program_name is $otp");
  }
   
  static function send_email($email, $subject, $message, $from=null)
  {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    if(is_null($from)) $from = config::$support_email;
    $headers .= "from: $from";
    log::debug("Sending email to $email");
    mail($email, $subject, $message, $headers);
  }
  
  static function create($partner_id, $email, $password,$title, $first_name, $last_name, $cellphone, $otp)
  {
    $program_id = config::$program_id;   
    $password = addslashes($password);
    $first_name = addslashes($first_name);
    $last_name = addslashes($last_name);
    $title = addslashes($title);
    if($program_id==7){
      
      $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password,title, first_name,last_name, cellphone,active, otp, otp_time)
      values($program_id,$partner_id, '$email',password('$password'),'$title', '$first_name','$last_name','$cellphone',1, '$otp', now())";
    }
    else
    {
      $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address, password,title, first_name,last_name, cellphone, otp, otp_time)
      values($program_id,$partner_id, '$email',password('$password'),'$title', '$first_name','$last_name','$cellphone', '$otp', now())";
    }
    global $db;
    $id = $db->insert($sql);
    $sql = "insert into mukonin_audit.user_role(user_id,role_code)
      values($id,'reg')";
    $db->exec($sql);
    $password = stripslashes($password);
    $title = stripslashes($title);
    $first_name = stripslashes($first_name);
    $last_name = stripslashes($last_name);
    return new user(array($id, $partner_id, $email,$title, $first_name, $last_name, $cellphone, $otp, $partner_id));
  }
  
  static function register($request, $is_admin=false)
  {    
    if (!$is_admin && !user::check($request)) return;
    $request = db::quote($request);
    $title = $request[title];
    $first_name = $request[first_name];
    $last_name = $request[last_name];
    $email = $request[email];
    if ($email  == '') $email = $request['email_address'];
    $password = $request[password];
    $cellphone = $request[cellphone];
    $otp = rand(10042,99999);
    $program_id = config::$program_id;
    $partner_id = (int)$request['partner_id'];
    $role = $request['role'];
    if ($role == '') $role = 'reg';
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
    if ($is_admin) return;


    user::sms_otp($cellphone, $partner_id, $user->id, $otp);
    $user->reload();
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
    $headers .= "from: ".config::$support_email;
    log::debug("Sending OTP email to $email");
    $mail_sent = mail($email, $subject, $message, $headers);
      
  }
    
  static function info()
  {
    global $session;
    $user = $session->user;
    echo json_encode(array(
      'email'=>$user->email,
      'title'=>$user->title, 
      'first_name'=>$user->first_name,
      'last_name'=>$user->last_name, 
      'cellphone'=>$user->cellphone            
    ));
  }
  static function check_otp($request)
  {

    $otp = $request['otp'];
    
    global $db, $session;
    $id = $session->user->id;
    $v = new validator($request); 

    if (!$db->exists("select id from mukonin_audit.user 
      where id = $id and otp='$otp' and timestampdiff(minute, otp_time, now()) <= 30")) {
        $v->report("otp", "!Invalid OTP or OTP has expired");
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
  
  static function deactivate($request)
  {
    global $db;
    $id = $request[id];
    $request = table::remove_prefixes($request); 
    list($email,$username) = $db->read_one("select email_address, Concat( first_name, ' ', last_name ) from mukonin_audit.user where id = $id ");
    user::audit('deactivate', $id, "$username($email)");
    
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
    $fields = array('email_address','first_name', 'last_name','title', 'cellphone','otp');
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
    
    $email= $request['email_address'];
    $program_id = config::$program_id;
    list($old_id, $old_partner_id, $active) = $db->read_one("select id, partner_id,active from mukonin_audit.user 
      where email_address = '$email' and program_id = $program_id");
    if ($old_partner_id != 0 && $old_id != $id && $active != 0)  {
      echo '!Email Address already exists';
      return;
    }
    if ($old_id != '' || $active != 0) { 
      $time = time();
      $db->exec("update mukonin_audit.user set email_address = 'overwritten-$time-$email' where id = $old_id");
    }
    
    $sql = "update mukonin_audit.user set ". substr($values,1). " where id = $id";
    $db->exec($sql);    
    if (!is_null($request['role'])){
      user::update_role($request);
    }
    $passwd = $request['password'];
    if ($passwd != '**********')
      $db->exec("update mukonin_audit.user set password = password('$passwd') where id = $id");
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

  static function force_audit($function, $object='', $detail='',$type='')
  {
    global $db, $session;
    $user = $session->user;
    $detail = stripslashes($detail);
    $type = stripslashes($type);
    log::info("FUNCTION: $function USER: $user->id OBJECT: $object TYPE: $type DETAIL: $detail");
    if (is_numeric($object)) {
      $object_id = $object; 
      $object_code = 'null';
    }
    else {
      $object_code = "'$object'";
      $object_id = 'null';
    }
    $detail = addslashes($detail);
    $db->insert("insert into mukonin_audit.trx(user_id, function_code, object_id, object_code, detail, object_type)
      values($user->id, '$function', $object_id, $object_code, '$detail', '$type')");
  }
  
  static function audit($function, $object='', $detail='',$type='')
  {
    user::verify($function);
    user::force_audit($function, $object, $detail, $type);
  }
  static function audit_trail($request)
  {
    user::verify('audit_trail');
    $headings = array('~Time','~Organisation','~First Name', '~Last Name', '~Email', '~Action', '~Detail');
    $table = new table($headings,table::TITLES | table::ALTROWS | table::FILTERABLE| table::EXPORTABLE);
    
    $table->set_heading("Audit Trail");
    $table->set_options($request);
    $program_id = config::$program_id;
    $table->show("select distinct t.create_time, p.full_name, u.first_name, u.last_name,
      u.email_address, f.name, t.detail
      from mukonin_audit.trx t 
        join mukonin_audit.function f on t.function_code = f.code
        join mukonin_audit.user u on t.user_id = u.id
        join mukonin_audit.partner p on u.partner_id = p.id and u.program_id = $program_id");
  }

  static function partner_audit_trail($request)
  {
    user::verify('partner_audit_trail');
    $headings = array('~Time','~Organisation','~First Name', '~Last Name', '~Email', '~Action', '~Detail');
    $table = new table($headings,table::TITLES | table::ALTROWS | table::FILTERABLE| table::EXPORTABLE);
    
    $table->set_heading("Audit Trail");
    $table->set_options($request);
    $program_id = config::$program_id;
    global $session;
    $partner_id = $session->user->partner_id;
    $table->show("select distinct t.create_time, p.full_name, u.first_name, u.last_name,
      u.email_address, f.name, t.detail
      from mukonin_audit.trx t 
        join mukonin_audit.function f on t.function_code = f.code
        join mukonin_audit.user u on t.user_id = u.id
        join mukonin_audit.partner p on u.partner_id = p.id and (u.partner_id = $partner_id or t.object_id = $partner_id and t.object_type = 'partner')
      and u.program_id = $program_id");
  }
   
  static function update_role($request)
  {
    global $db, $session;
    $user = &$session->user;
    $requestor = "$user->first_name $user->last_name <$user->email>";
    $request = table::remove_prefixes($request);
    $id = $request['id'];
    $role = $request['role'];
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
    $program_id = config::$program_id;
    if($program_id==3){
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
   else{
     foreach($emails as $email) {
        $message = "Dear $username <br><br> Administrator would like to inform you that you have been registered and you role is $user_role. <br>
        For more information please log on to <a href='http://mampo.qmessenger.mukoni.net'>mampo.qmessenger.mukoni.net</a> or call 021 661 0051.<br><br>
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
   
 }
  
  static function verify_internal($request)
  {
/*
    $email = $request[email];
    if (config::$program_id == 3 && !preg_match('/@(fpb\.(org|gov)\.za|mukoni\.co\.za|microsoft\.com|ea\.com|absa\.co\.za)$/i', $email)) { 
      global $errors;
      return $errors->add('email', "Application not yet released to the public. An announcement will be made soon.");
    } */
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
      $message = "Good day<br><br>$requestor would like to register as a user of the FPB Online. Please click <a href=\"$link\">here</a> to give access to user.<br><br>
                                                                Regards<br>
                                                                Customer Operations";
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
     
  static function titles()
  {
    echo select::add_items(",--Select Title--|Mr.|Mrs.|Ms|Miss|Dr|Prof|Sir|Madam",'');
  }
   static function roles()
  {
    $program_id = config::$program_id;
    echo select::add_db("select code, name from mukonin_audit.role where code not in('unreg','base') and program_id=$program_id order by name desc");
  }

  static function groups()
  {
    global $session;
    $user = &$session->user;
    echo select::add_db("select id, name from mukonin_audit.user_group where partner_id = $user->partner_id and active=1");
  }

  static function add($request)
  {
    $request = table::remove_prefixes($request);
    global $db, $session;
    $user = &$session->user;
    $requestor = "$user->first_name $user->last_name";
    $email = $request['email_address'];
    $first_name= $request['first_name'];
    $last_name= $request['last_name'];
    $cellphone= $request['cellphone'];
    $title= $request['title'];
    $code= $request['role'];
    $password=$request['password'];
    $partner_id = $request['partner_id'];
    if ($partner_id == '')
      $partner_id=$user->partner_id;
    $program_id = config::$program_id;
    $role = $db->read_one_value("select name from mukonin_audit.role where code ='$role' and program_id = $program_id" );
    $program_name = $db->read_one_value("select description from mukonin_audit.program where id = $program_id");
     log::debug('partner id is '.$partner_id);
       $sql = "insert into mukonin_audit.user(program_id, partner_id, email_address,title, first_name,last_name, cellphone,password,active)
          values($program_id,$partner_id, '$email','$title', '$first_name','$last_name','$cellphone',password('$password'),1)";
       $user_id=$db->insert($sql);


       $sql = "insert into mukonin_audit.user_role(user_id, role_code)
          values($user_id,'$code')";
       $db->exec($sql);

       $message = "Good day<br><br>$requestor has added you as $role on $program_name. Your username is <b>$email</b> and password is <b>$password</b>.";  
       $subject = "Added to the system";
       $headers  = "MIME-Version: 1.0\r\n";
       $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
       $headers .= "from: $requestor<$user->email>";
       $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sent email from $requestor to $email, $password: Result: $mail_sent");
  } 

  static function add_user($request)
  {
    return user::add($request);
  }
  static function manage($request)
  {  
    user::verify('manage_users');
    
    global $session;
    $user = $session->user;
    $program_id = config::$program_id;
    $partner_id = $request['partner_id'];
    if ($partner_id == '') $partner_id = $user->partner_id;
    $show_groups = $request['show_groups'];
    $sql = "select * from (select u.id, u.create_time, u.email_address, u.first_name, u.last_name,u.cellphone,'**********', r.name role";
    if ($show_groups == 1) 
      $sql .=", (select group_concat(ug.name) from mukonin_audit.user_group ug join mukonin_audit.group_users gu on ug.id = gu.group_id where gu.user_id = u.id and ug.active=1) groups ";
    $sql .= ",
                case u.id
                when $user->id then 'edit'
                else 'delete,edit' 
              end as actions
        from mukonin_audit.user u left join mukonin_audit.user_role ur on u.id = ur.user_id 
             left join mukonin_audit.role r on r.code = ur.role_code
        where u.partner_id = $partner_id and u.active=1 and r.program_id = ". config::$program_id . ") tmp where 1=1";    

      $titles = array('#id','~Time', '~Email Address|name=email_address|edit','~First Name|name=first_name|edit','~Last Name|name=last_name|edit','Cellphone|name=cellphone|edit','~Password|edit|name=password','Role|name=role|edit=list:?user/roles');
      if ($show_groups==1) $titles[] = '~Group(s)';//|name=groups|edit=multi:?user/groups';
      $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
      $table->set_heading("Manage Users");
      $table->set_key('id');
      $table->set_saver("index.php?a=user/update");
      $table->set_adder("index.php?a=user/add&partner_id=$partner_id");
      $table->set_deleter('index.php?a=user/deactivate');
      unset($request['show_groups']);
      unset($request['partner_id']);
      $table->set_options($request);
      $table->show($sql);
  }

  static function members($request)
  {
    user::verify('manage_users');

    $program_id = config::$program_id;
    $group_id = $request['group_id'];
    $sql = "select * from (select u.id, u.email_address, u.first_name, u.last_name,u.cellphone,r.name role, gu.create_time, 'delete' actions
        from mukonin_audit.user u 
           join mukonin_audit.group_users gu on u.id = gu.user_id and group_id = $group_id 
           left join mukonin_audit.user_role ur on u.id = ur.user_id 
           left join mukonin_audit.role r on r.code = ur.role_code and r.program_id = $program_id
        where u.active = 1
         ) tmp where 1=1";    

      $titles = array('#id', '~Email Address|name=email_address|edit','~First Name','Last Name','Cellphone','~Role','~Time Added','');
      $table = new table($titles, table::TITLES | table::ALTROWS );
      $table->set_key('id');
      $table->set_searcher("index.php?a=user/search&group_id=$group_id", "/?a=user/attach&group_id=$group_id");
      $table->set_deleter("index.php?a=user/detach&group_id=$group_id");
      unset($request['group_id']);
      $table->set_options($request);
      $table->show($sql);
  }


  static function search($request)
  { 
    $request = table::remove_prefixes($request);
    global $session;
    $user = $session->user;
    $user_sql = "select id, email_address,first_name, last_name,cellphone
        from mukonin_audit.user u 
        where partner_id = $user->partner_id and active=1";
    $group_id = $request['group_id'];
    if ($group_id != '') 
      $user_sql .= " and id not in (select user_id from mukonin_audit.group_users where group_id = $group_id)";
    if (isset($request['groups'])) {
      $group_sql = "select id, name `group` from mukonin_audit.user_group where partner_id = $user->partner_id and active = 1";
      table::search($request, $group_sql, $user_sql); 
    } 
    else
      table::search($request, $user_sql); 
	
  }
  static function manage_groups($request)
  {
    user::verify('manage_user_groups');

    global $session;
    $user = $session->user;
    $program_id = config::$program_id;
    $partner_id = $request['partner_id'];
    if ($partner_id == '') $partner_id = $user->partner_id;
    $titles = array('#group_id','~Time','~Name|name=name|edit','Size');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage User Groups");
    $table->set_key('group_id');
    $table->set_saver("index.php?a=user/rename_group");
    $table->set_adder("index.php?a=user/add_group&partner_id=$partner_id");
    $table->set_deleter('index.php?a=user/deactivate_group');
    $table->set_expandable('index.php?a=user/members','table');
    unset($request['partner_id']);
    $table->set_options($request);
    $sql = "select * from (select id group_id, create_time, name, (select count(1) from mukonin_audit.group_users gu, mukonin_audit.user u where group_id = g.id and u.id = gu.user_id and u.active=1) size 
         from mukonin_audit.user_group g where partner_id = $partner_id and active = 1) tmp where 1=1";
    $table->show($sql);
  }
 
  static function attach($request)
  {
    $request = table::remove_prefixes($request);
    $user_id = $request['id'];
    $group_id = $request['group_id'];
    global $db;
    $db->exec("insert mukonin_audit.group_users(user_id,group_id) values( $user_id, $group_id)");
  }


  static function detach($request)
  {
    $request = table::remove_prefixes($request);
    $user_id = $request['id'];
    $group_id = $request['group_id'];
    global $db;
    $db->exec("delete from mukonin_audit.group_users where user_id = $user_id and group_id = $group_id");
  }

  static function add_group($request)
  {
    $request = table::remove_prefixes($request);
    $name = $request['name'];
    $partner_id = $request['partner_id'];
    global $db;
    $db->exec("insert into mukonin_audit.user_group(name, partner_id) values('$name', $partner_id)");
  } 

  static function rename_group($request)
  {
    $request = table::remove_prefixes($request);
    $group = $request['name'];
    $id = $request['group_id'];
    global $db;
    $db->exec("update mukonin_audit.user_group set name = '$group' where id = $id");
  }

  static function deactivate_group($request)
  {
    $request = table::remove_prefixes($request);
    $id = $request['group_id'];
    global $db;
    $db->exec("update mukonin_audit.user_group set active = 0, name=concat(name,'-deleted')  where id = $id");
  }

  static function manage_all($request)
  {  
    user::verify('manage_all_users');
    
    global $session;
    $partner_id = $session->user->partner_id;
    $user_id = $session->user->id;
    $program_id = config::$program_id;
    $sql = "select * from (select u.id, u.create_time, p.full_name, email_address, u.first_name, u.last_name, u.cellphone, '**********', r.name role,
              case u.id
              when $user_id then 'edit'
              else 'delete,edit' 
            end as actions
      from mukonin_audit.user u, mukonin_audit.user_role ur, mukonin_audit.role r, mukonin_audit.partner p
      where u.id=ur.user_id and r.code = ur.role_code and u.partner_id = p.id
      and u.active=1 and u.program_id = $program_id and r.program_id = $program_id ) tmp where 1=1";    
            
    $titles = array('#id','~Time', '~Company', '~Email Address|edit','~First Name|edit','~Last Name|edit','~Cellphone|edit','~Password|edit|name=password','~Role|edit=list:?user/roles','');
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

  static function access_list()
  {
    echo select::add_items("u,Private(Only owner have access)|g,My Groups(Everyone in the same group as owner))|p,Everyone (Everyone in the organisation)");
  }
  
  function get_access_filter_sql($table,$conjuctor='and')
  {
    if (in_array('admin', $this->roles)) return null;
    $groups = implode(',',$this->groups);
    $sql .= " $conjuctor ($table.access = 'p' or $table.user_id = $this->id";
    if ($groups != '')
      $sql .= " or ($table.access = 'g' and $table.user_id in (select gu.user_id from mukonin_audit.group_users gu where gu.group_id in ($groups)))";

    return $sql .= ')';
 
  }  


  
}
?>
