<?php
require_once('../common/user.php');
user::verify('feature_request');
?>
 
<title>Feature Request </title>
<script type='text/javascript' src='common/page_wizard.js'></script>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<script>

$(function() {

  var wizard = $('#wizard').pageWizard().data('pageWizard');
 
  $("#update")
  .checkOnClick('#identity .form *', '/?a=feature/check')
  .sendOnClick('#identity .form *', '/?a=feature/save');
        
  wizard.start();
 
});
</script>


<div id="wizard" >
  <div id=identity caption="Feature Request">
    <span class=ajax_result></span>
    <p> We are interested in your ideas on how we can improve our products.<br> Please use this form to request new features or suggest modifications to existing features.</p>
    <div class="form" style="width: 550px">
      <p>Feature Title:</p><a><input type='text' id='title'  /><span>The title of the feature request.</span></a>  
     <p style="vertical-align: top" >Brief Description: </p><a><textarea id='description' style="height:200px" ></textarea><span>Elaborates why you request this feature.</span></a>
       <button wizard=next id=update style="margin-left: 290px">Submit</button>
    </div>
  </div>
   

  <div id=thank caption="Thank you">
    <p>Thank you. Request received  </p>
    <a href="/home.html">Home</a>
  </div>
  
</div> 
