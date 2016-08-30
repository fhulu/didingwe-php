<?php session_start();
require_once '../common/log.php';
require_once('../common/utils.php');

function configure() {
  global $config;
  $config = load_yaml("../common/app-config.yml", true);
  $config = merge_options($config, load_yaml("app-config.yml", false));

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
$type = $_SESSION['sid'] == 0? 'landing': 'session';
$page = $config[$type]['page'];
$request = $_REQUEST;
$session = &$_SESSION[$type];
foreach($config[$type] as $setting=>$value) {
  if ($setting =='page') continue;
  if (isset($request[$setting]))
    $session[$setting] = $request[$setting];
  else if (isset($session[$setting]))
    $request[$setting] = $session[$setting];
  else
    $request[$setting] = $session[$setting] = $value;
}
$options = array("path"=>$page);
$options['request'] = $request;
?>
<script>
var request_method = '<?=$config['request_method'];?>';
$(function() {
  $("body").page(<?=json_encode($options);?>);
});
</script>
