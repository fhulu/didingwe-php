<?php
  require_once '../common/log.php';
  log::init('index', log::DEBUG);
  require_once('../common/utils.php');
  require_once('config.php');
  $action = GET('action');
  if (!is_null($action)) {
    require_once('../common/page.php');
    return;
  }

  require_once '../common/session.php';
  global  $session;
  $tag = is_null($session)?time(): $session->id;
?>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link href="/jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	
<link href="/default.style.css?<?=$tag?>" media="screen" rel="stylesheet" type="text/css" />	

<script type='text/javascript' src='/jquery/min.js'></script>
<script type='text/javascript' src='/jquery/ui-min.js'></script>
<script type="text/javascript" src='/common/mukoni.jquery.js?<?=$tag?>'></script> 
<script type='text/javascript' src="/common/page.js?<?=$tag?>"></script>
<script>
  var request_method = '<?=config::$request_method;?>';
</script>
<?php
  require_once ('../common/log.php');
  
  function pre_load_custom($page)
  {
    log::debug("loading page pre_$page");
    if (file_exists("pre_$page.php")) {
      require_once "pre_$page.php";
    } 
    if (file_exists("$page.css")) { 
      echo "<link type='text/css' rel='stylesheet' href='$page.css'></link>";
    } 
    if (file_exists("$page.js")) { 
      echo "<script type='text/javascript' src='$page.js'></script>";
    }
  }

  global $session;
  log::debug(json_encode($_GET));
  list($page) = explode('/', GET('path'));
  $content = GET('content');
  if (!is_null($content) && !in_array($content, array('logout','login')))
    $_SESSION['content'] = $content;

  if ($content == '') $content = 'home';
  if ($page == '') $page = 'index';
  if (!is_null($page))  pre_load_custom($page);
  if ($content != $page && !is_null($content)) 
    pre_load_custom($content);
  $params = array_merge($_GET, array('path'=>$page, 'content'=>$content));
?>
<script>
$(function() {
  $("body").page(<?=json_encode($params);?>);
});
</script>
