<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  // require_once('../common/ifind.php');

  global $db;
  list($qmin,$qmax) = $db->read_one("select min(number), max(number) from question");
?>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<div id="selection" <?=$display[existing]?> > 
 <fieldset class = "details" style="width:350px; height: 150px;">
    <div class="centerbox">
      <h2><strong>Rate Game</strong></h2>
    </div >
    <div class=container>
      <div class="labels" style="border: 1px solid red;">
        <h4>Track my :</h4> 
      </div>
      <div class="controls">
        <div class="line">
          <select id = "game_id" name=game_id class="input" >
             <option id="classification">Classification</option>
             <option id="registration">Registration</option>
             <option id="appeal">Appeal</option>
          </select>
        </div>           
        <div class=line>
          <input type="button" class = "button-medium" id='next1'  value="Next" onclick="ajax_inner('question_table', 'do.php/rating/show_questions?game_id');swapShowById('selection','questions');" >&nbsp				
          <input type="button" class = "button-medium" value="New" onclick="swapShowById('selection','new_game');" >				
        </div>
      </div>
    </div>
  </fieldset>
</div>