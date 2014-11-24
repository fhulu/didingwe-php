<?php
require_once('../common/table.php');
require_once('../common/db.php');
// for populating existing games list
require_once("../common/select.php");

	function show_radio (&$user_data, &$row_data, $row_num, &$attr)
	{
		$row_data[yes]= "<input  name=q$row_num type=radio  value=Yes >";
		$row_data[no]="<input  name=q$row_num type=radio value=No >";
		
		return true;
		
	}
?>
<html>
	<head>
		<title>Rate Game</title>
		<link type="text/css" rel="stylesheet" href="game.css">
		<script type="text/javascript" src="dom.js"></script> 
	</head>
	<body>
		<!Selecting an existing game >
		<h2 ><strong>Rate an existing game</strong></h2>
		<div class="labels">
			<div class="line">Rate Date</div>
			<div class="line">Game</div>
		</div>
		<div class="controls">
			<div class="line">
			<input type="text" id="DPC_calendar1b_YYYY-MM-DD" name="rate_date" size=37 value="<?= date('Y-m-d'); ?>" />
			<input type="hidden" id="DPC_TODAY_TEXT" value="Month"/>
			<input type="hidden" id="DPC_BUTTON_TITLE" value="Choose date"/>
			<input type="hidden" id="DPC_MONTH_NAMES" value="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']"/>
			<input type="hidden" id="DPC_DAY_NAMES" value="['Sun', 'Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat']"/>
			<input type="hidden" id="DPC_FIRST_WEEK_DAY" value="0"/>
			</div>
		
			<div class="line">
				<select id=id name="id">
					<?php 
						select::load_from_db("select id,name from game");
					?>
				</select>
			</div>
        </div><br /> <br /> <br />
	
		<! Answering questions of the existing games > 
		<form name="rateform" action="rgh_srcExample.php" method="post">
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
			
if(isset($_POST['rate']))
{  // if form invoked from submit
	$question_str = '';
	for($i=0; isset($_POST['q'.$i]) ; $i++){
		if($_POST['q'.$i] == 'Yes'){
			$question_str .= ($i+1).","; 
		}
		//echo "q".$i." is ".$_POST['q'.$i]."<br/>";
		//echo "string".$question_str;
		
	}
	$question_str = substr($question_str, 0, strlen($question_str) - 1);					
	$query = "SELECT MAX(age) As Rate, GROUP_CONCAT(Classification ORDER BY Classification ASC SEPARATOR '')
				AS Classification FROM question WHERE(number IN ($question_str))";
		//echo $question_str;
		//echo $query;
	$headings = array('Rate','Classification');
	table::display($query, $headings, table::TITLES | table::ALTROWS, "game",0);
	
}


			
?>