<?php
require_once('../common/session.php');
require_once('../common/select.php');
require_once('../common/db.php');

?>
 <link type="text/css" rel="stylesheet" href="gct.css"></link>
<script type='text/javascript' src='../common/dom.js'></script>
<script type="text/javascript" src="../common/ajax.js"></script> 
<script type="text/javascript" src="jquery/min.js"></script> 
<script type="text/javascript" src="jquery/ui-min.js"></script> 


<script type="text/javascript">
$(function(){
   $('#close').click(function(){
   
      var now = new Date();    
      
      
      var previous_class_date = $('#previous_class_date').val();
      
      if(previous_class_date > now){
        alert("Previous Classification Date can not be greater than the Current Date");
        return false;
      } 
      var dateRegEx =  /^(19|20)[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/;
      var timeRegEx=/^[0-9]{3}$/;
      if ($("input[name='previous_class_date']").val().search(dateRegEx)== -1)
      {
      alert("Invalid date");
      return false;
      }
      
      if ($("input[name='running_time']").val().search(timeRegEx)== -1)
      {
      alert("Invalid time, please insert a number.");
      return false;
      }
      
     jq_submit('do.php/game/addfilm','previous_class_date,previous_decision,title,previous_title,running_time,subject_genre,director,cast,certificate_number,previous_format,format,language,film_type');
      return false; 
     
    });
    
    $("#previous_class_date").datepicker({
      dateFormat: 'yy-mm-dd',
      onSelect: function(dateText, inst) {
        //Get today's date at midnight
        var today = new Date();
        today = Date.parse(today.getMonth()+1+'/'+today.getDate()+'/'+today.getFullYear());
        //Get the selected date (also at midnight)
        var selDate = Date.parse(dateText);
        if(selDate >= today) {
          //If the selected date was before today, continue to show the datepicker
          $('#previous_class_date').val('');
          $(inst).datepicker('show');
        }
    }      
  });

});
 
  
</script>
<meta http-equiv="X-UA-Compatible" content="chrome=1">
<title>Film View</title>
<link type="text/css" rel="stylesheet" href="gct.css"></link>

<style>
.input
{
  width: 200px;
}
.labels
{
  width: 260px;
}
.controls
{
  right: 0px;
  
}
</style>  
<div class = "info"  style="width:490px;background-color:#ffffbe;">
  <h2><strong>Application for classification of films</strong></h2> 
<fieldset class = "infomation" >
      <LEGEND>Previous Film Details</LEGEND> 
        <div class="labels">
          <div class="line">Date of Classification</div>
          <div class="line">Classification Decision</div>
          <div class="line" >Title(if different)</div>
          <div class="line" >Consumer Advice</div>
          <div class="line">Format </div>
        </div>
        <div class="controls">
          <div class="line">
          </div>
          <div class="line">
            <input type='text'id="previous_class_date" title="Valid date: yyyy-mm-dd" name='previous_class_date' class="input"/>
          </div> 
          <div class="line">
            <input type='text' id="previous_decision" name='previous_decision' class="input" />
          </div> 
          <div class="line">
            <input type='text'id="previous_title" name='previous_title'  class="input" />
          </div> 
           <div class="line">
        <input type='text' id="consumer_advice" name='consumer_advice'  class="input"/>
        </div>
        <div class="line">
          <select  name="previous_format" class="input">
          <?= select::add_db("select code from format", 0,0,'--Select previous format--'); ?>
          </select>  
          </div> 
        </div>
      </fieldset>
  <div class="container">
    <div class="labels">
      <div class="line" >Title(for distribution/exhibition)</div>
      <div class="line" >Type</div>
      <div class="line" >Format </div>
      <div class="line">Running Time in Minutes</div>
      <div class="line">Subject or Genre</div>
      <div class="line" >Director</div>
      <div class="line" style="height:60px;" >Cast Includes</div>
      <div class="line" >Language</div>
      <div class="line" >Certificate Number</div>
   
    </div>
    <div class="controls" >
      
      <div class="line">
        <input type='text' name='title' id="title" class="input" />
      </div> 
      <div class="line">
        <select  name='film_type' class="input">
        <?= select::add_items("--Select Type--|Original|Re-release",''); ?>
        </select>  
      </div>
      <div class="line">
        <select  name="format" class="input">
        <?= select::add_db("select code from format", 0,0,'--Select format--'); ?>
        </select>  
      </div>
      <div class="line">
        <input type='text' id="running_time" name='running_time'  class="input"  />
      </div> 
       <div class="line">
          <select name='subject_genre' class="input" >
            <?php select::add_db("select code, description from genre", 0,0,'--Select genre--'); ?> 
          </select>
      </div>
      <div class="line">
        <input type='text' name='director'  id="director"class="input"  />
      </div>
      <div class="line" style="height:60px;" >
         <textarea rows="10" cols="30"id="cast" name='cast_includes'  class="input" style="height:55px;width:200px;"  ></textarea>
      </div>
      <div class="line" >
        <select  name="language" class="input">
        <?= select::add_db("select code from language", 0,'--Select language--'); ?>
        </select>  
      </div>
      
      <div class="line">
        <input type='text' id="certificate_number" name='certificate_number'  class="input"/>
      </div>
     
    </div>
  </div>
  <div class="line" >
    <input id=close type='submit'  value="Submit" class="button-medium" />
   
  </div> 
</div>
   
