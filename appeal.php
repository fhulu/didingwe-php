<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  
  global $session;
  $type = $_GET['type'];
  $id = $_GET['id'];
  $user_id = $session->user->id;
  $vendor_id = $session->user->partner_id;
  
  if (isset($_POST['message']))
  {
    $message = $_POST['message'];  
    
    global $db;
        
    if ($type == 'reg') {
      $id = $db->insert("insert into appeal(case_no, user_id, message, type)
                 select concat(date_format(now(), '%Y/%m/'), lpad(ifnull(substr(max(case_no),9),0)+1,5,0)),
                  '$user_id', '$message', 'r'
                 from appeal
                 where create_time >= date_format(now(), '%Y-%m-01')"); 
   
    }
    else {
      $game_id = $_POST['game_id'];
      $type = $_POST['type'];
      $rating = $_POST['rating'];
      $id = $db->insert("insert into appeal(case_no, user_id, game_id, message, type, desired_rating)
                 select concat(date_format(now(), '%Y/%m/'), lpad(ifnull(substr(max(case_no),9),0)+1,5,0)),
                  '$user_id', '$game_id', '$message', 'c', '$rating'
                 from appeal
                 where create_time >= date_format(now(), '%Y-%m-01')"); 
   
    }
       
    session::redirect("/?c=appeal&type=$type&id=$id");
    
    return;
  }
?>

<link type="text/css" rel="stylesheet" href="default.style.css"></link>
<style>
body *
{
  border-radius: 5px;

}
select,input, textarea
{
  width: 200px;
  height: 24px;
}
div.fieldset
{
  position: relative;
  top: 50px;
}

</style>
<script type="text/javascript">
function disableall()
{
  $("fieldset *").attr('disabled', 'disabled');
}

$(function() {
  setDefaultMsg('textarea', 'Type your appeal message here');
  setDefaultMsg("input[name='rating']", 'Enter your desired rating here');
  disableOnEmpty("textarea,input[type='text'],select","input[type='submit']");
  <?php if ($id != '') {
    global $db;
    $case_no = $db->read_one_value("select case_no from appeal where id = $id");
  ?>
    $("input[type='text'],select").attr('disabled', 'disabled');
    $('textarea').val('Thank you for lodging an appeal. Please take note of your case number: <?=$case_no ?>');
  <?php } ?>
  
  disableEvery(200, "textarea,input[type='text'],select","input[type='submit']"); 
});
</script>
<div class="fieldset">
  <fieldset class="details" style="width:30px;">
    <h3><strong>Appeal <?= $type=='reg'?'Registration':'Classification' ?> </strong></h3>
    <form method="POST">
      <?php if ($type=='class') { ?>
      <select name="game_id">
        <?php select::add_db("select game_id, title from game g, vendor_game vg where vg.game_id = g.id and vendor_id = vendor_id", '','','--Select game--'); ?>
      </select><br><br>
      <input type="text" name="rating" /><br/><br/>
      <?php } ?>
      <textarea name="message" style="height: 200px;"></textarea><br/><br/>
      <?php if ($id == '') { ?>
      <input type="submit" value="Submit Appeal"></input>
      <?php } else { ?> 
        <a href="/?c=home">Return to home</a>
      <?php } ?>
    </form>
  </fieldset>
</div>
