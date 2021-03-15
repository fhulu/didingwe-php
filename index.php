<?php
header('Access-Control-Allow-Origin: *');
session_start();
require_once 'didi/log.php';
require_once('didi/utils.php');
require_once('didi/config.php');


function process_action() {
  if (is_null($_REQUEST['action'])) return false;
  require_once('didi/page.php');
  return true;
}

function load_default_page() {
  global $config;
  $root_path = $config['root_path'];
  $index_file = "$root_path/index.php";
  if (!file_exists($index_file)) return false;
  chdir($root_path);
  require_once($index_file);
  return true;
}

function process_redirection() {
  global $config;
  $redirect_url = $config['redirect_url'];
  if (!$redirect_url) return false;
  header("Location: $redirect_url");
  return true;
}

configure();
if (process_redirection() || load_default_page() || process_action()) return ;
?>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name=viewport content="width=device-width, initial-scale=1" />
<?php
echo_scripts($config['css'], "<link href='\$script' media='screen' rel='stylesheet' type='text/css' />\n");
echo_scripts($config['scripts'], "<script src='\$script'></script>\n");

log::debug_json("BROWSER REQUEST", $_REQUEST);
$active = get_active_config_name();
$active_config = &$config[$active];
$request = merge_options($active_config, $_REQUEST);
unset($request['path']);
$page =  $request['page'];
if (!isset($page)) $page = $active_config['page'];
$options = ["path"=>$page, 'request'=>$request];
?>
<script>
var request_method = '<?=$config['request_method'];?>';
$(function() {
  $("body").page(<?=json_encode($options);?>);
});
</script>
<body>
<div class="didi processing modal font-large center-text" style="display: none;z-index: 1000">
  <div class="didi light-grey center-text rounded-large shadow pad pad-small col s12 m6 l4 no-float centered">
    <i class="didi fa fa-spin fa-spinner font-large"></i>
    <p class="didi message"></p>
  </div>
</div>
</body>
