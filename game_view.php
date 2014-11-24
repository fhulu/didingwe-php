<?php
require_once('table.php');
require_once('db.php');
?>
<html>
	<head>
		<title>View Games</title>
		<link type="text/css" rel="stylesheet" href="game.css">
		<script type="text/javascript" src="dom.js"></script>

	</head>
	<body>
		<p><strong>View Games</strong></p>
		<?php 
		    $sql = "select * from mukonin_fpb.game where status='r'";
			$fields = array('Id','Name','Description','Status');
			table::display($sql, $fields, null,"game");
		?>
		
		<?php 
		  /*  
			//<p>View Vendor</p>
			$sql = "select * from mukonin_fpb.vendor where status='r'";
			$fields = array('Id','Name','Telephone','Email','Status');
			table::display($sql, $fields, null,"game");*/
		?>
		<p><strong>View Questionnaire</strong></p>
		<?php 
		    $sql = "select * from mukonin_fpb.questionnaire where id='$id'";
			$fields = array('Id','Question','Description','No','Rate');
			table::display($sql, $fields, null,"game");
		?>
	</body>
</html>
<?php
	function rate()
	{
	//todo:define logic for game rating 
		$question;$max_rate;
	}
?>