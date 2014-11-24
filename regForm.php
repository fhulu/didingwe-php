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
    <script type="text/javascript" src="select.js"></script>
    <script type="text/javascript" src="../common/ajax.js"></script>
    <script type="text/javascript" src="../common/dom.js"></script>
    <link type="text/css" rel="stylesheet" href="regForm.css"></link>
    <script type="text/javascript">
      setInterval("disable_unset('submit','name,reg_number,telephone,email,country,username,password')",100);
    </script>
	

  </head>
<body>
  <h3 >Vendor Registration Form</h3>
   <form name="regform" action= "regForm.php"  method="POST" 
   onsubmit="return ajax_mconfirm('do.php/vendor/check_name,check_reg_number','name,reg_number')">
    <div class="labels">
      <div class="line">Full Name: </div>
      <div class="line">Register_Number:</div>
      <div class="line">Telephone:</div>
      <div class="line">Email Address:</div>
      <div class="line">Country:</div>
      <div class="line">UserName:</div>
      <div class="line">Password:</div>
    </div>
    <div class="controls">
    <div class="line">
      <input type='text'id='name' name='name'  />
    </div>
    <div class="line">
      <input type='text' id='reg_number' name='reg_number'  />
    </div>
		<div class=line>
		  <input type='text' name='telephone'  />
		</div>
    <div class="line">
      <input type='text' name='email'   />
		</div>
    <div class=line>
      <input type='text' name='country'  />
		</div>
    <div class="line">
		  <input type='text' name='username'  />
    </div>
    <div class="line">
      <input type='password' name='password'  />
    </div>
    <div class="line">
      <input type="submit" id='submit' value="Submit" >
      <input type="reset"  value="Reset" >
    </div>
    </div>
  </form>
</body>
</html>

