<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/table.php'); 
  
  class load
  {
    static function fpb_user
    {
    global $db,$session;
    
    $start = $_REQUEST['start'];
    $size = $_REQUEST['size'];
    
    $partner_id = $session->user->partner_id;  
    $sql = "select r.code, r.description from reason r, status_reason sr 
            where sr.reason_code = r.code and sr.status_code ='reje'";
   
    
    $reason_options = select::read_db($sql,'','','--Select Reason--');
    $reason_dropdown = "<select>$reason_options, </select>";
    
    $sql = "select v.id,co_name,co_reg_no,tax_ref_no, '' document, Concat( first_name, ' ', last_name ) AS contact_person,applicant_id, '' AS reject_reason, '' edit  
            from mukonin_audit.user u, mukonin_fpb.vendor v
            where u.partner_id= v.id and u.id=v.user_id and is_active = 0 and status_code = '' and status_reason_code = ''
            order by v.create_time desc 
            limit $start , $size"; 
          
    $headings = array('#id','Company Name','Company Reg No','Tax Ref Number', 'Document', 'Contact Person','Identity Number','Reject Reason','');
    table::display($sql,$headings,table::TITLES | table::ALTROWS,"game",0,
     
     function(&$user_data, &$row_data, $row_num, &$attr) use ($reason_dropdown)
      {
        $id = $row_data['id'];
        $attr .= " id=$id";
        global $db;
        $db2 = $db->dup();
        $rows = $db2->read("select d.id, description from document_type dt, document d, vendor v
          where dt.code = d.type and d.vendor_id = v.id and v.id = $id");
        foreach($rows as $row) {
          $id = $row['id'];
          $desc = $row['description'];
          $documents .= "<a href='/do.php/vendor/view_doc?id=$id'>$desc</a><br>";
        }
        $row_data['document'] = $documents;
        $row_data['reject_reason'] = $reason_dropdown;
        $row_data['edit'] = "<input type='image' src='activate.jpg' onclick='approve(this);' /> ".	
                            "<input type='image' src='remove16.png' onclick='reject(this);'/>";

        return true;
      }); 
    }
  }  

