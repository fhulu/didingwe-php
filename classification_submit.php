<?php
require_once('../common/select.php');
require_once('../common/ifind.php');
require_once('../common/db.php');



?>

<title>Vendors Registration Form </title>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<script type="text/javascript" src="../common/dom.js"></script>
<script type="text/javascript" src="../common/ajax.js"></script>
<script type="text/javascript" src="jquery/ui-min.js"></script> 
<script type='text/javascript' src='../common/wizard.js'></script>
<script type="text/javascript" >

$(function() {

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

$("#publication_date").datepicker({
  dateFormat: 'yy-mm-dd',
  onSelect: function(dateText, inst) {
    //Get today's date at midnight
    var today = new Date();
    today = Date.parse(today.getMonth()+1+'/'+today.getDate()+'/'+today.getFullYear());
    //Get the selected date (also at midnight)
    var selDate = Date.parse(dateText);
    if(selDate <= today) {
      //If the selected date was before today, continue to show the datepicker
      $('#publication_date').val('');
      $(inst).datepicker('show');
    }
  }      
});

$('#general,#exemption').click(function() {
  $('#reg_type_next').attr('steps', 1);
});

$('#exemption').click(function() {
  $('#film_submit').attr('steps', 4);
})

$('#publication').click(function() {
  $('#reg_type_next').attr('steps', 4);
});

$('#film_submit').click(function(event){
    jq_confirm('do.php/film/verify','title,film_type,format,running_time,subject_genre,director,cast_includes,language,certificate_number', event);
});

$('#prev_next').click(function(event){
   jq_confirm('do.php/film/previous_verify','previous_title,previous_decision,consumer_advice,previous_format', event);
});
$('#publication_submit').click(function(event){
    jq_confirm('do.php/film/publication_verify','p_title,issue_edition,publication_date,description', event);
   //jq_submit('do.php/film/addpublication','title,issue_edition,publication_date,publisher,address,telephone,fax,email,description');
    return false;
  
});

$("#notes_next").click(function(){  
  jq_submit('do.php/film/addfilm','previous_class_date,previous_decision,title,previous_title,running_time,subject_genre,director,cast_includes,certificate_number,previous_format,format,language,film_type');
  return false; 
});

var wizard = $('#wizard').wizard().data('wizard');
wizard.last_url = '/?c=home';
wizard.start();

});


</script>


<style>
.input
{
width: 270px;
height: 20px;
}
select
{
width: 200px;
height: 23px;
border-radius: 8px;
}

.button-medium
{
width: 80px;
border-radius: 8px;
font-size: 12px;
}  
.labels
{
width: 200px;
font-size: 12px;
}
.controls
{

font-size: 12px;
}


.nav
{
padding-top: 10px;
float: right;
}

p
{
font-size: 12px;
}

i
{
font-size: 12px;
}

</style>  



