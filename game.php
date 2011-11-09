<?php
require_once('db.php');
require_once('table.php');

class game {
  static function show_game()
  {
    $sql = "select * from mukonin_fpb.game where id='3'";
    $fields = array('Id','Name','Description','Status');
	
	table::display($sql, $fields, null,'game');
  }
}
?>
