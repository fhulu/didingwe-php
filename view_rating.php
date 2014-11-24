<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('../common/table.php');
?>
<html>
	<head>
		<title>View Approved Game Rating </title>
		<link type="text/css" rel="stylesheet" href="game.css"></link>
    <script type="text/javascript" src="../common/prototype.js"></script>
    <script type="text/javascript" src="../common/validation.js"></script>
		<script type="text/javascript" src="../common/dom.js"></script>
		<script type="text/javascript" src="../common/ajax.js"></script>
   
  </head>
  <body>
    <h2><strong>View A Game's Approved Rating</strong></h2>
    <div class=line>
      <div class=select>
        <select name=game_id>
          <?php 
            select::add_db('select id, name from game',0,0,'--Select a game--');
          ?>
        </select>
      </div>
      
      <input type="button" value="Next" onclick="ajax_inner('rating', 'do.php/rating/show_approved?game_id');" >			
      <input type="button" value="Cancel" onclick="" >				
    </div>
    <div id=rating></div>
  </body>
</html>    