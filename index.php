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
<?php
echo_scripts($config['css'], "<link href='\$script' media='screen' rel='stylesheet' type='text/css' />\n");
echo_scripts($config['scripts'], "<script src='\$script'></script>\n");
?>
<script>
  var request_method = '<?=$config['request_method'];?>';
</script>
<?php
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
