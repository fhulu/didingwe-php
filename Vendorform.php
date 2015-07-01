<?php
require_once('../common/db.php');
require_once('vendor.php');


                      
if (sizeof($_POST) > 0) {
    //echo $_POST["name"],$_POST["reg_number"] ,$_POST['Telephone'],$_POST["email"],$_POST["country"],$_POST["username"],$_POST["password"] ;
  $name = $_POST['name'];
  $reg_number= $_POST['reg_number'];
  $telephone = $_POST['telephone'];
  $email = $_POST['email'];
  $country = $_POST['country'];
  $username = $_POST['username'];
  $password = $_POST['password'];

  $sql ="INSERT INTO vendor(name,reg_number,telephone,email,country,username,password)
         VALUES('$name','$reg_number', '$telephone','$email','$country','$username','$password')";
  global $db;
  $db->exec($sql);

} 

?>
<html>
  <head>
  <title>Vendors Registration Form </title>
  <script type="text/javascript" src="../common/select.js"></script>
  <script type="text/javascript" src="../common/ajax.js"></script>
  <script type="text/javascript" src="../common/dom.js"></script>
  <!--
  <script type="text/javascript" src="../common/effects.js"></script>
  <script type="text/javascript" src="../common/fabtabulous.js"></script>
  -->
  <script src="../common/prototype.js" type="text/javascript"></script>
  <script src="../common/validation.js" type="text/javascript"></script>
  <link type="text/css" rel="stylesheet" href="vendor.css"></link>
  <script type="text/javascript">
  function FormValidationCallback(passed_validation, form) {
   if (!passed_validation) {
     $('flash').update('<div class="error">The form contains errors!</div>');
     $('flash').show();
   } else {
     $('flash').hide();
   }
      }
      document.observe("dom:loaded", function () {
        new Validation($('Vendorform'), {onFormValidate: FormValidationCallback});
      });
  </script>
  <script type="text/javascript">
  
  setInterval("disable_unset('submit','name,reg_number,telephone,email,country,username,password,password2')",100);
     
/*  function verifyemail()
  {  
    // require the right format of the Email
    var emailRegEx = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i;
    
    if (document.Vendorform.email.value.search(emailRegEx) == -1)
    {
      alert ("Please enter a valid email address.");
      return false;
    }
    return true;
  }
  */
  function verifyform(Vendorform)
  {  
 //   if (!verifyemail()) return false;
 
    if ((Vendorform.username.value.length < 5) || (Vendorform.username.length > 15))
    {
      alert("The username is the wrong length.\n");
      return false;
    } 
    
    if (Vendorform.password.value.length < 5)
    {
      alert("Please enter at least 5 characters in the \"Password\" field.");
      Vendorform.password.focus();
      return false;
    }

    // check if both password fields are the same
    if (Vendorform.password.value != Vendorform.password2.value)
    {
      alert("The two passwords are not the same.");
      Vendorform.password2.focus();
      return false;
    }
 
    
    return ajax_mconfirm('do.php/vendor/check_name,check_reg_number,check_email,check_username','name,reg_number,email,username');
  }
     
  
      
  </script>
	</head>
<body>
  <h3 >Vendor Registration Form</h3>
   <form id = "Vendorform" name="Vendorform" action= "Vendorform.php"  method="POST" 
    onsubmit="return verifyform(this);">
    <div id="flash" style="display:none;"></div>
    <div class="labels">
      <div class="line">Full Name  </div>
      <div class="line">Register Number </div>
      <div class="line">Telephone</div>
      <div class="line">Email Address </div>
      <div class="line">Country </div>
      <div class="line">UserName </div>
      <div class="line">Password </div>
      <div class="line">Confirm Password </div>
      
    </div>

    <div class="controls">
    <div class="line">
      <input type='text'id='name'class="required" name='name'  />
    </div>
    <div class="line">
      <input type='text' id='reg_number' name='reg_number'  />
    </div>
		<div class=line>
		  <input type='text' class="validate-alphanum" name='telephone'  />
		</div>
    <div class="line">
      <input type='text' id ='email'class="validate-email"  name='email'   />
		</div>
    <div class=line>
      <input type='text' name='country'  />
		</div>
    <div class="line">
		  <input type='text' id = 'username' name='username'  />
    </div>
    <div class="line">
      <input type='password' id = 'password'  name ='password'  />
    </div>
    <div class="line">
      <input type='password' id = 'password2'  name ='password2'  />
    </div>
    <div class=line>
      <input type='submit'  id='submit' value="Submit">
      <input type='reset'  value='Reset' >
    </div>
    </div>
  </form>

     
</body>
</html>

