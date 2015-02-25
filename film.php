<?php
require_once('../common/session.php');
require_once('../common/db.php');
require_once('../common/table.php');


class film 
{
  //A function to add a game into a DB
  static function save($request)
  {    
          

    global $db,$session;
    
    $title = $request[title];
    $description =  $request[description];
    $director  = $request[director];
    $type = $request['type'];
    $format = $request['format']; 
    $running_time  = $request[running_time];
    $genre = $request[subject_genre];
    $director = $request[director];
    $cast = $request[cast];
    $language = $request[language];
    $previous_advice = $request[previous_advice];
    $prev_certificate_number = $request[certificate_number];
    $release_type  = $request[release_type];
    $status='amat';
    $link=$request[link];
    
    if($link !='')
      $status='matr';
      
    $partner_id = $session->user->partner_id;
    $publisher = $db->read_one_value("select co_name from vendor where partner_id=$partner_id");
    
    $sql = "INSERT INTO film(title,synopsis,running_time,genre_code,publisher,director,cast,certificate_no,prev_format_code,language_code,release_type,link)
            VALUES('$title','$description','$running_time','$genre','$publisher','$director','$cast','$prev_certificate_number','$format','$language','$release_type','$link')";
       
    $film_id = $db->insert($sql);
    
    //payment::start('fpb_submit_film', $film_id, $title, 'publication::payment_result');
     
    $user_id = $session->user->id;
    $sql = "INSERT INTO partner_film(partner_id,film_id,user_id,status)
            VALUES($partner_id,$film_id,$user_id,'$status')";
    $db->insert($sql);
    #user::audit('submit_film', $film_id, $title);
  }
  
     
  //To check if a film already exits in the DB, validating by the name field
  static function check_name()
  {
    
    $title = $_REQUEST[title];
    
    global $db;
    if($db->exists("select id from film where title = '$title'"))
      echo "This film name already exists in the database";
    
  }
  
   static function addfilm()
  { 
  global $db;
    $title = $_GET['title'];
    $director = $_GET['director'];
    $previous_date = $_GET['previous_class_date'];
    $previous_decision= $_GET['previous_decision'];
    $previous_title= $_GET['previous_title'];
    $type= $_GET['film_type'];
    $format= $_GET['format']; 
    $running_time = $_GET['running_time'];
    $genre= $_GET['subject_genre'];
    $director= $_GET['director'];
    $cast= $_GET['cast'];
    $language= $_GET['language'];
    $previous_advice= $_GET['previous_advice'];
    $certificate_number= $_GET['certificate_number'];
    $previous_format = $_GET['previous_format'];
    $release_type = $_GET['film_type'];
 
 
   $sql = "INSERT INTO film(date_of_prev_classification,prev_classification,title,prev_title,running_time,genre_code,director,cast,certificate_no,prev_format_code,format_code,language_code,release_type)
            VALUES('$previous_date','$previous_decision','$title','$previous_title','$running_time','$genre','$director','$cast','$certificate_number','$previous_format','$format','$language','$release_type')";
    $db->insert($sql);
    
    
  }
  static function verify()
	{  
		$title = $_REQUEST[title];
		$type = $_REQUEST[film_type];
    $format = $_REQUEST[format];
		$time = $_REQUEST[running_time];
    $genre = $_REQUEST[subject_genre];
		$director = $_REQUEST[director];
    $cast = $_REQUEST[cast_includes];
		$language = $_REQUEST[language];
    $certificate = $_REQUEST[certificate_number];
    $timeREgex="/^[1-9][0-9]$/";
    
		if ($title =='')
		{
		  echo "!Please enter film title"; 
		  return;
		}  
    
    if ($type == '--Select Type--')
		{
		 echo "!Please Select a type."; 
		 return;
		} 
    
   if ($format == '--Select format--')
		{
		 echo "!Please select a format."; 
		 return;
		}
    
     if (!preg_match($timeREgex, $time)) 
    {
      echo "!Please enter a valid time.";
      return;
    }
    if ($genre == '--Select genre--')
		{
		 echo "Please select genre."; 
		 return;
		} 
    
    if ($director == '')
		{
		 echo "Please enter director's name."; 
		 return;
		} 
    
    if ($cast == '')
		{
		 echo "Please enter the cast."; 
		 return;
		} 
    if ($language == '--Select language--')
		{
		 echo "Please Select language."; 
		 return;
		} 
    
    if ($certificate == '')
		{
		 echo "Please enter the certificate number."; 
		 return;
		} 
  }
  
   static function previous_verify()
	{  
		$title = $_REQUEST[previous_title];
		$decision = $_REQUEST[previous_decision];
    $advice = $_REQUEST[consumer_advice];
		$format = $_REQUEST[previous_format];
   
    
		if ($title =='')
		{
		  echo "!Please enter film title"; 
		  return;
		}  
    if ($decision == '')
		{
		 echo "!Please enter classification decision."; 
		 return;
		} 
    
    if ($advice == '')
		{
		 echo "!Please enter consumer advice."; 
		 return;
		} 
    
   if ($format == '--Select format--')
		{
		 echo "!Please select a format."; 
		 return;
		}
    
    
  }
  static function publication_verify()
	{  
		$title = $_REQUEST[p_title];
		$issue= $_REQUEST[issue_edition];
    $date = $_REQUEST[publication_date];
		$description = $_REQUEST[description];
    $edition="/^[0-9]{1,4}$/";
   
		if ($title =='')
		{
		  echo "!Please enter publication title"; 
		  return;
		}  
    
   if (!preg_match($edition, $issue)) 
    {
		  echo "Please enter numeric values for  edition";
		 return;
		} 
    
   if ($date == '')
		{
		 echo "!Please select a date."; 
		 return;
		}
    
    
    if ($description == '')
		{
		 echo "Please enter description."; 
		 return;
		} 
    
  }
  
  static function publisher_verify()
	{  
		$title = $_REQUEST[p_title];
    $telephone = $_REQUEST[telephone];
		$fax_num= $_REQUEST[fax];
    $email_address = $_REQUEST[email];
    $emailRegEx ="/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i";
    $telRegEx = "/^(0[1-8])|\+[1-9][0-9]{8,}$/"; 
    $faxRegEx = "/^(0[1-8])|\+[1-9][0-9]{8,}$/"; 
   
		
		
   
		if ($title =='')
		{
		  echo "!Please enter publication title"; 
		  return;
		}  
    
   if (!preg_match($telRegEx, $telephone)) 
    {
		
		  echo "Please enter a valid telephone number";
		 return;
		} 
    
  if (!preg_match($faxRegEx, $fax_num)) 
		{
		  echo "Please enter a valid fax number";
		 return;
		} 
    
    
   if (!preg_match($emailRegEx, $email_address)) 
    {
		
		  echo "Please enter a valid email address";
		 return;
    } 
    
  }
  
  
  
  
  static function addpublication()
  { 
    global $db;
    $title = $_GET['p_title'];
    $edition = $_GET['issue_edition'];
    $publication_date = $_GET['publication_date'];
    $publisher= $_GET['publisher'];
    $address= $_GET['address'];
    $telephone= $_GET['telephone'];
    $fax= $_GET['fax']; 
    $email = $_GET['email'];
    $description= $_GET['description'];
   
   $sql = "INSERT INTO publication(title,edition,publication_date,publisher,address,telephone,fax,email,description)
            VALUES('$title','$edition','$publication_date','$publisher','$address','$telephone','$fax','$email','$description')";
    $db->insert($sql);
  }
  
   
  
}  
?>