<div id="wizard" style="width:500px;">
  <div title=" Type of Classification " style="width:470px;height:370px;">
    <p> I would like to submit an application for classification of:</p>
    <div class="container">
      <div class="controls">
      <div class="container" style="width:350px;">
        <input type='radio' name='class_type' id="general"  value='general' >Film</input><br>
        <p><i>Submission of Film for Classification Section 18(1) of the Film and Publication Act, 1996, as amended</i></p>
        <input type='radio' name='class_type'  id="exemption" value='exemption'>Exemption</input><br>
        <p><i>Classification of music dvd's, educational films, children's movies </i></p>
        <input type='radio' name='class_type'  id="publication" value='publication'>Publication</input><br>
        <p><i>Submission of Film for Classification Section 16(2) of the Film and Publication Act, 1996, as amended</i></p>
      </div>      
      <div class=nav>
        <button wizard=next id=reg_type_next class="button-medium">Next</button>          
      </div>
      </div>
    </div>  
  </div>

  <div title="Classification of films" style="width:550px;height:470px;"> 
    <p><strong>The Film amd Publication Board may refuse to process yor application if this form is not fully completed.</strong></p> 
    <div class="container">
      <div class="labels">
        <div class="line" >Title:</div>
        <div class="line" >Type:</div>
        <div class="line" >Format: </div>
        <div class="line">Running Time in Minutes:</div>
        <div class="line">Subject/Genre:</div>
        <div class="line" >Director:</div>
        <div class="line" style="height:60px;" >Cast Includes:</div>
        <div class="line" >Language:</div>
        <div class="line" >Certificate Number:</div>
      </div>
      <div class="controls"  >
        <div class="line">
          <input type='text' name='title' id="title" class="input" />
        </div> 
        <div class="line">
          <select  name='film_type' class="input">
          <?= select::add_items("--Select Type--|Original|Re-release",''); ?>
          </select>  
        </div>
        <div class="line">
          <select  name="format" id="format"  class="input">
          <?= select::add_db("select code from format",0,'--Select format--'); ?>
          </select>  
        </div>
        <div class="line">
          <input type='text' id="running_time" name='running_time'  class="input"  />
        </div> 
         <div class="line">
            <select name='subject_genre' id='subject_genre' class="input" >
              <?= select::add_db("select code, description from genre", 0,0,'--Select genre--'); ?> 
            </select>
        </div>
        <div class="line">
          <input type='text' name='director'  id="director"class="input"  />
        </div>
        <div class="line" style="height:60px;" >
           <textarea rows="10" cols="30"id="cast_includes" name='cast_includes'  class="input" style="height:55px;width:270px;"  ></textarea>
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
    <div class="nav" >
      <button wizard=back class="button-medium">Back</button>
      <button wizard=next id=film_submit  class="button-medium">Next</button>
    </div>
  </div>  

  <div title="Previous Classification" style="width:440px;height:220px">
    <p>Has this film been previously classified before?</p>
    <div style="margin-left: auto; margin-right: auto; width: 180px">
      <input type="button" class = "button-medium" value="Yes"  wizard=next>
      <input type="button" class = "button-medium" value="No" id="prev_no" wizard=next steps=3 />
    </div>
    <br><br><br>
    <input type="button" class = "button-medium" value="Back"  wizard=back>
  </div>

  <div title="Previous Film Details" style="width:500px;height:270px">
    <div class="labels"> 
      <div class="line" >Title(if different):</div>
      <div class="line">Classification Decision:</div>
      <div class="line" >Consumer Advice:</div>
      <div class="line">Date of Classification:</div>
      <div class="line">Format: </div>
    </div>     
    <div class="controls">
      <div class="line">
        <input type='text'id="previous_title" name='previous_title'  class="input" />
      </div> 
      <div class="line">
        <input type='text' id="previous_decision" name='previous_decision' class="input" />
      </div> 
      <div class="line">
        <input type='text' id="consumer_advice" name='consumer_advice'  class="input"/>
      </div>
      <div class="line">
        <input type='text'id="previous_class_date" title="Valid date: yyyy-mm-dd" name='previous_class_date' class="input"/>
      </div>
      <div class="line">
        <select  name="previous_format" class="input">
        <?= select::add_db("select code from format",0,'--Select previous format--'); ?>
        </select>  
      </div> 
    </div>
    <div class="line"> 
      <input type="button" class = "button-medium" value="Back"  wizard=back>
      <input type="button" class = "button-medium" value="Next" steps=2 id="prev_next" wizard=next />
    </div>
  </div>


  <div  title="Submission of a Publication for Classification" style="width:500px;height:550px; ">
    <p><strong>The Film amd Publication Board may refuse to process yor application if this form is not fully completed.</strong></p> 
    <div class="container" >
      <div class="labels" style="width:190px;">
        <div class="line" >Title: </div>
        <div class="line">Issue or Edition:</div>
        <div class="line">Date of Publication:</div>
        <div class="line" >Publishers(if not the applicant):</div>
        <div class="line" style="height: 65px">Address: </div>
        <div class="line">Telephone:</div>
        <div class="line">Fax:</div>
        <div class="line" >E-mail:</div>
        <div class="line">Description of Content:</div>
      </div>	  
      <div class="controls" > 
        <div class="line">
          <input type='text' id="p_title" name='p_title' class="input" />
        </div> 
        <div class="line">
          <input type='text' id="issue_edition" name='issue_edition' class="input" />
        </div> 
        <div class="line">
          <input type='text'id="publication_date" name='publication_date' class="input"/>
        </div> 
        <div class="line">
          <input type='text' id="publisher" name='publisher' class="input" />
        </div> 
        <div class="line" style="height:65px;" >
          <textarea rows="5" cols="30" name='address'  class="input" style="height:65px;width:270px;"  ></textarea>
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
          <textarea rows="15" cols="30"id="description" name='description'  class="input" style="height:70px;width:270px;"  ></textarea>
        </div>
      </div>
      <div class="line"></div>
      <div class="line"></div>		
    </div>
    <div class="nav" >
      <button wizard=back class="button-medium">Back</button>
      <button wizard=next steps=2 id=publication_submit class="button-medium">Next</button>
    </div>       	
  </div>

  <div title="Notes" style="height:300px;width:750px;">
    <ol>
      <li>Proof of your right to distribute and/or exhibit this film in South Africa, in the format submitted, must be attached to this form.</li><br>
      <li>Unless exempted from the cash-on submission basis, payment in full of the applicable amunt must be made before this application may be processed.
      Where payment has been made by direct deposit into the account of the Board, proof of such deposit must be attached to this form.</li><br>
      <li>You must provide, free of charge, a copy of this film, as soon as it become available in home-entertainment format, 
      to the Board for its records. Section 4 of the Films and Publications Regulations, 1999.</li>
    </ol>
    <button id=notes_next wizard=next  class="button-medium">Next</button>
  </div>

  <div title="Thank you" style="height:250px;">
    <p>Thank you. </p>
    <div>
      <button id=close wizard=close class="button-medium">Close</button>
    </div>
  </div>   
</div>
