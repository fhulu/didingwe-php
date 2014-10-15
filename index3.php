<?php
  require_once('../common/utils.php');
  require_once('config.php');
  if (isset($_GET['a'])) {
    require_once('../common/action.php');
    return;
  }
?>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link href="/jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	
<link href="/common/jquery.datetimepicker.css" media="screen" rel="stylesheet" type="text/css" />
<link href="/main_menu.css" media="screen" rel="stylesheet" type="text/css" />	
<link href="/default.style.css" media="screen" rel="stylesheet" type="text/css" />	

<script type='text/javascript' src='/jquery/min.js'></script>
<script type='text/javascript' src='/jquery/ui-min.js'></script>
<script type="text/javascript" src='/common/mukoni.jquery.js'></script> 
<script type='text/javascript' src="/common/page3.js"></script>
<script type='text/javascript' src="/common/jquery.datetimepicker.js"></script>

<script>
  var request_method = '<?=config::$request_method;?>';
</script>
<?php
  require_once ('../common/log.php');
  
  function pre_load_custom($page)
  {
    log::debug("loading page $page");
    if (file_exists("$page.php")) {
      require_once "$page.php";
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
  log::debug(json_encode($_GET));
  list($page) = explode('/', GET('path'));
  $content = GET('content');
  if (!is_null($content) && !in_array($content, array('logout','login')))
    $_SESSION['content'] = $content;

  if ($content == '') $content = 'home';
  if (!is_null($page) && $page !== 'index') pre_load_custom($page);
  if ($content != $page && !is_null($content)) 
    pre_load_custom($content);
  $params = array_merge($_GET, array('path'=>'index', 'content'=>$content));
  var_dump($params);
?>
<script>
$(function() {
  $("body").page(<?=json_encode($params);?>);
});
</script>