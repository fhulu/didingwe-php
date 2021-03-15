<?php
require_once 'didi/log.php';
require_once('didi/utils.php');


function do_preprocessing(&$config) {
  $preprocess = $config['preprocess'];
  if (!$preprocess || !file_exists($preprocess) || in_array($preprocess, get_included_files()) ) return;

  require_once($preprocess);
  $func = basename($preprocess, ".php");
  $preprocess_config = $func($config);
  $config = merge_options($config, $preprocess_config);
#  replace_fields($config,$config,true);
}

function configure_brand(&$config) {
  $brand_path = $config['brand_path'];
  if ($brand_path && file_exists($brand_path)) {
    $brand_config = load_yaml("$brand_path/app-config.yml", false);
    if ($brand_config) 
      $config = merge_options($config, $brand_config);
    
 #   replace_fields($config, $config, true);

  }
  else {
    $brand_path = "./";
  }
  $brand_link = ".".$config['brand_name'];
  $config['brand_link']  = "/$brand_link";
 # if ($brand_path != "./")
 #   replace_fields($config,$config,true);

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
  $config = load_yaml("didi/app-config.yml", true);
  $config = merge_options($config, load_yaml("app-config.yml", false));

  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);
  do_preprocessing($config);

  configure_brand($config);

  if ($config['log_dir'] && $config['log_file'])
    log::init($config['log_file'], $config['log_level']);

  $active = get_active_config_name();
  $active_config = &$config[$active];
  replace_fields($active_config, $_REQUEST, true);
  replace_fields($active_config, $active_config, true);
  $config = merge_options($config, $active_config);
  do_preprocessing($config);
  replace_fields($config,$config,true);
  $_SESSION['config'] = $config;
  return $site_config != null;
}
