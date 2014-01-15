<script type='text/javascript' src='common/page_wizard.js'></script>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<style>
  .page-wizard>div>p {
    border-bottom: 1px solid lightgray;
    padding-bottom: 10px;
    margin-left: 10px;
    margin-right: 10px;
  }
  .form button
  {
    left: 10px;
  }
  
</style>
<script>
$(function() {
  $('#forgot').pageWizard();
  $('.id_next').checkOnClick('#id *', '/?a=user/start_reset_pw', {method: 'get'} );
  $('.reset_next').checkOnClick('#forgot *', '/?a=user/reset_pw' );
});
</script>
<div id=forgot>
  <div id="id" caption="Identification Info" next>
    <span class=ajax_result></span>
    <p>Please enter the email address or the cellphone number you used when you first registered.
     You will then receive an SMS with a One Time Pin(OTP) which will allow you to reset the password.</p>
     <div class=form>
       <p>Email Address:</p><a><input type="text" id="email"><span>Email address used when registering</span></a>
       <p>Cellphone Number:</p><a><input type="text" id="cellphone"><span>Cellphone number used when you first registered:</span></a>
     </div>
  </div>
  <div id='reset' caption="One Time Password" back next>
    <p>Please enter the One Time Pin(OTP) that you received on the cellphone you used when you first registered.
       The new password must be entered twice to ensure confirm correctness.</p>
    <div class=form>
      <p>OTP:</p><a><input type="text" id="otp"><span>One Time Pin receved on cellphone </span></a>
      <p>New Password:</p><a><input type="password" id="password"><span>Your password is a secret word that you will use to gain access to the system. The password is case sensitive.</span></a>
      <p>Confirm Password:</p><a><input type="password" id="password2"><span>Same password as above to confirm correctness</span></a>
    </div>
  </div>
  <div caption="Success" back next>
    <p>Your password has been changed successfully, you can now log into the system with your new password.</p>
    <a href="login.html" class="hcenter">Login</a>
  </div>
</div>