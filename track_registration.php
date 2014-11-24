<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  
  global $db;
  global $session;
  $vendor_id = $session->user->partner_id;
?>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<div id="selection"></br> </br></br></br>
  <fieldset class = "details" style="width:350px; height: 260px;">
    <div class="centerbox">
      <h2><strong>Track Registration</strong></h2>
    </div >
    <div class=container>
      <div class="labels" style="top:20px; left: 25px;">
        <h4>Status </h4>
      </div>
      <div style="position: absolute;top: 40px; left: 50px;">
        <div class="line">
          <fieldset style="border-radius: 10px;width: 120px;height: 120px;">
            <?= $db->read_one_value("select concat_ws(' - ', s.description, r.description) 
                                  from vendor v left join status s on (v.status_code = s.code) 
                                       left join reason r on (r.code = v.status_reason_code)
                                  where v.id = '$vendor_id'");
            ?>
          </fieldset>
        </div>    
        <a href="/?c=complaints" style="position: relative; top: 90px; left: 170px;">Submit Complaint</a>				       
      </div>
    </div>
  </fieldset>
</div>