<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('certificate.php');
  
  global $session;
  global $db;
  
  $vendor_id = $session->user->partner_id;
  
  $certificate_number = $db->read_one_value("SELECT id, fpb_reg_no
                                             from vendor 
                                             where id = '$vendor_id'");
  $applicant_name = $db->read_one_value("SELECT id, co_name
                                         from vendor 
                                         where id = '$vendor_id'");
  $trading_name = $db->read_one_value("SELECT id, trading_as 
                                       from vendor
                                       where id = '$vendor_id'"); 
  $address = $db->read_one_value("SELECT id, physical_address
                                  from vendor 
                                  where id = '$vendor_id'");
  $distributor_type = $db->read_one_value("SELECT id, description
                                           from vendor v left join vendor_type vd on (v.type = vd.code)
                                           where id = '$vendor_id'");
                                  
  //Dates to be generated from System
  //Temporarily set statically
  $from_date = '05/2012';
  $to_date = '05/2013';
     
  $pdf = new CertificatePDF();
  
  $pdf->Body($certificate_number, $applicant_name, $trading_name, $address, $distributor_type, $from_date, $to_date);
  $pdf->Output('regcertificate.pdf', 'D');
?>
