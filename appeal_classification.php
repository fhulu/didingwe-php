<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
?>
<style>
</style>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<div id="selection"></br> </br></br></br>
 <fieldset class = "datails" style="width:350px; height: 360px;">
    <div class="centerbox">
      <h2><strong>Appeal Classification</strong></h2>
    </div >
    <div class=container>
      <div class="labels" style="top:10px;">
        <h4>Select Game </h4>
      </div>
      <div class="controls">
        <div class="line">
          <select id = "game_id" name=game_id class="input" >
             <?= select::add_db('select id, title from game',0,0,'--Select a game--');?>
          </select>
        </div>    
      </div>
      <textarea style="width: 400px; height: 200px; border-radius: 10px; resize: none;"></textarea></br></br>
      <input type="submit" style="float: right;" value="Submit Appeal"></input>     
    </div>
  </fieldset>
</div>