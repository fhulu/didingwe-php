<?php
require_once('session.php');
require_once('db.php');
require_once('config.php');
require_once('table.php'); 
require_once('validator.php');
require_once('select.php');
require_once('errors.php');


class feature_exception extends Exception {};
class feature
{
   
  static function check($request)
  {
    
    $v = new validator($request); 
    $v->check('title')->is(2); 
    $v->check('description')->is(30);
    return $v->valid();
  }

  static function priorities()
  {
    return select::add_db('select code, name from mukonin_audit.priority order by no','','','--Select Priority--');
  }
 
  static function status()
  {
    return select::add_db('select code, description from mukonin_audit.status where code !="decl"','','','--Select Status--');
  }
  static function save($request)
  {    
    global $db,$session;
    
    $user_id= $session->user->id;
    $partner_id= $session->user->partner_id;
    $title = $request[title];;
    $description = $request[description];  
    $program_id = config::$program_id; 
    $program_owner = config::$program_owner;
      
    $sql = "INSERT into mukonin_audit.feature_request(program_id,user_id,partner_id,title,description,priority,status_code)
            VALUES($program_id,$user_id,$partner_id,'$title','$description','med','pend')";
    $db->exec($sql);
    
    list($email_address,$requestor)= $db->read_one("SELECT email_address,concat(first_name,' ' ,last_name) user from mukonin_audit.user  
                                   where id=$user_id");
    $rows = $db->read("select email_address from mukonin_audit.user u, mukonin_audit.partner p, mukonin_audit.user_role r
    where u.partner_id = p.id and r.user_id = u.id and r.role_code='admin' and short_name='$program_owner'");

   
    foreach($rows as $row)
    { 
        $username = $row['contact_person'];
        $email = $row['email_address'];

        $message = "Good day<br><br>
        I would like to have the following feature added to the system.<br><br>
        $description.<br><br>
        Regards,<br>
        $requestor";
        $subject = "Feature Request, ".$title;
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $headers .= "from:  $email_address";
        $mail_sent = mail($email, $subject, $message, $headers);
        log::debug("Sending email $email_address to $username<$email>: $mail_sent");    
     }
      
  }
 
  static function update_priority($request)
  {
    global $db, $session;
    $user_id = $session->user->id;
    $id = $request['id'];
    $priority = $request['priority'];
    $status = $request['status'];
    //user::audit('update_role', $id, $priority);
    
    global $db;
   
    $sql = "update mukonin_audit.feature_request set priority='$priority',status_code='$status' where id = $id";
    $db->exec($sql);
       
   }
   
  
  static function manage_features($request)
  {
    //user::verify('manage_features');
   $program_id = config::$program_id;
    $titles = array('#id','Time','~Company','~Requestor','~Request','~Reason','~Priority|name=priority|edit=list:?feature/priorities','~Status|name=status|edit=list:?feature/status','~Reject Reason','Action');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage Feature Request");
    $table->set_options($request);$table->set_key('id');
    $table->set_expandable('/?a=request_comment');
    $table->set_row_actions('edit,Decline,Comment');
    $table->set_saver('/?a=feature/update_priority');
    $table->set_options($request);
    $sql = "select * from (select fr.id,fr.create_time,full_name,concat(first_name,' ',last_name) requestor,fr.title,fr.description,pr.name priority,s.description status, r.description reason
            from mukonin_audit.feature_request fr
            join mukonin_audit.user u on u.id = fr.user_id
            join mukonin_audit.partner p on p.id = fr.partner_id
            join mukonin_audit.status s on s.code = fr.status_code
            left join mukonin_audit.priority pr on pr.code = fr.priority
            left join mukonin_audit.reason r on r.code = fr.status_reason_code 
            where fr.program_id=$program_id) tmp where 1=1";
    $table->show($sql);
  }
  static function detail($request)
  {      
    $id = $request['id'];
    //user::verify('process_licence', $id);  
    $sql = "select  title,description reason,count(comment) comments
            from mukonin_audit.feature_request fr
            left join mukonin_audit.feature_comment fc on fr.id=fc.feature_id
            where fr.id = $id ";
     
    global $db;
    $result = $db->read_one($sql, MYSQLI_ASSOC);
    
    echo json_encode($result);
  }
  
    static function answers($request)
  {      
    $id = $request['id']; 
    $program_id = config::$program_id;
    
    $sql = "SELECT distinct fc.id,fc.create_time date,comment,concat(first_name,' ',last_name)
            from mukonin_audit.feature_comment fc 
            join mukonin_audit.user u on u.id = fc.user_id
            WHERE feature_id= $id and fc.program_id=$program_id";
     
    $headings = array('#id|name=id','Date', 'Comments','User');
    $table = new table($headings,table::TITLES | table::ALTROWS);
    unset($request['id']);
    $table->set_options($request);
    $table->show($sql);
  }
  static function view_feature($request)
  {
    //user::verify('manage_feedbacks');
    $program_id = config::$program_id;
    $titles = array('#id','Time','~Requestor','~Request ','~Status','~Action');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("View Feature Requests");
    $table->set_options($request);$table->set_key('id');
    $table->set_row_actions('Comment');
    $table->set_expandable('/?a=request_comment');
    $table->set_options($request);
    $sql = "select * from (select fr.id,fr.create_time,concat(first_name,' ',last_name) requestor,fr.title,s.description,
            case s.description
             when 'pending' then 'Comment'
              when 'planned' then 'Comment'
              when 'declined' then ''
              when 'under review' then 'Comment'
              else '' 
            end as actions
            from mukonin_audit.feature_request fr
            join mukonin_audit.user u on u.id = fr.user_id
            join mukonin_audit.status s on s.code = fr.status_code
            join mukonin_audit.partner p on p.id = fr.partner_id
           where fr.program_id=$program_id) tmp where 1=1";
    $table->show($sql);
  }
  
  
  static function add_comment($request)
  {
    global $session,$db;  
    
    $user_id = $session->user->id;
    $program_id = config::$program_id;
    $id= $request[id];
    $comment= $request[comments];
    
    $sql = "INSERT into mukonin_audit.feature_comment(program_id,feature_id,user_id,comment)
           VALUES($program_id,$id,$user_id,'$comment')";
    $db->exec($sql);
    
    $rows = $db->read("select email_address,fr.title from mukonin_audit.user u, mukonin_audit.feature_request fr
            where u.id=fr.user_id and u.program_id=fr.program_id  and fr.id = $id");
    foreach($rows as $row)
    { 
      $title = $row['title'];
      $email = $row['email_address'];
      $message = "Good day<br><br>
				  Hello World.<br>
				  $comment.<br><br>
				  Regards<br>
				  Customer Operations";
      $subject = "";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  donotreply@fpb.org.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username<$email>");    
    }
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

  static function reject($request)
  { 
    
    global $db;
   
    $request = db::quote($request);
    $id = $request['id'];
    $status_code = substr(strtolower($request['code']),0,4);
    $reason_code = $request['reason'];
    $title = $db->read_one_value("select title from mukonin_audit.feature_request where id = $id");
    $status = $db->read_one_value("select description from mukonin_audit.status where code = '$status_code'"); 
    $reason = $db->read_one_value("select description from mukonin_fpb.reason where code = '$reason_code'"); 
    user::audit("reje_feature_request",$id,"Title: $title, Status:$status,Reason: $reason");
    
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

