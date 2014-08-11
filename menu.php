<?php

require_once("db.php");

class menu
{
  const SUBMENU = 0x0001;
  const LINKS = 0x0002;
  var $name;
  var $level0;
  var $flags;
  
  
  function __construct($name, $level0='', $flags=null)
  {
    $this->name = $name;
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
      $functions = $user->partner_id? $user->functions: user::default_functions();
    }
    log::debug("functions are ". implode(',', $functions));
    
    global $db;
    $parent_id = $db->read_one_value("select id from \$audit_db.menu where name = '$this->name' and program_id = ".config::$program_id );
    if ($parent_id == '') return;
    
    menu::show_subitems($parent_id, $functions, 0);
  }
  
  function show_subitems($parent_id, $functions, $level)
  {    
    $sql = "select function_code, m.id, f.name function, m.name display, url, description, protected 
            from \$audit_db.menu m join \$audit_db.function f on f.code = m.function_code and f.program_id = m.program_id
            where parent_id = $parent_id and f.code in ('$functions') order by position"; 
    
    
    global $db;
    $items = $db->read($sql);
    foreach ($items as $item) {
      $function = $item['function_code'];
      $protected = $item['protected'];
      if ($protected && !in_array($function, $functions)) continue;
      $url = $item['url'];
      if ($url == '') 
        $url = "$function.html";
      $name = $item['display'];
      if ($name == '') $name = $item['function'];
      $title = $item['description'];
      $toplevel = ($level == 0 && $this->level0 != '');
      if (!($this->flags & self::LINKS)) $onclick = " onclick='location.href=\"$url\"'";
      if ($toplevel) echo "<$this->level0$onclick>\n";
      if ($this->flags & self::LINKS) 
        echo "<a href=\"$url\" title=\"$title\">$name</a>\n";
      else
        echo $name;
      menu::show_subitems($item['id'], $functions, $level+1);
      if ($toplevel) echo "</$this->level0>\n";
    }
    if ($submenu) echo "</span>\n";
	}

  static function query($request, $name=null)
  {
    if (is_null($name)) $name = $request['name'];

    global $session;
    if ($_SESSION[instance] == '' || $_SESSION[last_error] != '') {
      require_once('user.php');
      $functions =  user::default_functions();
    }
    else {
      global $session;
      require_once("user.php");
      $user = $session->user;
      $functions = $user->partner_id? $user->functions: user::default_functions();
    }
    
    global $db;
    $parent_id = $db->read_one_value("select id from menu where name = '$name'" );
    if ($parent_id == '') return;
    
    $functions = implode("','", $functions);
    $children = menu::query_subitems($parent_id, $functions);
    $children['template'] = 'sub_menu';
    return array('children'=>$children);
  }
  

  static function query_subitems($parent_id, $functions)
  {
      $sql = "select m.id, f.code,ifnull(url,concat(f.code,'.html')) href,
        description title, ifnull(m.name,f.name) name
            from menu m join function f on f.code = m.function_code and f.program_id = m.program_id
            where parent_id = $parent_id and f.code in ('$functions')
            order by position";
    
    global $db;
    $items = $db->read($sql, MYSQLI_ASSOC);
    if (sizeof($items) == 0) return '';
    
    $result = array();
    foreach ($items as $item) {
      $children = menu::query_subitems($item['id'], $functions);
      if ($children != '') $children['template'] = 'link';
      $code = $item['code'];
      unset($item['code'], $item['id']);
      $item['children'] = $children;
      $result[$code] = $item; 
    }
    return $result;
  }
}
?>
