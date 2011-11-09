<?php
require_once("table.php");
require_once('db.php');
?>
<html>
	<head>
		<title></title>
		<link type="text/css" rel="stylesheet" href="game.css">
		<script type="text/javascript" src="dom.js"></script>
	</head>
	<body>

		<p><strong>Rate Question</strong></p>
		<?php 
		    $sql = "select id,question,age,classification from mukonin_fpb.questionnaire where id='2'";
			$fields = array('Id','Question','Age','Class');
			
			table::display($sql, $fields,table::TITLES | table::ALTROWS | table::TOTALS,"game");
		?>
	</body>
</html>
<?php
	function read_question ($sql,$age,$classification) 
	{
		$sql=
		
	}
	function rate($rows,$age,$classification)
	{
		
	}
	
?>