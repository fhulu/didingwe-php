<?php session_start();
require_once '../common/log.php';
require_once('../common/utils.php');

function configure() {
  global $config;
  $config = load_yaml("app-config.yml", true);

  if ($config['log_dir'] && $config['log_file'])
    log::init($config['log_file'], log::DEBUG);

  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);
}

function process_action() {
  if (is_null($_REQUEST['action'])) return false;
  require_once('../common/page.php');
  return true;
}

configure();
if (process_action()) return ;
?>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<link href="/common/didi.css" media="screen" rel="stylesheet" type="text/css" />
<link href="/common/input_page.css" media="screen" rel="stylesheet" type="text/css" />

<link href="/default.style.css" media="screen" rel="stylesheet" type="text/css" />
<script src='/jquery-min.js'></script>
<script src='/jquery-ui.min.js'></script>
<script src='/common/mukoni.jquery.js'></script>
<script src="/common/mkn.js"></script>
<script src="/common/mkn.render.js"></script>
<script src="/common/page.js"></script>
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
  $path = $_REQUEST['path'];
  $content = $_REQUEST['content'];
  if (isset($path) && !isset($content)) {
    $request = $options = $_REQUEST;
  }
  else {
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
  }
  $options['request'] = $request;
?>
<script>
$(function() {
  $("body").page(<?=json_encode($options);?>);
});
</script>
