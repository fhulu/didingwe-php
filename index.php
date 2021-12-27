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
    $tag_name = $tag;
    if (in_array('tag', array_keys($attrs))) $tag_name = $attrs['tag'];
    $template = "<$tag_name" . build_tag_template_attrs($attrs, ['alias', 'text', 'tag']);
    $list = merge_options($config[$tag], $config[ $attrs['alias'] ]);
    if (!isset($list)) continue;
    if (is_assoc($list) || !is_array($list)) $list = [$list];
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
        $line .= ">$text</$tag_name>";
      else
        $line .= "/>";
  
      $line = replace_vars($line, $values);
      $line = preg_replace('/ \w+\s*="\$[^"]+"/', "", $line);
      $lines[] = $line . "\n";
    }
  }
  $config[$tag_type] = $lines;
}

function search_file_paths($name, $paths, $prefix="") {
  $paths = array_reverse($paths);
  foreach ($paths as $path) {
    $path .= "/$prefix$name";
    if (file_exists($path)) return $path;
  }
  return $name;
}

log::debug_json("BROWSER REQUEST", $_REQUEST);
if (process_redirection() || load_default_page() || process_action()) return ;

$spa = $config['spa'];
$request = $_REQUEST;
if (isset($request['path'])) 
  $request['content'] = $request['path'];
else
  $request['content'] = $spa['content'];
unset($request['path']);

$page =  $request['page'];
if (!isset($page)) $page = $spa['page'];
$options = ["path"=>$page, 'request'=>$request];

build_tag_lines('head_tag');
build_tag_lines('body_tag');
$config['options'] = json_encode($options);
$template_path = search_file_paths($spa['template'], $config['search_paths'], 'web/');
$spa_template = file_get_contents($template_path);
$spa_template = replace_vars($spa_template, $config);
echo $spa_template;
