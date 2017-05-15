<?php session_start();
require_once '../common/log.php';
require_once('../common/utils.php');


function configure_brand(&$config) {
  $brand_path = $config['brand_path'];
  if (!$brand_path || !file_exists($brand_path)) return false;;
  $brand_config = load_yaml("$brand_path/app-config.yml", false);
  if ($brand_config)
    $config = merge_options($config, $brand_config);
  replace_fields($config, $config, true);

  $brand_link = ".".$config['brand_name'];
  $config['brand_link']  = "/$brand_link";
  replace_fields($config,$config,true);

  if (!file_exists($brand_link))
    symlink($brand_path,$brand_link);
  return true;
}

function get_active_config_name()
{
  return $_SESSION['auth']? 'authenticated': 'landing';
}
function configure() {
  global $config;
  $config = load_yaml("../common/app-config.yml", true);
  $config = merge_options($config, load_yaml("app-config.yml", false));

  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);
  configure_brand($config);

  if ($config['log_dir'] && $config['log_file'])
    log::init($config['log_file'], log::DEBUG);

  $active = get_active_config_name();
  $active_config = &$config[$active];
  replace_fields($active_config, $_REQUEST, true);
  replace_fields($active_config, $active_config, true);
  $config = merge_options($config, $active_config);
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
