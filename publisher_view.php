<?php
require_once('../common/session.php');
require_once('../common/select.php');
require_once('../common/db.php');
require_once('vendor.php');

?>

  <meta http-equiv="X-UA-Compatible" content="chrome=1">
  <title>Publisher Details Form </title>
  <script type="text/javascript" src="../common/select.js"></script>
  <script type="text/javascript" src="../common/ajax.js"></script>
  <script type="text/javascript" src="../common/dom.js"></script>
  <link type="text/css" rel="stylesheet" href="gct.css"></link>
  <script type="text/javascript">
    setInterval("disable_unset('submit','title,fname,lname,reg_number,country,telephone,email,name,password,password2')",100);

    function is_valid_msisdn(msisdn)
    {

      var control = getElementByIdOrName(msisdn);

      if (control.value.search (/^0[1-8][1-9][0-9]+$/) == -1)
      {
        alert("Invalid Cell Phone Number");
        return false;
      }
      return true;

    }

    function verifyform(form)
    {

      // require the right format of the Email
      var emailRegEx = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i;

      if (form.email.value.search(emailRegEx) == -1)
      {
          alert ("Please enter a valid email address.");
          return false;
      }
      
      if (!ajax_mconfirm('do.php/vendor/check_email','email'))
         return false;
      
      if (is_valid_msisdn('telephone'))
        return true;
        
      return false;
    }	
  </script>
 
<form method="POST" action="do.php/publisher/add" onsubmit="return verifyform(this);">
  <fieldset  class = "details" style="width:370px; height: 400px;">
    <div class="centerbox">
      <h2 ><strong>Publisher Details</strong></h2>
    </div >
    <div class="container">
      <div class="labels" style="width:190px;">
        <div class="line">Title  </div>
        <div class="line">First Name  </div>
        <div class="line">Last Name  </div>
        <div class="line">Country </div>
        <div class="line">Phone Number</div>
        <div class="line">E-mail</div>
        <div class="line">Publisher Name </div>
      </div>
      <div class="controls">
        <div class="line">
          <select  name="title" class="input" >
            <?= select::add_items(",--Select Title--|Mr.|Mrs.|Miss|Dr",''); ?>
          </select>
        </div>
        <div class="line">
          <input type='text' name='fname'  class="input" />
        </div>
        <div class="line">
          <input type='text' name='lname'  class="input" />
        </div>
        <div class="line">
          <select  name="country" class="input" >
            <?= select::add_items(",--Select Country--|China|South Africa|Uganda|USA|UK",''); ?>
          </select>
        </div>
        <div class="line">
          <input type='text' name='telephone' class="input"  />
        </div>
        <div class="line">
          <input type='text' name='email' class="input"  />
        </div>
        <div class="line">
          <input type='text' name='name' class="input"  />
        </div>
        <div class="line" >
          <input type='submit' id='submit' value="Download" class="button-medium"/>
        </div>
        
      </DIV>
      <div class="icon">
        <!--img src="http://linden.qmessenger.test/data/images/7.jpg" style="Filter: Chroma(Color=#ffffff)"></img-->
      </div>
    </div>
  </fieldset>
</form>

