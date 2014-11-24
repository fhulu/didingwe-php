<?php
require_once('../common/session.php');
require_once('../common/user.php');
//user::verify('feature_request');
?>
 
<title>Feedback Form </title>
<link type="text/css" rel="stylesheet" href="common/table.css"></link>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<script type="text/javascript" src='../common/page_wizard.js'></script> 
<script type="text/javascript" src='../common/table.js'></script> 
<script>

$(function() {
  $('#id').hide();

  var wizard = $('#wizard').pageWizard().data('pageWizard');
  var table = $('#platforms');
  table.table({ sortField: 'description', url: '/?a=feedback/ask'} );
  $("#update")
    .checkOnClick('#identity  .form *', '/?a=feedback/check', { method: 'get'})
    .sendOnClick('#identity .form *', '/?a=feedback/save', { method: 'get'});

   
     
   
 wizard.start();
 
});
</script>


<div id="wizard" >
  <div id=identity caption="Feedback ">
    <span class=ajax_result></span>
    <p>Please describe the circumstances that led to your compliment, comment or complaint  by completing the form below. We will be sure to use your information to help us create a better OSS for you.</p>
    <div class="form" style="width: 550px">
       <p style="vertical-align: top;height:100px">Feedback Type</p>
      <a><div id=platforms_div><table id="platforms"></table></div>
        <span></span>
      </a>
      <p>Subject:</p><a><input type='text' id='title'  /><span>The title of the request.</span></a>  
      <p style="vertical-align: top" >Message: </p><a><textarea id='message' style="height:200px" ></textarea><span>Elaborates why you request this feature.</span></a>
    </div>
    <div class=nav>
      <p></p>
      <button wizard=next id=update class="hcenter" >Submit</button>
    </div>
  </div>
   

  <div id=thank caption="Thank you">
    <p>Thank you. Request received  </p>
    <a href="/home.html">Home</a>
  </div>
  
</div> 

