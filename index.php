<?php
session_start(); 
require_once('log.php');
log::init('index', log::DEBUG);


function get_page_file($page)
{
  list($name, $params) = explode('?', $page);
  static $extensions = array('','.php','.htm','.html');
  foreach ($extensions as $ext) {
    $file_name = $name . $ext;
    if (file_exists($file_name))
      return $file_name;
  }
  return null;
}

function init_div($div, $default=null)
{
  $section = $div[0];
  $page = $_GET[ $section ]; 
  if ($page == '') {
    $page = $_SESSION[$div];
    if ($page != '') 
      return;
    $page = is_null($default)? $div: $default;
  }
  // determine file name from page
  $file_name = get_page_file($page);
  if (is_null($file_name)) {
   
    $params = '';
    array_walk($_GET, function($value, $key) use (&$params, $section) {
      if ($key != $section)
        $params .= "&$key=".urlencode($value);
    });
    
    $page .= $params;

    $_SESSION[$div] = "a.php?a=$page";
  }
  else
    $_SESSION[$div] = $file_name;
  //echo "SESSION[div] = ". $_SESSION[$div] . "<br>\n";
}

function load_div($div)
{
  $page = $_SESSION[$div];
  echo "<div id='$div'>";
  if (strpos($page, '?') === false && file_exists($page))
    require_once($page);
  echo "</div>\n";
}


function set_div($div)
{
  $page = $_SESSION[$div];
  if (strpos($page, '?') !== false) 
    echo "ajax_inner('$div', '$page', true);\n";
}

init_div('content', 'home');
init_div('banner');
init_div('menu');
init_div('left-nav');
init_div('right-nav');
init_div('footer');

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    
    <link href="default.style.css" media="screen" rel="stylesheet" type="text/css" />	
    <link href="jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	

    <script type='text/javascript' src='jquery/min.js'></script>
    <script type='text/javascript' src='jquery/ui-min.js'></script>
    <script type='text/javascript' src='../common/dom.js'></script>
    <script type="text/javascript" src="../common/ajax.js"></script> 

    <script type="text/javascript">
    
	
      window.onload = function() {
      <?php 
        set_div('banner');
        set_div('menu');
        set_div('left-nav');
        set_div('content');
        set_div('right-nav');
        set_div('footer');
      ?>
      };
    </script> 
  </head>
  
  <body>
    <div id='frame'>
      <div id='header'>
        <?php load_div('banner'); ?>
        <?php load_div('menu'); ?>
      </div>
      <div id='body'>
        <?php load_div('left-nav'); ?>
        <?php load_div('content'); ?>
        <?php load_div('right-nav'); ?>
      </div>
      <?php load_div('footer'); ?>
   </div>
  </body>
</html>