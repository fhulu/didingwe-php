<?php

require_once("db.php");

class menu
{
  const SUBMENU = 0x0001;
  const LINKS = 0x0002;
  var $type;
  var $level0;
  var $flags;
  
  
  function __construct($type, $level0='', $flags=null)
  {
    $this->type = $type;
    $this->level0 = $level0;
    $this->flags = is_null($flags)? self::SUBMENU | self::LINKS: $flags;
  }
  
  function show()
  {
    global $session;
    if ($_SESSION[instance] == '' || $_SESSION[last_error] != '') {
      require_once('user.php');
      $functions =  user::default_functions();
    }
    else {
      global $session;
      require_once("user.php");
      $user = $session->user;
      $functions = $session->user->functions;
    }
    log::debug("functions are ". implode(',', $functions));
    
    global $db;
    $parent_id = $db->read_one_value("select id from mukonin_audit.menu where type = '$this->type' and program_id = ".config::$program_id );
    if ($parent_id == '') return;
    
    menu::show_subitems($parent_id, $functions, 0);
  }
  
  function show_subitems($parent_id, $functions, $level)
  {
  
    $sql = "select function_code, m.id, ifnull(m.name, f.name) name, url, description, protected 
      from mukonin_audit.menu m join mukonin_audit.function f on f.code = m.function_code and f.program_id = m.program_id
      where parent_id = $parent_id and f.program_id = ". config::$program_id;
    $sql .= " order by position"; 
    
    global $db;
    $items = $db->read($sql);
    $submenu = sizeof($items) > 1 && $this->flags & self::SUBMENU;
    if ($submenu) echo "<span>\n";
    foreach ($items as $item) {
      $function = $item['function_code'];
      $protected = $item['protected'];
      if ($protected && !in_array($function, $functions)) continue;
      $url = $item['url'];
      if ($url == '') 
        $url = "/?c=$function";
      $name = $item['name'];
      $title = $item['description'];
      $toplevel = ($level == 0 && $this->level0 != '');
      if (!($this->flags & self::LINKS)) $onclick = " onclick=\"location.href='$url'\"";
      if ($toplevel) echo "<$this->level0$onclick>\n";
      if ($this->flags & self::LINKS) 
        echo "<a href='$url' title='$title'>$name</a>\n";
      else
        echo $name;
      menu::show_subitems($item['id'], $functions, $level+1);
      if ($toplevel) echo "</$this->level0>\n";
    }
    if ($submenu) echo "</span>\n";
	}
}
?>
