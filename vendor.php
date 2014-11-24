<?php
require_once('../common/db.php');
require_once('../common/table.php');
require_once('../common/select.php');
require_once('../common/session.php');
require_once('certificate.php');

class vendor_exception extends Exception {};
class vendor
{
  static function check_applicant_id()
  {  
    $applicant_id =  $_REQUEST[id_no];
    global $db;
    if ($db->exists("select id from vendor where applicant_id = '$applicant_id'" ))
      echo "!this applicant's id number already exist";
  }
 
   static function check_co_name()
  {
    $co_name =  $_REQUEST[co_name];
    global $db;
    if ($db->exists("select id from vendor where co_name ='$co_name'"))
      echo "!The Company Name already exist";  
  }
   static function check_trading_as()
  {
    $trading_as =  $_REQUEST[trading_as];
    global $db;
    if ($db->exists("select id from vendor where trading_as ='$trading_as'"))
      echo "!The Trading Name already exist";  
  }
  static function check_co_reg_no()
  {
    $co_reg_no =  $_REQUEST[co_reg_no];
    global $db;
    if ($db->exists("select id from vendor where co_reg_no = '$co_reg_no'"))
      echo "!The Company Registration Number already exist";
  }
  static function check_vat_no()
  {
    $vat_no =  $_REQUEST[vat_no];
    global $db;
    if ($db->exists("select id from vendor where vat_no = '$vat_no'"))
      echo "!The Vat Number already exist";
  }
  
  
  static function check_tax_ref_no()
  {
    $tax_ref_no =  $_REQUEST[tax_ref_no];
    global $db;
    if ($db->exists("select id from vendor where tax_ref_no = '$tax_ref_no'"))
      echo "!The Tax Ref Number already exist";
  }
  
  static function check_email()
  {
    $email =  $_REQUEST[email];
    global $db;
    if ($db->exists("select id from mukonin_audit.user where email_address = '$email'"))
      echo "!The Email Address already exist";
  }
  
  static function save()
  { 
    $type = $_REQUEST['type'];
    $id_no = $_REQUEST['id_no'];
    $co_name = $_REQUEST['co_name'];
    $trading_as = $_REQUEST['trading_as'];
    $co_reg_no = $_REQUEST['co_reg_no'];
    $vat_no = $_REQUEST['vat_no'];
    $tax_ref_no = $_REQUEST['tax_ref_no'];
    $fpb_registration_no = $_REQUEST['fpb_reg_no'];
    
    global $session;
    $user = &$session->user;
    $user_id = $user->id; //get user_id
    
    // if a partner id is selected, iow, using an existing company, then we must notify the company admin(s)
    $partner_id = $_REQUEST['id'];
       
    $postal_address = $_REQUEST['postal_address'];
    $postal_code = $_REQUEST['postal_code'];
    $tel_no = $_REQUEST['tel_no'];
    $fax_no = $_REQUEST['fax_no'];
    $physical_address = $_REQUEST['physical_address'];
    $physical_code = $_REQUEST['code'];
    $country = $_REQUEST['country'];
    $province = $_REQUEST['province'];
    
    $program_id = config::$program_id;
    
    global $db;
    
    if($trading_as==''){
   
      $trading_as= $co_name;
    }
    
    $sql = "INSERT INTO mukonin_audit.partner(short_name, full_name) values ('FPB Client','$co_name')";
    $partner_id = $db->insert($sql);
    $user->partner_id = $partner_id;    

    $fpb_reg_no = sprintf("FPB8/%04d/%04d", date('Y'),$partner_id);
    $sql ="INSERT INTO vendor(id,type,trading_as,user_id,applicant_id,co_name,co_reg_no,tax_ref_no,vat_no,fpb_reg_no,
          postal_address,postal_code,telephone,fax_no,physical_address,physical_code,country,province,status_code, status_reason_code)
          VALUES($partner_id,'$type','$trading_as',$user_id,'$id_no','$co_name','$co_reg_no','$tax_ref_no','$vat_no', '$fpb_reg_no',
          '$postal_address','$postal_code','$tel_no','$fax_no','$physical_address','$physical_code','$country','$province','pend','aapp')";      
    $db->exec($sql); 
    
