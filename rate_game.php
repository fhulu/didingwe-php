<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('../common/table.php');

global $db;
list($qmin,$qmax) = $db->read_one("select min(number), max(number) from question");
?>

<script type="text/javascript" src='../common/wizard.js'></script> 
<script type="text/javascript" src='../common/table.js'></script> 
<script>
function save_game()
{ 
  if(jq_confirm('do.php/game/game_verify,check_name','title,country_of_origin,genre_code,year_of_production, platform_code,version,release_date'))
  {

    new_game_id = jq_submit('do.php/game/add','title,sysnopsis,code,country_of_origin,year_of_production,format,publisher,genre_code,version,release_date,platform_code,board,reason,rating', 'get');
        
    $.get( 'do.php/rating/show_questions', { game_id: new_game_id }, function(data) {
      $('#question_table').html(data);
    });
  }
  
}

function save_questions(qmin,qmax)
{
  
  var range = qmin+':'+qmax;
  if(jq_confirm('do.php/rating/verify','q:'+range))
  {
    var game_id_param = 'game_id';
    if (new_game_id != undefined) {
      game_id_param += '='+new_game_id;
      new_game_id = undefined;
    }
    jq_submit('do.php/rating/save', game_id_param+',q:'+range);          
    $('#rating_table').html(jq_submit('do.php/rating/show', game_id_param));
  }
}

function start_wizard()
{
  var wizard = $('#rate_game').wizard().data('wizard');
  wizard.start();
  $('#questions').table({url: '/?a=rating/show_questions'});
  
  $("#release_date").datepicker({
    dateFormat: 'yy-mm-dd',
    onSelect: function(dateText, inst) {
      var today = new Date();
      today = Date.parse(today.getMonth()+1+'/'+today.getDate()+'/'+today.getFullYear());
      var selDate = Date.parse(dateText);
      if(selDate <= today) {
        //If the selected date was before today, continue to show the datepicker
        $('#release_date').val('');
        $(inst).datepicker('show');
      }
    }        
 });  

}
function rate_a_game(id) 
{
  if (id !== undefined) {
    $('#rate_game').load('/?a=game/load', {id: id}, function() {
      start_wizard();
    });
  }
  else start_wizard();
}  

</script>

<?php
if ($_GET['c'] == 'rate_game') {
  require_once('home.php');
  echo "<script>\n$(function() { rate_a_game();});\n</script>\n";
}
?>
<style>
.input
{
  width: 320px;
}

#questions th,
#questions td
{
  vertical-align: top;
  border: 1px solid grey;
}
</style>
<div id="rate_game" style="display:none">
  <div title="Fill out the form" style="width:520px;">
    <p><i>Please complete the form and enter the details of the game you want to rate</i></p>  
    <div class="container">    
      <div class="labels" style="width: 140px;">
        <div class="line">Title</div>
        <div class="line" style=" height:50px;">Sysnopsis</div>
        <div class="line">Country Of Origin</div>
        <div class="line">Year Of Production</div>
        <div class="line">Format</div>
        <div class="line">Platform</div>
        <div class="line">Publisher</div>
        <div class="line">Genre</div>
        <div class="line">Version</div>
        <div class="line">Release Date</div>
      </div>
      <div class="controls"  >
        <div class="line"><input class="input" type="text" name="title" ></div>
        <div class="line" style=" height:50px;"><textarea rows="5" cols="30" name='sysnopsis'  class="input" style=" height: 45px;" ></textarea></div>
        <div class="line">
          <select  name="country_of_origin" id="country_of_origin" style="width:320px;" >
            <?= select::add_db('select code, name from mukonin_contact.country order by name','za') ?>
          </select>
        </div>
        <div class="line"><input class="input" type="text" name="year_of_production" ></div>
         <div class="line">
          <select  name="country_of_origin" class="input" >
            <?= select::add_items(",--Select a Format--|PC|Console|Arcade|Other",''); ?>
          </select>
        </div>
        <div class="line">
          <select id = "platform_code" name = "platform_code" class="input" >
             <?= select::add_db('select code, description from platform', '', '','--Select platform--');?>
          </select>
        </div>
        <div class="line"><input class="input" type="text" name="publisher" ></div>
        <div class="line">
          <select id = "genre_code" name = "genre_code" class="input" >
             <?= select::add_db('select code, description from genre','','','--Select a genre--');?>
          </select>
        </div>        
        <div class="line"><input class="input" type="text" name="version" ></div>
        <div class="line" ><input id="release_date" title="formart:yyyy-mm-dd" class="input" type="text" name="release_date" ></div>   
      </div>
    </div>
    <div class="line"> 
      <input type="button" class = "button-medium" value="Next"id="game_next" wizard=next />
    </div>
  </div>
  <div title="Previous Rating" style="width:440px;">
    <p>Has this game been previously rated by another board besides FPB, e.g. USK, ESRB, etc?</p>
    <div style="margin-left: auto; margin-right: auto; width: 180px">
      <input type="button" class = "button-medium" value="Yes"  wizard=next>
      <input type="button" class = "button-medium" value="No" id="prev_no" wizard=next steps=2 />
    </div>
    <br><br><br>
    <input type="button" class = "button-medium" value="Back"  wizard=back>
  </div>
  <div title="Rating from other boards" style="width:450px;">
    <div class="labels" style="width: 80px;">
      <div class="line">Board</div>
      <div class="line">Rating</div>
      <div class="line" >Reason</div>      
    </div>
    <div class="controls">
      <div class="line">
        <select name = "board" class="input" >
          <?= select::add_db('select id, name from board','','','--Select board--');?>
        </select>
      </div> 
      <div class="line">
        <input type='text' name='rating' class="input" />
      </div> 
      <div class="line">
        <input type='text' name='reason'  class="input" />
      </div> 
    </div>
    <div class="line"> 
      <input type="button" class = "button-medium" value="Back"  wizard=back>
      <input type="button" class = "button-medium" value="Next" id="board_next" wizard=next />
    </div>
  </div>
  <div title="Questionnaire" class=container style="width:600px;">
    <table id=questions></table>
    <div class="line" style="margin-top:20px"> 
      <input type="button" class = "button-medium" value="Back" wizard=back>
      <input type="button" class = "button-medium" value="Next" id="question_next" wizard=next />
    </div>
  </div>
  <div title="Classification" class=container style="width:400px;">
    <div id=rating_table></div>
    <br>
    <input type=button class = "button-medium" value=Done wizard=close>
  </div>
</div>
