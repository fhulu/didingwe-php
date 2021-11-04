<?php
header('Access-Control-Allow-Origin: *');
session_start();
require_once('config.php');

configure();
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

function build_tag_template_attrs($attrs, $exclusions) {
  $template = "";
  foreach ($attrs as $name=>$value) {
    if (in_array($name, $exclusions)) continue;
    $template .= " $name=\"$value\"";
  }
  return $template;
}

function build_tag_lines($tag_type) {
  global $config;
  $tags = $config[$tag_type];
  if (!$tags) $tags = [];
  $lines = [];
  foreach($tags as $tag=>$attrs) {
    $template = "<$tag" . build_tag_template_attrs($attrs, ['alias', 'text']);
    $list = merge_options($config[$tag], $config[ $attrs['alias'] ]);
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
      $lines[] = $line . "\n";
    }
  }
  $config[$tag_type] = $lines;
}

log::debug_json("BROWSER REQUEST", $_REQUEST);
if (process_redirection() || load_default_page() || process_action()) return ;

$spa = $config['spa'];
$active = get_active_config_name();
$active_config = merge_options($spa, $spa[$active]);

$request = $_REQUEST;
if (isset($request['path'])) 
  $request['content'] = $request['path'];
else
  $request['content'] = $active_config['content'];
unset($request['path']);

$page =  $request['page'];
if (!isset($page)) $page = $active_config['page'];
$options = ["path"=>$page, 'request'=>$request];

build_tag_lines('head_tag');
build_tag_lines('body_tag');
$config['options'] = json_encode($options);
$spa_template = file_get_contents($active_config['template']);
$spa_template = replace_vars($spa_template, $config);

echo $spa_template;
