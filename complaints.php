<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
?>
<style>
  form * 
  {
     border-radius: 5px;  
     position: relative;
  }
  .controls>*
  {
    position: relative;
    width: 320px;
  }
</style>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<div id="selection"></br> </br></br></br>
 <fieldset class = "details" style="width:560px; height: 400px;">
  <form action="do.php/submit_complaint" method="post" >
    <div class="centerbox">
      <h2><strong>Submit a Complaint</strong></h2>
    </div >
    <div class=container>
      <div class="labels" style="width: 200px;text-align: right;left: 0px;">
        <p>I would like to complain about </p>
        <p>Regarding </p>
        <p>Compaint </p>
      </div>
      <div class="controls"  style="padding-right: 10px;">
        <select name="topic" value="--Select Complaint--">
          <?php select::add_db("select * from complain_type", '','','--Choose complaint--'); ?>
        </select></br></br>
        <input type="text" name="detail"></input></br></br>
        <textarea name="message" style="height: 200px; resize: none;"></textarea>      
      </div></br></br>
    </div>
    <input type="submit" style="top: 160px;float: right;" value="Submit Complaint"></input>  
  </form>
  </fieldset>
</div>