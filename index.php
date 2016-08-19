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

log::debug_json("BROWSER REQUEST", $_REQUEST);
$type = $_SESSION['uid'] == 0? 'landing': 'session';
$page = $config[$type]['page'];
$request = $_REQUEST;
foreach($config[$type] as $sub_page) {
  if ($page =='page') continue;
  if (isset($request[$sub_page]))
    $_SESSION[$sub_page] = $request[$sub_page];
  else if (isset($_SESSION[$sub_page]))
    $request[$sub_page] = $_SESSION[$sub_page];
}
$options = array("path"=>$page);
$options['request'] = $request;
?>
<script>
$(function() {
  var request_method = '<?=$config['request_method'];?>';
  $("body").page(<?=json_encode($options);?>);
});
</script>
