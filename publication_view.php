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
   jq_submit('do.php/game/addpublication','title,issue_edition,publication_date,publisher,address,telephone,fax,email,description');
      return false; 
     
    });
    
    $("#publication_date").datepicker({
      dateFormat: 'yy-mm-dd',
      onSelect: function(dateText, inst) {
        //Get today's date at midnight
        var today = new Date();
        today = Date.parse(today.getMonth()+1+'/'+today.getDate()+'/'+today.getFullYear());
        //Get the selected date (also at midnight)
        var selDate = Date.parse(dateText);
        if(selDate >= today) {
          //If the selected date was before today, continue to show the datepicker
          $('#publication_date').val('');
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
<div class = "info"  style="width:500px;height:550px; background-color:#ffffbe;">
  <h2><strong>Submission of a Publication for Classification</strong></h2> 
	<fieldset class = "infomation"  >
      <LEGEND>Publication Details</LEGEND> 
        <div class="labels">
		  <div class="line" >Title </div>
          <div class="line">Issue or Edition</div>
          <div class="line">Date of Publication</div>
		  <div class="line" >Publishers(if not the applicant)</div>
          <div class="line" style="height: 65px">Address </div>
		  <div class="line">Telephone</div>
          <div class="line">Fax</div>
		  <div class="line" >E-mail</div>
          <div class="line">Description of Content</div>
        </div>
        <div class="controls">
		  <div class="line"></div> 
          <div class="line">
            <input type='text' id="title" name='title' class="input" />
          </div> 
		
          <div class="line">
            <input type='text' id="issue_edition" name='issue_edition' class="input" />
          </div> 
          <div class="line">
            <input type='text'id="publication_date" title="Valid date: yyyy-mm-dd" name='publication_date' class="input"/>
          </div> 
          <div class="line">
            <input type='text' id="publisher" name='publisher' class="input" />
          </div> 
		   <div class="line" style="height:65px;" >
				<textarea rows="5" cols="30" name='address'  class="input" style="height:65px;width:200px;"  ></textarea>
			</div>
          <div class="line">
            <input type='text'id="telephone" name='telephone'  class="input" />
          </div> 
           <div class="line">
				<input type='text' id="fax" name='fax'  class="input"/>
			</div>
			 <div class="line">
				<input type='text' id="email" name='email'  class="input"/>
			</div>
			<div class="line" style="height:60px;" >
				<textarea rows="15" cols="30"id="description" name='description'  class="input" style="height:70px;width:200px;"  ></textarea>
			</div>
		</div>	
		<div class="line" ></div>
		<div class="line" ></div>
		<div class="line" >
			<input id=close type='submit'  value="Submit" class="button-medium" />
		</div>       
	
	</fieldset>

</div>