    $sql = "INSERT INTO mukonin_audit.group_partner(partner_id,program_id,group_code ) values ($partner_id,$program_id,'$type')";
    $db->insert($sql);

    $sql = "update mukonin_audit.user_role set role_code='admin' where user_id = $user_id and role_code='unreg'";
    $db->exec($sql);
    
    $sql = "update mukonin_audit.user set partner_id = $partner_id where id = $user_id";
    $db->exec($sql);
    $user->assign_role('admin');
    
    vendor::start_approval($user);
  }

  static function upload_file($control, $type)
  {
    $file_name = $_FILES[$control]["name"];
    if ($file_name == '') return;
    global $db,$session;
    $user_id = $session->user->id;
    
    $sql = "INSERT INTO document(vendor_id,user_id,filename,type) 
            select partner_id,$user_id,'$file_name','$type' from mukonin_audit.user where id = $user_id";
    
    $id = $db->insert($sql);
    $path ="uploads/$id-".$_FILES[$control]["name"]; 
    log::debug("Uploading file $path");
    if (!is_uploaded_file($_FILES[$control]['tmp_name']))
      throw new vendor_exception("File $path not moved to destination folder. Check permissions");
      
    if (!move_uploaded_file($_FILES[$control]["tmp_name"], $path))
      throw new vendor_exception("File $path not moved to destination folder. Check permissions");
      
    log::debug("File uploaded $path");
  }
  
  static function upload()
  { 
    vendor::upload_file('id_copy','ID');
    vendor::upload_file('co_reg_copy','CR');
    vendor::upload_file('tax_clearance_copy','TC');
  }
   static function company_activate()
  {     
    global $session, $db;
    //$user_id = $session->user->id; //get user_id
    $session->user->partner_id = $partner_id;
      
    
    $sql = "update mukonin_fpb.vendor set active=1 where partner_id = $partner_id";
    $db->exec($sql);
  }
  
  static function view()
  { 
    global $db;
     $id =  $_REQUEST[game_id];
   
    $sql = "select p.first_name,concat(r.max_age,r.classification) as rating ,r.date_rated FROM game g,rating r ,publisher p
            where g.id = r.game_id and
                  r.publisher_id = p.id and
                  g.id = $id ";
         
	  $headings = array('Publisher Name','Rating','Date Rated');
			table::display($sql,$headings,table::TITLES | table::ALTROWS,"game",0);
      
  }
  
  static function approve($request)
  {
    global $db,$session;   
    $id = $_REQUEST['id'];
    $db->exec("update mukonin_fpb.vendor set is_active = 1,status_code='reg',status_reason_code=''  where id =$id ");
    
    $rows = $db->read("select email_address, Concat( first_name, ' ', last_name ) AS contact_person
        from mukonin_audit.user u, mukonin_fpb.vendor v, mukonin_audit.user_role r
        where u.partner_id = v.id and r.user_id = u.id and r.role_code = 'admin' and v.id = $id");

    foreach($rows as $row)
    { 
      $username = $row['contact_person'];
      $email = $row['email_address'];
      $message = "Dear $username, <br>FPB would like to inform you that your application has been approved.";
      $subject = "Approve Application";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  fpbadmin@mukoni.co.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username");    
    }
  } 
  
  static function notify($request)
  {   
    global $db;
   
    $id = $_REQUEST['id'];    
    $rows = $db->read("select email_address, Concat( first_name, ' ', last_name ) AS contact_person
        from mukonin_audit.user u, mukonin_fpb.vendor v, mukonin_audit.user_role r
        where u.partner_id = v.id and r.user_id = u.id and r.role_code = 'admin' and v.id = $id");

    foreach($rows as $row)
    { 
      $username = $row['contact_person'];
      $email = $row['email_address'];
      $message = "Dear $username, <br>FPB would like to inform you that your registration has expired.";
      $subject = "Rejected Application";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  fpbadmin@mukoni.co.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username<$email>");    
    }    
  } 

  static function update_status($request)
  {   
    global $db;
   
    $id = $_REQUEST['id'];
    $status_code = substr(strtolower($_REQUEST['code']),0,4);
    $reason_code = $_REQUEST['reason'];
    
    $sql = "update mukonin_fpb.vendor set status_code='$status_code', status_reason_code= '$reason_code' where id = $id";    
    $db->exec($sql);  
        
    $status = $db->read_one_value("select description from mukonin_fpb.status where code = '$status_code'"); 
    $reason = $db->read_one_value("select description from mukonin_fpb.reason where code = '$reason_code'"); 
    
    $rows = $db->read("select email_address, Concat( first_name, ' ', last_name ) AS contact_person
        from mukonin_audit.user u, mukonin_fpb.vendor v, mukonin_audit.user_role r
        where u.partner_id = v.id and r.user_id = u.id and r.role_code = 'admin' and v.id = $id");

    foreach($rows as $row)
    { 
      $username = $row['contact_person'];
      $email = $row['email_address'];
      $message = "Dear $username, <br><br>FPB would like to inform you that the status of your application is:<b>$status</b>.<br>Reason: <b>$reason</b>.";
      $subject = "FPB Application Status";
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from:  fpbadmin@mukoni.co.za";
      $mail_sent = mail($email, $subject, $message, $headers);
      log::debug("Sending email to $username<$email>");    
    }    
  } 

  
  static function start_approval($user)
  {     
    global $db;
   
    $program_id = config::$program_id;
    $partner_id = $user->partner_id;
    $requestor = "$user->first_name $user->last_name <$user->email>";
    
    $co_name = $db->read_one_value("select co_name from vendor where id = $partner_id ");
    
    $emails = $db->read_column("select email_address 
            from mukonin_audit.user u, mukonin_audit.partner p, mukonin_audit.user_role ur
            where u.id = ur.user_id and u.partner_id = p.id and p.short_name='FPB'and program_id=$program_id");
    
    foreach($emails as $email) {
      $link = "http://". $_SERVER['SERVER_NAME'] ."/?c=fpb_user_view"; 
      $message = "$requestor from $co_name would like to register as a user of the Online Submission Application. Please click <a href=\"$link\">here</a> to give access to user.";
      $subject = "Approve Registration";
      $headers  = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $headers .= "from: $requestor";
      log::debug("Sending email for $requestor to $email");
      $mail_sent = mail($email, $subject, $message, $headers);
      
    }
  }
  
  static function view_doc($request)
  {
    $id = $request['id'];
    if (!is_numeric($id)) {
      vendor::view_cert($request);
      return;
    }
    global $db;
    list($file_name, $desc) = $db->read_one("select filename, description from document d, document_type dt
      where d.type = dt.code and d.id = $id");
    $file_name = "uploads/$id-$file_name";
    if (!file_exists($file_name)) {
      echo "Document file not found. Please report to System Administrator";
      return;
    }
    $size = filesize($file_name);
    header("Content-Disposition: attachment; filename=\"$desc\"");
    header("Content-length: ". $size);
    // --- get mime type end --
    header("Content-type: ". get_mimetype($file_name));
    $file = fopen($file_name, 'rb');
    $data = fread($file, $size);
    fclose($file);
    echo $data;
  }

  static function view_cert($request)
  {
    $pdf = new CertificatePDF();
    $pdf->Body($request['id']);
    $pdf->Output();
  }

  static function manage($request)
  {              
    $status_actions = array(
      'pending' => array('Approve','Reject'),
      'renewal' => array('Approve','Reject','Suspend'),
      'registered' => array('Suspend','Withdraw'),
      'rejected' => array('Approve'),
      'suspended' => array('Approve','Withdraw'),
      'withdrawn' => array('Approve'),
      'expired' => array('Notify'),
      'cancelled' => array()
    );
    
    $action_pos = array( "Approve"=>0, "Reject"=>1, "Withdraw"=>1, "Suspend"=>2, "Notify"=>0 );
              
    $sql = "select v.id, substring(change_time,1,10) 'date', co_name, pg.name type, c.name country, p.name,
            (select distinct city from mukonin_contact.surburb where code = v.physical_code limit 1) city, 
            Concat( first_name, ' ', last_name ) AS contact_person, email_address email, fpb_reg_no,s.description status, r.description reason,'' action  
            from mukonin_fpb.vendor v 
              left join mukonin_audit.partner_group pg on pg.code = v.type 
              left join mukonin_audit.user u on u.id = v.user_id
              left join mukonin_fpb.status s on v.status_code=s.code
              left join mukonin_fpb.reason r on status_reason_code = r.code
              left join mukonin_contact.country c on v.country = c.code
              left join mukonin_contact.province p on v.province = p.code";
    
    $headings = array('#id','~Date', '~Company Name', '~Type','~Country', '~Province', '~City', '~Contact Person','#email', '~Licence','~Status', '~Reason','Actions');
    $table = new table($headings,table::TITLES | table::ALTROWS | table::FILTERABLE | table::EXPANDABLE);
    $table->set_heading("Manage Distributors");
    $table->set_options($request);
    $table->set_expandable('id');
    
    $table->set_callback( function(&$row_data, $row_num, &$attr) use ($status_actions, $action_pos)
    {
     // $attr .= " id=".$row_data['id'];;
      $row_data['document'] = $documents;
      $status = $row_data['status'];
      if ($status != '' && $status != 'pend' && $status != 'renew') {
        $attr = preg_replace("/class *= *'?[^\s]+'?/",'', $attr);
        $attr .= " class='$status'";
      }
      $actions = $status_actions[$status];
      if (is_null($actions)) return true;
      
      $options = array('disabled','disabled','disabled','disabled');
      foreach($action_pos as $action=>$pos) {
        if (in_array($action, $actions)) $options[$pos] = "title='$action'";
      }
      foreach($options as $option) { 
        $row_data['action'] .= "<div $option/>";
      }
      
      $email = $row_data['email'];
      $row_data['contact_person']  = "<a href='mailto:$email'>".$row_data['contact_person'].'</a>';
      
      $id = $row_data['id']; 
      $row_data['fpb_reg_no'] = "<a href='/?a=vendor/view_cert&id=$id'>".$row_data['fpb_reg_no'].'</a>';
      return true;
    }); 
    
    $table->show($sql);
  }

  static function detail($request)
  {      
    $id = $request['id'];
       
    $sql = "select cp.first_name, cp.last_name, concat(uu.first_name, ' ', uu.last_name) update_user, change_time, 
              co_name, trading_as, co_reg_no, postal_address, postal_code, physical_address, physical_code, 
              fax_no, telephone, province, applicant_id, vat_no, tax_ref_no, fpb_reg_no, pg.name type,
              c.name country, p.name province, cp.email_address email, s.description status, r.description reason,
              crd.id co_reg_doc, tcc.id tcc_doc, idd.id id_doc 
            from mukonin_fpb.vendor v 
              left join mukonin_audit.partner_group pg on pg.code = v.type 
              left join mukonin_audit.user uu on uu.id = v.user_id
              left join mukonin_audit.user cp on cp.partner_id = v.id
              left join mukonin_audit.user_role ur on ur.user_id = cp.id and ur.role_code = 'admin'
              left join mukonin_fpb.status s on v.status_code=s.code
              left join mukonin_fpb.reason r on status_reason_code = r.code
              left join mukonin_contact.country c on v.country = c.code
              left join mukonin_contact.province p on v.province = p.code
              left join mukonin_fpb.document crd on crd.vendor_id = v.id and crd.type = 'CR'
              left join mukonin_fpb.document tcc on tcc.vendor_id = v.id and tcc.type = 'TC'
              left join mukonin_fpb.document idd on idd.vendor_id = v.id and idd.type = 'ID'
            where v.id = $id";
    
    global $db;
    $result = $db->read_one($sql, MYSQL_ASSOC);
    
    echo json_encode($result);
  }
  
}  

    function get_file_extension($file) {
     
    return array_pop(explode('.',$file));
    }
     
    function get_mimetype($value='') {
     
    $ct['htm'] = 'text/html';
    $ct['html'] = 'text/html';
    $ct['txt'] = 'text/plain';
    $ct['asc'] = 'text/plain';
    $ct['bmp'] = 'image/bmp';
    $ct['gif'] = 'image/gif';
    $ct['jpeg'] = 'image/jpeg';
    $ct['jpg'] = 'image/jpeg';
    $ct['jpe'] = 'image/jpeg';
    $ct['png'] = 'image/png';
    $ct['ico'] = 'image/vnd.microsoft.icon';
    $ct['mpeg'] = 'video/mpeg';
    $ct['mpg'] = 'video/mpeg';
    $ct['mpe'] = 'video/mpeg';
    $ct['qt'] = 'video/quicktime';
    $ct['mov'] = 'video/quicktime';
    $ct['avi'] = 'video/x-msvideo';
    $ct['wmv'] = 'video/x-ms-wmv';
    $ct['mp2'] = 'audio/mpeg';
    $ct['mp3'] = 'audio/mpeg';
    $ct['rm'] = 'audio/x-pn-realaudio';
    $ct['ram'] = 'audio/x-pn-realaudio';
    $ct['rpm'] = 'audio/x-pn-realaudio-plugin';
    $ct['ra'] = 'audio/x-realaudio';
    $ct['wav'] = 'audio/x-wav';
    $ct['css'] = 'text/css';
    $ct['zip'] = 'application/zip';
    $ct['pdf'] = 'application/pdf';
    $ct['doc'] = 'application/msword';
    $ct['docx'] = 'application/msword';
    $ct['bin'] = 'application/octet-stream';
    $ct['exe'] = 'application/octet-stream';
    $ct['class']= 'application/octet-stream';
    $ct['dll'] = 'application/octet-stream';
    $ct['xls'] = 'application/vnd.ms-excel';
    $ct['xlsx'] = 'application/vnd.ms-excel';
    $ct['ppt'] = 'application/vnd.ms-powerpoint';
    $ct['wbxml']= 'application/vnd.wap.wbxml';
    $ct['wmlc'] = 'application/vnd.wap.wmlc';
    $ct['wmlsc']= 'application/vnd.wap.wmlscriptc';
    $ct['dvi'] = 'application/x-dvi';
    $ct['spl'] = 'application/x-futuresplash';
    $ct['gtar'] = 'application/x-gtar';
    $ct['gzip'] = 'application/x-gzip';
    $ct['js'] = 'application/x-javascript';
    $ct['swf'] = 'application/x-shockwave-flash';
    $ct['tar'] = 'application/x-tar';
    $ct['xhtml']= 'application/xhtml+xml';
    $ct['au'] = 'audio/basic';
    $ct['snd'] = 'audio/basic';
    $ct['midi'] = 'audio/midi';
    $ct['mid'] = 'audio/midi';
    $ct['m3u'] = 'audio/x-mpegurl';
    $ct['tiff'] = 'image/tiff';
    $ct['tif'] = 'image/tiff';
    $ct['rtf'] = 'text/rtf';
    $ct['wml'] = 'text/vnd.wap.wml';
    $ct['wmls'] = 'text/vnd.wap.wmlscript';
    $ct['xsl'] = 'text/xml';
    $ct['xml'] = 'text/xml';
     
    $extension = get_file_extension($value);
     
    if (!$type = $ct[strtolower($extension)]) {
     
    $type = 'text/html';
    }
     
    return $type;
    }
?>