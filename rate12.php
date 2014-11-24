<?php
require_once('../common/session.php');
require_once('../common/table.php');
require_once('../common/db.php');

	function show_radio (&$user_data, &$row_data, $row_num, &$attr)
	{
		$row_data[yes]= "<input  name=q$row_num type=radio  value=Yes >";
		$row_data[no]="<input  name=q$row_num type=radio value=No >";
		
		return true;
		
	}
?>
<html>
	<head>
		<title></title>
		<link type="text/css" rel="stylesheet" href="game.css">
		<script type="text/javascript" src="../common/dom.js"></script>
	</head>
	<body>
		
		<form name="rateform" action="rate12.php" method="post">
		<?php
			$sql = "select number,question,description,''yes,'' no from question";
			$titles = array('Question No.','Question','Description','Yes','No');
			table::display($sql, $titles,table::TITLES | table::ALTROWS,"game",0,'show_radio');
			
		?>
		<br />
		<input type="submit" value="Rate Me" name="rate"/>
		</form>
	</body>
</html>
<?php
	global $db;	
  global $rating_id;
  
  
  //=$_SESSION[rating_id];
  
  
  
if(isset($_POST['rate']))
{  // if form invoked from submit
	$question_str = '';
	for($i=0; isset($_POST['q'.$i]) ; $i++)
  {
    $question_str = ($i+1); 
		if($_POST['q'.$i] == 'Yes')
      $ans=1;
    else
      $ans=0;
    
    
    
    $sql="INSERT INTO answer(rating_id,question_no,answer) VALUES($rating_id,$question_str,$ans)";    
   $db->insert($sql);
    
    
  
    
		//echo "q".$i." is ".$_POST['q'.$i]."<br/>";
		//echo "string".$question_str;
		
	}
  echo "siigffjfjfj $rating_id";
  
	/*$question_str = substr($question_str, 0, strlen($question_str) - 1);					
	$query = "SELECT MAX(q.age) AS Rate, GROUP_CONCAT(qc.class_code ORDER BY c.code ASC SEPARATOR '')
				   AS code FROM question q ,question_class qc
           WHERE (q.number IN ($question_str))";
		//echo $question_str;
		//echo $query;
	$headings = array('Age','Rating');
	table::display($query, $headings, table::TITLES | table::ALTROWS, "game",0);
	*/
}


			
?>