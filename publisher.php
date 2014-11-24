<?php
require_once('../common/session.php');
require_once('../common/select.php');
require_once('../common/db.php');



class publisher

{ 
  static function add()
  {        
    if (sizeof($_POST) > 0) 
    {
    
      //echo $_POST["name"],$_POST["reg_number"] ,$_POST['Telephone'],$_POST["email"],$_POST["country"],$_POST["username"],$_POST["password"] ;
      $title = $_POST['title'];
      $fname = $_POST['fname'];
      $lname = $_POST['lname'];
      $country = $_POST['country'];
      $telephone = $_POST['telephone'];
      $email = $_POST['email'];
      $name = $_POST['name'];
      

      global $db;

      $sql ="INSERT INTO publisher(title,first_name,last_name,country,telephone,email,name)
            VALUES('$title','$fname','$lname','$country','$telephone','$email','$name')";      
            $db->exec($sql);

    }
  }
  static function check_email()
  {
    $email =  $_GET[email];
    global $db;
    if ($db->exists("select id from publisher where email = '$email'"))
      echo "!The Email Address is already exist";
  }
    
	
}

?>

