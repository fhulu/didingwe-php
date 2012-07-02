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

    $_SESSION[$div] = "/?a=$page";
  }
  else
    $_SESSION[$div] = $file_name;
  //echo "SESSION[div] = ". $_SESSION[$div] . "<br>\n";
}
function load_div($div)
{
  $page = $_SESSION[$div];
  echo "<div id='$div'>";
  if (strpos($page, '?') === false && file_exists($page)) {
    try {
      require_once($page);
    }
    catch (user_exception $exception) {
      require_once('breach.php');
    }/*
    catch (Exception $exception)
    {
      require_once('error.php');
    }*/
  }
  echo "</div>\n";
}


function set_div($div)
{
  $page = $_SESSION[$div];
  if (strpos($page, '?') !== false) 
    echo "ajax_inner('$div', '$page', true);\n";
}
if (isset($_GET['a'])) {
  require_once('action.php');
  return;
}
init_div('content', 'home');
init_div('banner');
init_div('menu');
init_div('left-nav');
init_div('right-nav');
init_div('footer');

?>
<!DOCTYPE html">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link href="default.style.css" media="screen" rel="stylesheet" type="text/css" />	
    <link href="jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	

    <script type='text/javascript' src='jquery/min.js'></script>
    <script type='text/javascript' src='jquery/ui-min.js'></script>
    <script type='text/javascript' src='../common/dom.js'></script>
    <script type="text/javascript" src="../common/ajax.js"></script> 
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