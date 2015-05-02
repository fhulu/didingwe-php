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
    $file = "pre_$page.php";
    $common_file = "../common/$file";
    if (file_exists($file)) 
      require_once $file;
    else if (file_exists($common_file))
      require_once $common_file;
    
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
  $request = array_merge($_REQUEST, array('content'=>$content));
  $options = array("path"=>$page, 'request'=>$request)
?>
<script>
$(function() {
  $("body").page(<?=json_encode($options);?>);
});
</script>
