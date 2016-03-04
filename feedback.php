<?php
require_once('session.php');
require_once('config.php'); 
require_once('../common/select.php');
require_once('../common/validator.php');
require_once('../common/curl.php');
require_once('../common/errors.php');


class feedbacks extends Exception {};
class feedback
{
   
  static function check($request)
  {
    
    $v = new validator($request); 
    $v->check('title')->is(2);
    $v->check('type')->is('in(feedback_type.code)','optional'); 
    $v->check('message')->is(30);
    return $v->valid();
  }

  static function priorities()
  {
    return select::add_db('select code, name from priority order by no','','','--Select Priority--');
  }
 
  static function save($request)
  {    
    global $db,$session;
    
    $user_id= $session->user->id;
    $partner_id= $session->user->partner_id;
    $title = $request[title];
    $type = $request[type];
    $reason = $request[message];  
    $program_id = config::$program_id;
  
    //TO DO create a function in DB
     //user::audit('update_role', $id, $role);
    
    $sql = "INSERT into feedback(program_id,user_id,partner_id,title,description,type,status_code)
            VALUES($program_id,$user_id,$partner_id,'$title','$reason','$type','recei')";
    $db->exec($sql);
      
  }
 
  static function update_priority($request)
  {
    global $db, $session;
    $user_id = $session->user->id;
    $id = $request['id'];
    $priority = $request['tmp~priority'];
    //user::audit('update_role', $id, $priority);
    
    global $db;
   
    $sql = "update feature_request set priority='$priority' where id = $id";
    $db->exec($sql);
       
   }
   
  
  static function manage_feedback($request)
  {
    //user::verify('manage_feedbacks');
    $titles = array('#id','Time','~Requestor','~Request ','~Reason','~Type');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage Feedbacks");
    $table->set_options($request);$table->set_key('id');
//    $table->set_row_actions('edit,Approve,Reject');
//    $table->set_saver('/?a=feature/update_priority');
    $table->set_options($request);
    $sql = "select * from (select fr.id,fr.create_time,concat(first_name,' ',last_name) requestor,fr.title,fr.description,pr.description type
            from feedback fr
            join user u on u.id = fr.user_id
            join feedback_type pr on pr.code = fr.type
      
            join partner p on p.id = fr.partner_id) tmp where 1=1";
    $table->show($sql);
  }
  static function view_feedback($request)
  {
    //user::verify('manage_feedbacks');
    $titles = array('#id','Time','~Requestor','~Request ','~Reason','~Type');
    $table = new table($titles, table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPORTABLE);
    $table->set_heading("Manage Feedbacks");
    $table->set_options($request);$table->set_key('id');
//    $table->set_row_actions('edit,Approve,Reject');
    $table->set_expandable('/?a=fpb_dist_detail');
    $table->set_options($request);
    $sql = "select * from (select fr.id,fr.create_time,concat(first_name,' ',last_name) requestor,fr.title,fr.description,pr.description type
            from feedback fr
            join user u on u.id = fr.user_id
            join feedback_type pr on pr.code = fr.type
      
            join partner p on p.id = fr.partner_id) tmp where 1=1";
    $table->show($sql);
  }
  
  static function approve($request)
  {
    global $db;   
    $id = $request[id];
    
    list($user_id,$partner_id,$title)= $db->read_one("select user_id, partner_id,title from feature_request where id = $id");
    user::audit("appr_feature",$partner_id, $title,'partner');
 
    $sql = "update feature_request
            set status_code='appr'
            where id = $id";
    $db->exec($sql);
    
    $rows = $db->read("select email_address,Concat( first_name, ' ', last_name ) AS contact_person
                       from user u
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
    user::verify('rate_game');
    $titles = array('#No.','Please tick the appropriate feedback type','');
    $table = new table($titles, table::TITLES );
    $table->set_key('number');
    $table->set_options($request);
    $table->set_callback(function(&$row, $index, &$attr) {  
      $code = $row['code'];    
      $input = "input name='type' type=radio value=";
      $row['yes'] = "<$input$code>";
   
    });
    $table->show("select code,description, '' yes
      from feedback_type");
  }
  static function reject($request)
  { 
    
    global $db;
   
    $request = db::quote($request);
    $id = $request['id'];
    $status_code = substr(strtolower($request['code']),0,4);
    $reason_code = $request['reason'];
    $title = $db->read_one_value("select title from feature_request where id = $id");
    $status = $db->read_one_value("select description from status where code = '$status_code'"); 
    $reason = $db->read_one_value("select description from reason where code = '$reason_code'"); 
    user::audit($status_code."_feature_request",$id,"Title: $title, Reason: $reason");
    
    $sql = "update feature_request
              set status_code='$status_code',
             status_reason_code='$reason_code'
            where id = $id";
    
    $db->exec($sql);  
        
  
    
    $rows = $db->read("select email_address,Concat( first_name, ' ', last_name ) AS contact_person
        from user u,feature_request fr
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