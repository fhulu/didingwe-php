<?php
  require_once('../common/session.php');
  require_once('game.php');
  require_once('../common/table.php');
  require_once('../common/db.php');
  
  $rating_id = $_SESSION[rating_id];
  $game_id = isset($_REQUEST[game_id])? $_REQUEST[game_id]: $_SESSION[game_id];
  $vendor_id = $_SESSION[user_id];
  $game_id = $_SESSION[game_id];

  
  class rating
  {    
    static function show_questions($request)
    {        
      $titles = array('No.','Does the game contain','No','Yes, intensity is'=>array('Low','Med','High'));
      $table = new table($titles, table::TITLES | table::ALTROWS | table::PAGEABLE);
      $table->set_key('number');
      $table->set_options($request);
      $table->set_callback(function($row) {
        $row['no'] = "<input type=radio value=0 >";
        $row['low'] = "<input type=radio value=1>";
        $row['medium'] = "<input type=radio value=2>";
        $row['high'] = "<input type=radio value=3 >";
      });
      $table->show("select number,question, '' no,'' low,'' medium,'' high from question order by number");
    }
    
    static function manage_questions()
    { 
     
      global $db;
      
      $sql = "select q.question,q.age,c.code from question q,classification c ,question_class qc 
            where q.number = qc.question_number and
                  c.code = qc.class_code";
      $titles = array('Question ','Age','Classfication');
      table::display($sql, $titles,table::TITLES | table::ALTROWS,"game",0,'radio');
     
    }
    
    static function save()
    {
      global $db,$session;   
      $user_id = $session->user->id;
      $game_id =  $_REQUEST[game_id];
      //Store into rating game_id and vendor_id
      $sql="INSERT INTO rating(game_id,user_id) VALUES($game_id,$user_id)" ;
      $_SESSION[rating_id] = $rating_id = $db->insert($sql);
      rating::save_answers($rating_id);
      
    }

    static function verify()
    {
      global $db;
      //Read two values from the DB and store them into two variables
      list($total_questions, $qmin, $qmax) 
        = $db->read_one("SELECT COUNT(1), min(number), max(number) FROM question");
      
      $answered_count = 0;
     
      for ($i = $qmin; $i <= $qmax; ++$i) {
        if(isset($_REQUEST["q$i"])){
          ++$answered_count; 
           
        }
      }
      
     if ($answered_count < $total_questions)
     
        echo "!Please answer all questions";
    }
    
    static function save_answers($rating_id) 
    {
      global $db;

      //To insert as many answers as there are questions
      $max_age = 0;
      $advices = array();
     
      list($qmin,$qmax) = $db->read_one("select min(number), max(number) from question");
      for ($q = $qmin; $q <= $qmax; ++$q){
         if(!isset($_REQUEST["q$q"])) continue;
       
        $answer = $_REQUEST["q$q"]; //set the ans to the value of the question

        //Insert the selected answer to the answer table
        $sql="INSERT INTO answer(rating_id,question_no,answer) VALUES($rating_id,$q,$answer)";    
        $db->insert($sql);
      
        //calculate rating
        if($answer == 0) continue;

        list($age, $advice) = $db->read_one("SELECT age, consumer_advice FROM question WHERE number=$q");
        if ($age > $max_age) $max_age = $age;
        if ($answer != 1 && !in_array($advice, $advices))
          $advices[] = $advice;      
      }
      
      if (sizeof($advices) > 1) {
        $key = array_search('PG', $advices);
        if ($key !== false) unset($advices[$key]);
      }
      
      $advices = implode('',$advices);
      rating::update($max_age,$advices,$rating_id);
      rating::show($rating_id);
     
    }
    
    static function update($max_age,$advice,$rating_id)
    {
      //Update the rating
      
      global $db;
      $db->exec("UPDATE rating SET max_age=$max_age, consumer_advice='$advice'
                WHERE id = $rating_id");
                
    }
    
    static function show($id=null)
    {
      //if (is_null($id)) {
        global $session;
        $rating_id = $_SESSION[rating_id];
        $id = $rating_id;
     // }
      
      //Show rating to the user
      $headings = array('Age Restriction','Consumer Advice');
      $table = new table($headings, table::TITLES);
      $table->set_heading("Your game is rated as:");
      $table->show("select max_age,consumer_advice from rating WHERE id = $rating_id ");
			
    }
    
    static function show_approved()
    {
      global $game_id;
      echo "The game ID is: $game_id";
      
    }
    
   
    static function history($request)
    {  
      $sql = "select g.id, r.date_rated, v.co_name,u.first_name,u.last_name,g.title, r.max_age,r.consumer_advice, '' rerate
                  FROM game g, rating r ,vendor v , mukonin_audit.user u
                  where g.id = r.game_id and
                  r.user_id = u.id and
                  v.id = u.partner_id";
   
      $headings = array('#id', '~Date Rated','~Company Name','~First Name','~Last Name','~Game Title','~Age','~Consumer Advice', 'Rerate');
      $table = new table($headings, table::TITLES | table::ALTROWS | table::FILTERABLE);
      $table->set_heading("Rating History");
      $table->set_key('id');
      $table->set_options($request);
      $table->set_callback(function($row) {
        $row['rerate'] = "<div class=rerate/> ";
      });
      $table->show($sql);
    }
  
 //End of class
  }
?>