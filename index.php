<?php
header('Access-Control-Allow-Origin: *');
session_start();
require_once('log.php');
require_once('utils.php');


function do_preprocessing(&$config) {
  $preprocess = $config['preprocess'];
  if (!$preprocess || !file_exists($preprocess) || in_array($preprocess, get_included_files()) ) {
    replace_fields($config,$config);
    return;
  }
  require_once($preprocess);
  $func = basename($preprocess, ".php");
  $preprocess_config = $func($config);
  $config = merge_options($config, $preprocess_config);
  replace_fields($config,$config);
}

function get_active_config_name()
{
  return $_SESSION['auth']? 'auth': 'public';
}


function configure() {
  global $config;
  $config = merge_options(['didi_root'=>'../didi'], load_yaml("../vocab/app-config.yml", false));
  $config = merge_options(['didi_path'=>$config['didi_root'] . "/vocab"], $config);
  $config = merge_options(load_yaml($config['didi_path'] . "/app-config.yml", false), $config);
  do_preprocessing($config);
  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);

  $log = replace_fields($config['log'], $config);
  if ($log)
    log::init($log['path'], $log['level']);

  $spa = &$config['spa'];
  $active = get_active_config_name();
  $active_config = $spa = merge_options($spa, $spa[$active]);

  replace_fields($active_config, $_REQUEST);
  replace_fields($active_config, $active_config);
  $config = merge_options($config, $active_config);
  replace_fields($config, $config);
  do_preprocessing($config);
  $_SESSION['config'] = $config;
  return $site_config != null;
}

function process_action() {
  if (is_null($_REQUEST['action'])) return false;
  require_once('page.php');
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
log::debug_json("BROWSER REQUEST", $_REQUEST);
if (process_redirection() || load_default_page() || process_action()) return ;

function build_tag_template_attrs($attrs, $exclusions) {
  $template = "";
  foreach ($attrs as $name=>$value) {
    if (in_array($name, $exclusions)) continue;
    $template .= " $name=\"$value\"";
  }
  return $template;
}
function echo_head_tags($config) {
  $tags = $config['head_tags'];
  if (!$tags) return;
  foreach($tags as $tag=>$attrs) {
    $template = "<$tag" . build_tag_template_attrs($attrs, ['list', 'text']);
    $list = $attrs['list'];
    if (!isset($list)) $list = $tag;
    $list = $config[$list];
    if (!isset($list)) continue;

    foreach ($list as $value) {
      $line = $template;
      if (!is_array($value)) {
        $values = ["value"=>$value];
      }
      else if (sizeof($value) == 1) {
        [$name, $props] = assoc_element($value);
        $values = is_array($props)? $props: ['value'=>$props];
        $values['name'] = $name;
      }
      else {
        $values = $value;        
      }
      $line .= build_tag_template_attrs($values, ['name', 'value']);
      $text = $attrs['text'];
      if (isset($text)) 
        $line .= ">$text</$tag>";
      else
        $line .= "/>";
  
      $line = replace_vars($line, $values);
      $line = preg_replace('/ \w+\s*="\$[^"]+"/', "", $line);
      echo $line. "\n";
    }
  }
  return $lines;
}

?>
<?php

$config = array_merge(['session_timeout'=>300], $config);
$spa = $config['spa'];
$active = get_active_config_name();
$active_config = merge_options($spa, $spa[$active]);

$request = $_REQUEST;
if (isset($request['path'])) 
  $content = $request['path'];
else
  $content = $active_config['content'];
$request['content'] = $content;
unset($request['path']);
$page =  $request['page'];
if (!isset($page)) $page = $active_config['page'];
$options = ["path"=>$page, 'request'=>$request];
?>
<!DOCTYPE html>
<html>
<head>
<?php echo_head_tags($config); ?>
<script>
var request_method = '<?=$config['request_method'];?>';
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
</head>
<body>
<div class="didi processing modal font-large center-text" style="display: none;z-index: 1000">
  <div class="didi light-grey center-text rounded-large shadow pad pad-small col s12 m6 l4 no-float centered">
    <i class="didi fa fa-spin fa-spinner font-large"></i>
    <p class="didi message"></p>
  </div>
</div>
</body>
</html>
