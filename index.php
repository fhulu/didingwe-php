<?php session_start();
require_once '../common/log.php';
require_once('../common/utils.php');

function configure() {
  global $config;
  $config = load_yaml("../common/app-config.yml", true);
  $config = merge_options($config, load_yaml("app-config.yml", false));

  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);
  replace_fields($config,$config,true);

  if ($config['log_dir'] && $config['log_file'])
    log::init($config['log_file'], log::DEBUG);

  $brand_path = $config['brand_path'];
  if (!file_exists($brand_path)) return;
  $brand_link = ".".$config['brand_name'];

  if (file_exists($brand_link)) return;
  symlink($brand_path,$brand_link);
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

log::debug_json("BROWSER REQUEST", $_REQUEST);
$active = $_SESSION['sid'] == 0? 'landing': 'authenticated';
$active_config = $config[$active];
$session = &$_SESSION[$type] = merge_options($active_config, $session, $_REQUEST);
$request = $session;
replace_fields($request, $request, true);
unset($request['path']);
unset($request['page']);
$options = ["path"=>$active_config['page'], 'request'=>$request];
?>
<script>
var request_method = '<?=$config['request_method'];?>';
$(function() {
  $("body").page(<?=json_encode($options);?>);
});
</script>
