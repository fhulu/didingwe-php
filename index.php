<?php
session_start(); 
require_once('log.php');
log::init('index', log::DEBUG);
$_SESSION['referrer'] = $_SERVER[REQUEST_URI];


function load_div($div, $page)
{
  $paths = explode('/', $page);
  $args = explode('?', $paths[sizeof($paths)-1]);
  $file_name = $args[0];
  $js = "$file_name.js";
  log::debug("JSFILE: $js");
  if (file_exists($js))
    echo "<script type='text/javascript' src='$js'></script>\n"; 
  if (file_exists("$file_name") || file_exists("$file_name.php") || file_exists("$file_name.html")) 
    $_SESSION[$div] = "do.php/$page";
}

function init_div($div, $default=null)
{
  $var = $div[0];
  $page = $_GET[$var]; 
  if ($page == '') {
    if (!isset($_SESSION[$div])) 
      $page = is_null($default)? $div: $default;
  }
  load_div($div, $page);
}

function set_div($div)
{
  $page = $_SESSION[$div];
  if ($page != '') 
    echo "ajax_inner('$div','$page',true);\n";  
}

init_div('content', 'home');
init_div('banner');
init_div('menu');
init_div('left-nav');
init_div('right-nav');
init_div('footer');

?>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<link href="default.style.css" media="screen" rel="stylesheet" type="text/css" />	
    <script type="text/javascript" src="../common/dom.js"></script> 
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
        <div id='banner'></div>
        <div id='menu'></div>
      </div>
      <div id='body'>
        <div id='left-nav'></div>
        <div id='right-nav'></div>
        <div id='content'></div>
      </div>
      <div id='footer'></div>
    </div>
  </body>
</html>