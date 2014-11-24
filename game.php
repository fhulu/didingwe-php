<?php
require_once('../common/session.php');
require_once('../common/db.php');
require_once('../common/table.php');
require_once('rating.php');


class game 
{
  //A function to add a game into a DB
  static function add()
  {
    global $db, $session;
    $title = $_GET[title];
    $sysnopsis = $_GET[sysnopsis];
    $country_of_origin = $_GET[country_of_origin];
    $year_of_production = $_GET[year_of_production];
    $format = $_GET[format];
    $platform_code = $_GET[platform_code];
    $genre_code = $_GET[genre_code];
    $publisher = $_GET[publisher];
    $version = $_GET[version];
    $release_date = $_GET[release_date];
    
    $board = $_GET[board]; 
    $reason = $_GET[reason];
    $rating = $_GET[rating];

    $sql = "INSERT INTO game(title,sysnopsis,country_of_origin,year_of_production,format,genre_code,publisher,
                        version,release_date)
              VALUES('$title','$sysnopsis','$country_of_origin','$year_of_production','$format','$genre_code',
              '$publisher',$version,'$release_date')";
         

    $game_id = $db->insert($sql);
    
    //Store platform code and the entered new game 
    $user_id = $session->user->id; //get user_id
   
    $sql = "INSERT INTO game_platform(game_id,platform_code, user_id)
            VALUES($game_id,'$platform_code',$user_id)";
    $db->insert($sql);
    
    //Store vendor and the entered new game 

    $vendor_id = $session->user->partner_id;
    $user_id = $session->user->id;
    $sql = "INSERT INTO vendor_game(vendor_id,game_id,user_id)
            VALUES($vendor_id,$game_id,$user_id)";
    $db->insert($sql);
    
    
    
    $sql = "INSERT INTO board_rating(board_id,vendor_id,game_id,user_id,rating,reason)
              VALUES('$board','$vendor_id','$game_id','$user_id','$reason','$rating')";
    
    $db->insert($sql);
 
   echo $game_id;
  }
  
  
  static function add_rating()
  {
    global $db, $session;
    $vendor_id = $session->user->partner_id;
    $user_id = $session->user->id;
    
    
    $board = $_GET[board]; 
    $reason = $_GET[reason];
    $rating = $_GET[rating];
    
    $sql = "INSERT INTO board_rating(board_id,vendor_id,game_id,user_id,rating,reason)
              VALUES('$board','$vendor_id','$game_id','$user_id','$reason','$rating')";
 
    $game_id = $db->insert($sql);
       
  }
     
  //To check if a game already exits in the DB, validating by the name field
  static function check_name()
  {
    
    $title = $_REQUEST[title];
    
    global $db;
    if($db->exists("select id from game where title = '$title'"))
      echo "!This Game Name Already Exists In The DB";
    
  }
  
   static function addfilm()
  { 
  global $db;
    $title = $_GET['title'];
    $director = $_GET['director'];
    $previous_date = $_GET['previous_class_date'];
    $previous_decision= $_GET['previous_decision'];
    $previous_title= $_GET['previous_title'];
    $type= $_GET['type'];
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
  static function film_verify()
	{  
		$title = $_REQUEST[title];
		$type = $_REQUEST[type];
		$genre_code = $_REQUEST[subject_genre];
	
    
		if ($title =='')
		{
		  echo "!Please enter Game Title"; 
		  return;
		}  
    
    if ($type == '')
		{
		 echo "Please Select a type."; 
		 return;
		}
 
    if (!preg_match($year, $year_of_production)) 
    {
      echo "Please enter a valid year.";
      return;
    }
    
		if ($platform_code == '')
		{
		 echo "Please Select a Platform ."; 
		 return;
		}
    
    if ($genre_code =='')
		{
		  echo "Please Select a Genre ."; 
		  return;
		}
		if(!is_numeric($version))
		{
		  echo "Please enter Numeric values for  Version";
		  return;
		}
   
    if (!preg_match($reg, $release_date)) 
    {
      echo "Please enter a valid date.";
      return;
    }   
    

  }

  static function game_verify()
	{  
		$title = $_REQUEST[title];
		$platform_code = $_REQUEST[platform_code];
		$genre_code = $_REQUEST[genre_code];
		$version = $_REQUEST[version];
    $country_of_origin = $_REQUEST[country_of_origin];
    $year_of_production = $_REQUEST[year_of_production];
		$release_date = $_REQUEST[release_date];
    $year="/^(19|20)[0-9]{2}$/";
    
		if ($title =='')
		{
		  echo "!Please enter Game Title"; 
		  return;
		}  
    
    if ($country_of_origin == '')
		{
		 echo "Please Select a Country ."; 
		 return;
		}
 
    if (!preg_match($year, $year_of_production)) 
    {
      echo "Please enter a valid year.";
      return;
    }
    
		if ($platform_code == '')
		{
		 echo "Please Select a Platform ."; 
		 return;
		}
    
    if ($genre_code =='')
		{
		  echo "Please Select a Genre ."; 
		  return;
		}
		if(!is_numeric($version))
		{
		  echo "Please enter Numeric values for  Version";
		  return;
		}
   
    
  }
  
  
  static function load($request)
  {
    global $db;
    $data = $db->read_one("select * from game where id = ". $request['id']);
    echo json_encode($data);
  }
}  
?>
