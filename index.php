<?php
  function at($array, $index) 
  {
    return isset($array[$index])? $array[$index]: null;
  }
  
  function GET($item) { return at($_GET, $item); }
  function POST($item) { return at($_POST, $item); }
  function REQUEST($item) { return at($_REQUEST, $item); }
  function SESSION($item) { return at($_SESSION, $item); }
  
  require_once ('session.php');
  if (isset($_GET['a'])) {
    require_once('../common/action.php');
    return;
  }
?>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link href="default.style.css" media="screen" rel="stylesheet" type="text/css" />	
<link href="jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	

<script type='text/javascript' src='jquery/min.js'></script>
<script type='text/javascript' src='jquery/ui-min.js'></script>
<script type="text/javascript" src='common/mukoni.jquery.js'></script> 
<script type='text/javascript' src="common/page.js"></script>
<!--<script type="text/javascript" src='common/mukoni.jquery-ui.js'></script>-->

<?php
  require_once ('log.php');
  
  $page = at($_GET,'page');

  function pre_load_custom($page)
  {
    log::debug("loading page $page");
    if (file_exists("pre-$page.php")) {
      require_once "pre-$page.php";
    } 
    if (file_exists("$page.css")) { 
      echo "<link type='text/css' rel='stylesheet' href='$page.css'></link>";
    } 
    if (file_exists("$page.js")) { 
      echo "<script type='text/javascript' src='$page.js'></script>";
    }
  }

  global $session;
  log::init('index', log::DEBUG);
  log::debug(json_encode($session));
  $public_pages = array('login_page','home','map','register','error_page');
  $content = GET('content');
  if (!$session && !in_array($content,$public_pages) && !in_array($page, $public_pages) ) {
    $page = 'landing';
  }
  else if (!is_null($content)) {
    $_SESSION['content'] = $content;
  }
  
  else if (isset($_SESSION['content'])) {
    $content = $_GET['content'] = $_SESSION['content'];
  }
  else 
    $page = 'landing';
  
  pre_load_custom($page);
  if (isset($content)) 
    pre_load_custom($content);
  
  $params = array_merge($_GET, array('page'=>$page));
?>
<script>
$(function() {
  $("body").page({data: <?=json_encode($params);?>});
});
</script>