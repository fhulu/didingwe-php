<?php

require_once("db.php");

class menu
{
   static function show($parent_id, $li='li', $attr='')
  {
    echo "<ul $attr>\n";
    global $session;
    if ($session == '') {
      global $db;
      $functions = $db->read_column("select function_id from mukonin_audit.role_function rf, mukonin_audit.function f
      where rf.function_id = f.id and role_id = 0 and program_id = ".config::$program_id );
    }
    else {
      require_once("session.php");
      $functions = explode(',',$session->user->functions);
    }
    menu::show_subitems($parent_id, $functions, $li, 0);
    echo "</ul>\n";
  }
  
  static function show_subitems($parent_id, $functions, $li, $level)
  {
    
    $sql = "select function_id, m.id, parent_id, name, url, description from 
      mukonin_audit.function f, mukonin_audit.menu m
      where f.id = m.function_id and parent_id = $parent_id and program_id = ". config::$program_id;
    $sql .= " order by position"; 
    
    global $db;
    $items = $db->read($sql);
    foreach ($items as $item) {
      $function_id = $item['function_id'];
      if (!in_array($function_id, $functions)) continue;
      if ($level == 0 && $li != '') echo "<$li>";
      $url = $item['url'];
      $name = $item['name'];
      echo "<a href='$url'>$name</a>\n<span>\n";
      menu::show_subitems($item['id'], $functions, $li, $level+1);
      echo "</span>\n";
      if ($level == 0 && $li != '') echo "</$li>\n";
    }
	}
}
?>
