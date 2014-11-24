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

function rate_a_game(id) 
{
  $('#ratings').table({url: '/?rating/show_questions');
  var wizard = $('#rate_game').wizard().data('wizard');
  wizard.start(3);
  
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
  
  $("#next1").click(function() {
    $('#question_table').html(jq_submit('do.php/rating/show_questions','game_id'));
  });
  
  $("#question_next").click(function() {
//    save_questions(<?="$qmin,$qmax"?>);
  });
  
  $("#prev_no,#board_next").click(function() {
    save_game();
  });
}  
