<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('../common/table.php');
   
   

   class question
  {
    
    static function save($request)
    {
    
      global $db;
      $question = $request[question];
      $age =  $request[age];
      $advice =  $request[advice];
      $number =  $request[number];
      
       
     
      if($request[number] != ''){
        $db->exec("UPDATE question SET question='$question',age =$age ,consumer_advice='$advice'
                  WHERE number = $number");
      return;
      }
      if($request[number] == ''){
        $sql="INSERT INTO question (question,age,consumer_advice) VALUES('$question',$age,'$advice' )" ;
        $number = $db->insert($sql);
        echo $number;
        return;
      }    
      
    }
    static function delete($request)
    {
 
      global $db;
      $number =  $request[number];
   
      $sql = "DELETE FROM question where number=$number";
      $db->exec($sql);
        

    }
    
    static function table($request)
    {
      $headings = array('~No','~Question','~Age','~Consumer Advice','');
      $table = new table($headings,table::TITLES | table::ALTROWS);
      $table->set_callback(function (&$row_data, $row_num, &$attr) 
      {
        $attr .= " id=" .$row_data['number'];
                
        $row_data['edit'] = "<img type='image' src='edit16.png' onclick='editQuestion(this);'/> ".
                            "<img type='image' src='remove16.png' onclick='deleteQuestion(this);'/>";

        return true;
      });
      
      $table->set_heading("Manage Questions");
      $table->set_options($request);
      $table->show("select number,question, age ,consumer_advice, '' edit from question"); 
    }
    
  }
  
?>

    

