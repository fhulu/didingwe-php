<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  
  global $session;
  $user_id = $session->user->id;
  $topic = $_POST['topic']; 
  $detail = $_POST['detail'];
  $message = $_POST['message'];

  global $db;
        
  $id = $db->insert("insert into complaint(case_no, user_id, message, type, type_code)
                        select concat(date_format(now(), '%Y/%m/'), lpad(ifnull(substr(max(case_no),9),0)+1,5,0)),
                          '$user_id', '$message', 'c', $type_code
                        from complaint c left join complain_type ct on (c.type_code = ct.type_code)
                        where create_time >= date_format(now(), '%Y-%m-01')
                        and '$topic' = $type_code"); 
          
  //Redirect After Writing to Database
  session::redirect("/?c=thankyou");
?>