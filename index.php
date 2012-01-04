<?php
session_start(); 
require_once('log.php');
log::init('index', log::DEBUG);
$_SESSION['referrer'] = $_SERVER[REQUEST_URI];


function load_div($div, $page)
{
  list($file_name, $params) = explode('&', $page);
  if (file_exists("$file_name") || file_exists("$file_name.php") || file_exists("$file_name.html")) 
    $_SESSION[$div] = "a.php?a=$page";
}

function init_div($div, $default=null)
{
  $var = $div[0];
  $page = $_GET[$var]; 
  if ($page == '') {
    if (!isset($_SESSION[$div])) 
      $page = is_null($default)? $div: $default;
  }
  
  $params = '';
  array_walk($_GET, function($value, $key) use (&$params, $var) {
    if ($key != $var)
      $params .= "&$key=".urlencode($value);
  });
  
  $page .= $params;
  load_div($div, $page);
}

function set_div($div)
{
  $page = $_SESSION[$div];
  if ($page != '')
    echo "ajax_inner('$div', '$page', true);\n";
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
	<link href="slideshowstyle.css" media="screen" rel="stylesheet" type="text/css" />
  
	<script language="JavaScript" src="pop-up.js"></script>
	<link rel=StyleSheet href="pop-upstyle.css" type="text/css" media="screen">
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