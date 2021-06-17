<?php 
  require_once '../common/log.php';
  log::init('index', log::DEBUG);
  require_once '../common/session.php';
  require_once('../common/utils.php');
  if (!is_null($action)) {
    require_once('../common/page.php');
    return;
  }

  global  $session;
  $tag = is_null($session)?time(): $session->id;
?>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link href="/jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />
<link href="/common/jquery.datetimepicker.css" media="screen" rel="stylesheet" type="text/css" />

<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src="bootstrap/js/bootstrap.min.js"></script>

<link href="/common/input_page.css?<?=$tag?>" media="screen" rel="stylesheet" type="text/css" />

<link href="/default.style.css?<?=$tag?>" media="screen" rel="stylesheet" type="text/css" />
<script type='text/javascript' src='/jquery/min.js'></script>
<script type='text/javascript' src='/jquery/ui-min.js'></script>
<script type='text/javascript' src='/common/jquery.datetimepicker.js'></script>
<script type="text/javascript" src='/common/mukoni.jquery.js?<?=$tag?>'></script>
<script type='text/javascript' src="/common/mkn.js?<?=$tag?>"></script>
<script type='text/javascript' src="/common/mkn.render.js?<?=$tag?>"></script>
<script type='text/javascript' src="/common/page.js?<?=$tag?>"></script>
<script>
  var request_method = '<?=$config['request_method'];?>';
</script>
<?php

  function pre_load_custom($page)
  {
    if ($page[0] == '/') $page = substr($page,1);
    log::debug("loading page pre_$page");
    $file = "pre_$page.php";
    $common_file = "../common/$file";
    if (file_exists($file))
      require_once $file;
    else if (file_exists($common_file))
      require_once $common_file;
  }

  global $session;
  log::debug_json("BROWSER REQUEST", $_REQUEST);
  $config = array_merge(['session_timeout'=>300], $config);
  $content = $_REQUEST['content'];
  $prefix = $_SESSION['uid'] == 0? 'landing': 'session';
  $page = $config[$prefix.'_page'];
  if ($content == '')  $content = $config[$prefix.'_content'];
  $_SESSION['content'] = $content;
  if (!is_null($page))  pre_load_custom($page);
  if ($content != $page && !is_null($content))
    pre_load_custom($content);
  $request = $_REQUEST;
  if (!is_null($content)) $request['content'] = $content;
  unset($request['path']);
  $options = array("path"=>$page);
  if (!empty($request)) $options['request']=$request;
?>
<script>
$(function() {
  $("body").page(<?=json_encode($options);?>);
  var timer;
  var start_timer = ()=> {
    if (timer) clearTimeout(timer);
    timer = setTimeout(()=> {
      window.location.href = '/<?=$content?>';
    }, <?=($config['session_timeout']+60)*1000?>);
  };
  start_timer();
  $(document).bind("mousemove keypress click", start_timer);
});
</script>
