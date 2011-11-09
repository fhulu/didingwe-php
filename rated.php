<?php
require_once("table.php");
require_once('db.php');

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
		<script type="text/javascript" src="dom.js"></script>
	</head>
	<body>
		
		<?php 
			$sql = "select id,question,description,'' yes, '' no from mukonin_fpb.questionnaire";
			$titles = array('Question No.','Question','Description','Yes','No');
			
		?>
		<form name="rateform" action="rated.php" method="post">
		<?php
			table::display($sql, $titles,table::TITLES | table::ALTROWS,"game",0,'show_radio');
			
		?>
		<br />
		<input type="submit" value="Rate Me" name="rate"/>
		</form>
		<?php
			
			for($i=0;$i<3;$i++){
				if($_POST['q'.$i] == 'yes'){
					$questionaire_str .= '1,';
				}
				$questionaire_str = substr($questionaire_str, 0, strlen($questionaire_str)-1);
				$sql="SELECT MAX(age) FROM questionnaire WHERE(id IN ($questionaire_str))";

			}
		?>
	</body>
</html>
