<?php

require_once("db.php");

class menu
{
   static function show($type, $li='li', $attr='')
  {
    if ($session == '') {
      global $db;
    
      $functions =  $db->read_column("select distinct function_code from mukonin_audit.role_function
        where role_code in ('base', 'unreg')");
    }
    else {
      require_once("session.php");
      global $session;
      $functions = explode(',',$session->user->functions);
    }
    
    global $db;
    $parent_id = $db->read_one_value("select id from mukonin_audit.menu where type = '$type' and program_id = ".config::$program_id );
    if ($parent_id == '') return;
    
    echo "<ul $attr>\n";
    menu::show_subitems($parent_id, $functions, $li, 0);
    echo "</ul>\n";
  }
  
  static function show_subitems($parent_id, $functions, $li, $level)
  {
  
    $sql = "select function_code, m.id, parent_id, name, url, description 
      from mukonin_audit.menu m join mukonin_audit.function f on f.code = m.function_code and f.program_id = m.program_id
      where parent_id = $parent_id and f.program_id = ". config::$program_id;
    $sql .= " order by position"; 
    
    global $db;
    $items = $db->read($sql);
    if (sizeof($items) > 1) echo "<span>\n";
    foreach ($items as $item) {
      $function = $item['function_code'];
      if (!in_array($function, $functions)) continue;
      if ($level == 0 && $li != '') echo "<$li>";
      $url = $item['url'];
      if ($url == '') $url = "/?c=$function";
      $name = $item['name'];
      echo "<a href='$url'>$name</a>\n";
      menu::show_subitems($item['id'], $functions, $li, $level+1);
      if ($level == 0 && $li != '') echo "</$li>\n";
    }
    if (sizeof($items) > 1) echo "</span>\n";
	}
}
?>
