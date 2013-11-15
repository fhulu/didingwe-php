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


class feature_exception extends Exception {};
class feature
{
   
  static function check($request)
  {
    
    $v = new validator($request); 
    //$v->check('title')->is(2);
    //$v->check('priority')->is('in(mukonin_audit.priority.code)'); 
    $v->check('reason')->is(30);
    return $v->valid();
  }

  static function priorities()
  {
    return select::add_db('select code, name from mukonin_audit.priority order by no','','','--Select Priority--');
  }
 
  static function save($request)
  {    
    global $db,$session;
    
    $user_id= $session->user->id;
    $partner_id= $session->user->partner_id;
    $title = $request[title];
    $type = $request[type];
    $reason = $request[reason];  
    $program_id = config::$program_id;
  
    //TO DO create a function in DB
     //user::audit('update_role', $id, $role);
    
    $sql = "INSERT into mukonin_audit.feature_request(program_id,user_id,partner_id,title,description,priority,status_code)
            VALUES($program_id,$user_id,$partner_id,'$title','$reason','med','pend')";
    $db->exec($sql);
      
  }
 
  static function update_priority($request)
  {
    global $db, $session;
    $user_id = $session->user->id;
    $id = $request['id'];
    $priority = $request['priority'];
    //user::audit('update_role', $id, $priority);
    
    global $db;
   
    $sql = "update mukonin_audit.feature_request set priority='$priority' where id = $id";
    $db->exec($sql);
       
   }
   
  
  static function manage_features($request)
  {
    //user::verify('manage_features');

    global $session;
    $user = $session->user;
    $program_id = config::$program_id;
    $partner_id = $request['partner_id'];
    if ($partner_id == '') $partner_id = $user->partner_id;
    $titles = array('#id','Time','~Company','~Requestor','~Request','~Reason','~Priority|name=priority|edit=list:?feature/priorities','~Status','~Status Reason','Action');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage Feature Request");
    $table->set_options($request);$table->set_key('id');
    $table->set_row_actions('edit,Approve,Reject');
    $table->set_saver('/?a=feature/update_priority');
    $table->set_options($request);
    $sql = "select * from (select fr.id,fr.create_time,full_name,concat(first_name,' ',last_name) requestor,fr.title,fr.description,pr.name priority,s.description status, r.description reason,
       case s.description
              when 'pending' then 'edit,Approve,Reject'
              when 'approved' then 'edit,Reject'
              when 'rejected' then 'edit,Approve'
              else '' 
            end as actions
            from mukonin_audit.feature_request fr
            join mukonin_audit.user u on u.id = fr.user_id
            join mukonin_audit.partner p on p.id = fr.partner_id
            join mukonin_audit.status s on s.code = fr.status_code
            left join mukonin_audit.priority pr on pr.code = fr.priority
            left join mukonin_audit.reason r on r.code = fr.status_reason_code) tmp where 1=1";
    $table->show($sql);
  }
  static function approve($request)
  {
    global $db;   
    $id = $request[id];
    
    list($user_id,$partner_id,$title)= $db->read_one("select user_id, partner_id,title from mukonin_audit.feature_request where id = $id");
    user::audit("appr_feature",$partner_id, $title,'partner');
 
    $sql = "update mukonin_audit.feature_request
            set status_code='appr',status_reason_code=''
            where id = $id";
    $db->exec($sql);
    
    $rows = $db->read("select email_address,Concat( first_name, ' ', last_name ) AS contact_person
                       from mukonin_audit.user u
                      where id=$user_id");
   
    $proto = isset($_SERVER['HTTPS'])?'https':'http';
    foreach($rows as $row)
    { 
      $email = $row['email_address'];    
      $username = $row['contact_person'];
      $message = "Good day $username<br><br>
        Your application for registration as a <b>$title</b> has been approved.
        The original copy of your license will be posted to you within 3 Weeks.
        <br>For more information please log on to <a href='$proto://submit.fpb.org.za/'>submit.fpb.org.za</a> to track the status of your application or call 012 661 0051.<br><br>
        Regards<br>
        Customer Operations";
      $subject = "Approve Application";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  donotreply@fpb.org.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username");
    }
  }
  
    static function ask($request)
  {
    //user::verify('rate_game');
    $titles = array('#No.','Please tick the appropriate feedback type','');
    $table = new table($titles, table::TITLES | table::ALTROWS);
    $table->set_key('number');
    $table->set_options($request);
    $table->set_callback(function(&$row, $index, &$attr) {  
      $code = $row['code'];    
      $input = "input name='type' type=radio value=";
      $row['yes'] = "<$input$code>";
   
    });
    $table->show("select code,description, '' yes
      from mukonin_audit.feedback_type");
  }
  static function reject($request)
  { 
    
    global $db;
   
    $request = db::quote($request);
    $id = $request['id'];
    $status_code = substr(strtolower($request['code']),0,4);
    $reason_code = $request['reason'];
    $title = $db->read_one_value("select title from mukonin_audit.feature_request where id = $id");
    $status = $db->read_one_value("select description from mukonin_fpb.status where code = '$status_code'"); 
    $reason = $db->read_one_value("select description from mukonin_fpb.reason where code = '$reason_code'"); 
    user::audit($status_code."_feature_request",$id,"Title: $title, Reason: $reason");
    
    $sql = "update mukonin_audit.feature_request
              set status_code='$status_code',
             status_reason_code='$reason_code'
            where id = $id";
    
    $db->exec($sql);  
        
  
    
    $rows = $db->read("select email_address,Concat( first_name, ' ', last_name ) AS contact_person
        from mukonin_audit.user u,mukonin_audit.feature_request fr
        where u.id = fr.user_id and fr.id = $id");

    $proto = isset($_SERVER['HTTPS'])?'https':'http';
    foreach($rows as $row)
    { 
      $username = $row['contact_person'];
      $email = $row['email_address'];
      $distributor = $row['type'];
      $message = "Good day<br><br>
				  Your application for registration as a $distributor has been <b>$status</b> due to <b>$reason</b>.<br>
				  For more information please log on to <a href='$proto://submit.fpb.org.za/'>submit.fpb.org.za</a> to track the status of your application or call 012 661 0051.<br><br>
				  Regards<br>
				  Customer Operations";
      $subject = "FPB Application Status";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  donotreply@fpb.org.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username<$email>");    
    }    
  }
  
}
?>

