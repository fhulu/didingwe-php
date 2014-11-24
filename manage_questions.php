<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('../common/table.php');
  require_once('question.php');
  
?>

<script src="../common/table.js"></script> 
<script>
  function showEditBoxes(row)
  {
    var question = row.eq(1);
    $(question).html("<textarea name='Question' class='input' style='width:100%;'>"+$(question).text()+"</textarea>");
    var age = row.eq(2);
    var value = $(age).text();
    $(age).html("<select name='Age' >"+"<?= select::add_items(",--Select age--|10,10|13,13|14,14|16,16|18,18") ?>"+"</select>");
    $(age).find("select").val(value);
    var advice = row.eq(3);
    var value = $(advice).text();
    $(advice).html("<select name='Consumer_advice'>"+"<?= select::add_items(",--Select consumer_advice--|D|N|P|PG|S|V|SV|B|SL",'') ?>"+"</select>");
    $(advice).find("select").val(value);
  };
 
  function editQuestion(button)
  {
    var row = $(button).parent().parent();
    showEditBoxes(row.children());
    $(button)
      .attr("src","save16.png")
      .attr("onclick","saveQuestion(this)")
  };

  function deleteQuestion(button)
  {
    
    var row = $(button).parent().parent();
    var number = row.attr('id');
    $(button).parent().parent().remove();
    // ajax for deleting
    if(number != '')
    {
      $.get("do.php/question/delete",{number:row.attr('id')});
      return;  
       
    }
    
  };  
  
  function saveQuestion(button)
  {
    var row = $(button).parent().parent();
    var number = row.attr('id');

    //validation	 
    var valid = true;
    
    $(row).find("textarea,select").each(function() {
      if ($(this).val() == "") {
        alert("Value for "+$(this).attr('name') + " is empty, please fix before saving");
        valid = false;
        return false;
      }
    });
	  if (!valid) return;
 
    // button status
    $(button)
      .attr("src","edit16.png")
      .attr("onclick","editQuestion(this)")
      
   // ajax saving
    var question = row.find("textarea").val();
    var age = row.find('select').eq(0).val();
    var advice = row.find('select').eq(1).val(); 
   // $.get("do.php/question/save?question="+question +"&age="+ age +"&code=" +code +"&number=" +number);
    $.get("do.php/question/save", {question: question, age: age, advice: advice,number: number}, function(data) {
      row.attr('id',data);
    });

    // put values into td's
    row.find('textarea,select').each(function() {
      var td = $(this).parent();
      td.html($(this).val());
    });
      
  };
  
  $(function() {
    $("input[value=Add]")
      .click(function() {
        var table = $("table.game:first")[0];
        var newRow = table.insertRow(-1);
        $(newRow).attr('id','');
        newRow.insertCell(-1);
        newRow.insertCell(-1);
        newRow.insertCell(-1);
        showEditBoxes($(newRow).children());
        buttons = newRow.insertCell(-1);
        buttons.innerHTML =  "<img src='save16.png' action=save onclick= 'saveQuestion(this);' />"+
                         "<img src='remove16.png' action=remove onclick='deleteQuestion(this);' />";
        $('tr:odd').addClass('alt'); 
    });  

    $('#questions').table({ sortField: 'number', url: '/?a=question/table'});
  });
  

</script>

<table class="game" id="questions"></table>
<br>
<input type='button' value='Add' class='button-medium' />


