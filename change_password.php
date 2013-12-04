<?php
require_once('../common/select.php');
?>

<title>Change Password</title>
<script type='text/javascript' src='common/page_wizard.js'></script>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<script>

$(function(){ 
  $('#info,change').loadChildren('/?a=user/info');
  var wizard = $('#wizard').pageWizard().data('pageWizard');
  $('.info_next').sendOnClick('#email', '/?a=user/start_reset_pw');
  $('.change_next').checkOnClick('#email,#change *', '/?a=user/reset_pw');
  wizard.start(2); 
});
</script>
<div id="wizard" >
  <div id=info caption="Start Reset Password" wizard=top next>
    <span class=ajax_result></span>
    <p> As as security measure to ensure that you are the owner of this account,
      the system will send you a One Time Password (OTP) to <b id="cellphone"></b>. </p>
    <p>If you think this number is incorrect, please ask your System Administrator to 
      update your details on the system with the correct cellphone number. </p>
    <div class="form">
      <p>* Email:</p><a><input type='text' id='email' readonly /><span>Your e-mail address for e-mail notification's.</span></a>
    </div>
    <p>Please click <b>Next</b> to continue</p>
  </div>
  <div id="change" caption="Enter New Password" back next>
    <p>Please enter the One Time Password (OTP) that you received on <b id="cellphone"></b>.
      If you have not received the OTP, please ask your System Administrator to reset the password for you.</p>
    <div class="form">
      <p>One Time Password:</p><input change type="text" id="otp"/>
      <p>New Password</p><input login type="password" id="password"/>
      <p>Confirm New Password</p><input change type="password" id="password2"/>
    </div>
  </div>
  <div id=success caption="Thank you">
    <p>Thank you</p>
    <p>Your password has been changed successfully. You can continue to use the system.</p>
  </div>
</div>

