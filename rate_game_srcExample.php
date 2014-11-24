<?php
  /*require_once("session.php");*/
  require_once("../common/select.php");
  /*require_once('../common/process.php');*/
  require_once('../common/db_file.php');

  if (sizeof($_POST) > 0) {
    log::init('upload', log::DEBUG);
    $file = new db_file();
    $file->upload('batch_file', false, 'batches');
    $dest_path = dirname($file->path).date("/Y-m-d-").basename($file->path); 
    rename($file->path, $dest_path); 
    process::q('ots_batch::load_extract', $dest_path, $_POST['format_id'], 
    $_POST['lacode'], $_POST[wash_type], $_POST['batch_date'], $_FILES['batch_file']['name'], $_POST['court_date'], $_POST[wash_period], $_POST[wash_period_type]);
  } 
?>
<html>
  <head>
    <title>Load Batch</title>
    <script type="text/javascript" src="select.js"></script>
    <script type="text/javascript" src="datepickercontrol.js"></script>
    <script type="text/javascript" src="dom.js"></script>
    <script type="text/javascript" src="ajax.js"></script>
    <link type="text/css" rel="stylesheet" href="ots.css">
    <link type="text/css" rel="stylesheet" href="batch.css">
    <link type="text/css" rel="stylesheet" href="datepickercontrol_darkred.css"></link>
    <script type="text/javascript">
      setInterval("disable_unset('submit','batch_date,batch_file,lacode,format_id,wash_type')",100);
    </script>
  </head>
  <body>
    <h2 ><strong>Upload New Batch</strong></h2>
    <form  action="batch_edit.php" method="POST" 
    enctype="multipart/form-data" onsubmit="return ajax_mconfirm('do.php/ots_batch/check_lacode,check_filename', 
      'batch_date,lacode,batch_file,format_id')" >
      <div class="labels">
        <div class="line">Batch Date</div>
        <div class="line">Municipality</div>
        <div class="line">Washing Type</div>
        <div class="line">File Source</div>
        <div class="line">File Type</div>
        <div class="line">Court Date</div>
        <div class="line">File Format</div>
      </div>
      <div class="controls">
        <div class="line">
          <input type="text" id="DPC_calendar1b_YYYY-MM-DD" name="batch_date" size=37 value="<?= date('Y-m-d'); ?>" />
          <input type="hidden" id="DPC_TODAY_TEXT" value="Month"/>
          <input type="hidden" id="DPC_BUTTON_TITLE" value="Choose date"/>
          <input type="hidden" id="DPC_MONTH_NAMES" value="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']"/>
          <input type="hidden" id="DPC_DAY_NAMES" value="['Sun', 'Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat']"/>
          <input type="hidden" id="DPC_FIRST_WEEK_DAY" value="0"/>
        </div>
        <div class="line">
          <select id=lacode name="lacode">
            <?php 
              select::add_db("select * from mukonin_tcs.dept_select_view where client_id=$client_id", 0, 0, '(Not Selected)');
            ?>
            <option value=-1>(Mixed)</option>
          </select>
        </div>
        <div class="line">
          <select name="wash_type">
            <?php select::add_items('n,Not Required|c,Cell Numbers|e,Email Addresses|a,All Contact Info','c'); ?>
          </select>
          Expired after<input type="text" name="wash_period" size=1 value="2"/>
          <select name="wash_period_type">
            <?php select::add_items('day,Days|week,Weeks|month,Months', 'week'); ?>
          </select>
         
        </div>
        <div class=line><input type="file" id='batch_file' name="batch_file" size="37" /></div>
        <div class="line">
          <select name="format_id" onclick="ajax_value('format','do.php/batch/get_format?format_id')">
            <?php select::add_db("select id, name from mukonin_tracing.batch_format WHERE type = 'rfc'", 0,0,'(Not Selected)'); ?>
          </select>
        </div>
        <div class=line>
          <input type="text" id="DPC_calendar2b_YYYY-MM-DD" name="court_date" size=37 />
        </div>
        <div class="line"><textarea name=format rows="3" cols="50" wrap="hard" disabled></textarea></div>
        <div class="line"></div>
        <div class="line"></div>
        <div id=progress class=progress></div>
        <div class="line">
          <input type="submit" id=submit value="Submit" >
          <input type="reset"  value="Reset" >
        </div>
      </div>
    </form>
  </body>
</html>
