<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  // require_once('../common/ifind.php');

  global $db;
  list($qmin,$qmax) = $db->read_one("select min(number), max(number) from question");
?>

    <link type="text/css" rel="stylesheet" href="gct.css"></link>
    <script type='text/javascript' src='../common/dom.js'></script>
    <script type="text/javascript" src="../common/ajax.js"></script> 
    <script type="text/javascript" >

      setInterval("disable_unset('next','game_id')",100);

      setInterval("disable_unset('next','title,platform_code,genre_code,version,release_date')",100);

      function save_game()
      {
        if(ajax_mconfirm('do.php/game/verify,check_name,isValidDateTime','title,platform_code,genre_code,version,release_date'))
        {
          ajax_inner('progress', 'do.php/game/add?title,platform_code,genre_code,version,release_date', '<i>Saving game details...</i>');
          ajax_inner('progress', 'do.php/game/add_vendor_game','<i>Storing Vendor and Game details...</i>');
          ajax_inner('question_table', 'do.php/rating/show_questions','Loading questions...');   
          swapShowById('new_game', 'questions');
        }
        
      }

      function save_questions(qmin,qmax)
      {
        var range = qmin+':'+qmax;
        if(ajax_confirm('do.php/rating/verify?q:'+range))
        {
          ajax_inner('progress', 
            'do.php/rating/save?game_id,q:'+range+',qmin='+qmin+',qmax='+qmax,
            'Rating in progress...');
          ajax_inner('rating_table', 'do.php/rating/show');
          swapShowById('questions','rating');
        }
      }
      
    </script>
    
<script type="text/javascript">
  window.onload = function() {
    document.getElementById("release_date").title="New tooltip";
  };
</script>


<?php
$display = array(
  'new'       =>  'style="display: none;"',
  'existing' =>  'style="display: none;"'
);
  
$display[$_GET[type] ] = '';  
?> 
<h2><strong>Rate Game</strong></h2>
<div id="selection" <?=$display[existing]?> >
  <div class=container>
    <div class="labels">
      <div class="line">Select Game </div>
    </div>
    <div class="controls">
      <div class="select-game">
        <select id = "game_id" name = game_id class="input" >
          <?php 
            //ifind::add('name','version', 'game', 
            //"ajax_inner('questions','do.php/rating/show_questions?name')");
              select::add_db('select id, title from game',0,0,'--Select a game--');
          ?>
        </select>
      </div>
        <br> 
      <div class=line>
        <input type="button" class = "button-medium" id='next'  value="Next" onclick="ajax_inner('question_table', 'do.php/rating/show_questions?game_id');swapShowById('selection','questions');" >&nbsp				
        <input type="button" class = "button-medium" value="New" onclick="swapShowById('selection','new_game');" >				
      </div>
    </div>
  </div>
</div> 
<div id="new_game" <?=$display['new']?> >
  <div class=container>
    <h3>Fill out the form and add a game</h3>
    <p>Please complete the form and enter the details of the game you want to rate</p>
  </div>
  <div class=container>
    <div class="labels">
      <div class="line">Game Title</div>
      <div class="line">Platform</div>
      <div class="line">Genre</div>
      <div class="line">Version</div>
      <div class="line">Release Date</div>
    </div>
    <div class="controls">
      <div class="line"><input class="input" type="text" name="title" ></div>
      <div class="line">
        <select  name ="platform_code" class="input" >
          <?php 
          
            select::add_db('select code, description from platform','','','--Select a platform--');
          ?>
        </select>
      </div>
      <div class="line">
        <select id = "genre_code" name = "genre_code" class="input" >
          <?php 
          
            select::add_db('select code, description from genre','','','--Select a genre--');
          ?>
        </select>
      </div>
      <div class="line"><input class="input" type="text" name="version" ></div>
        


      <div class="line" ><input id="release_date" title="formart:dd-mm-yyyy" class="input" type="text" name="release_date" ></div>
      <div class="line-1"></div>
      <div class="line"> 
        <input type="button" class = "button-medium" id='next' value="Next" onclick="save_game();" />&nbsp;&nbsp;&nbsp;
        <input type="button" class = "button-medium" value="Cancel" onclick="swapShowById('new_game','selection');" >
      </div>
    </div>
  </div>
</div>
<div id=progress></div>
<div id="questions" style="display: none;">
  <div id="question_table"></div>
  <input type=button class = "button-medium" value=Back  onclick="swapShowById('questions', 'selection');" />
  <input type=button class = "button-medium" value=Next onclick="save_questions(<?="$qmin,$qmax"?>)">
  
</div>
<div id=rating style="display: none;">
  <div id=rating_table></div>
  <br>
    <input type=button class = "button-medium" value=Done onclick="swapShowById('rating', 'selection'), history.go(0); " >
  
</div>

