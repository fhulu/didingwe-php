<?php
require_once 'log.php';
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

function get_active_config_name() {
  return $_SESSION['auth']? 'auth': 'public';
}


function configure() {
  global $config;
  $config = load_yaml("../vocab/app-config.yml", true);
  $config = merge_options(load_yaml($config['didi_root'] . "/vocab/app-config.yml", true), $config);
  replace_fields($config, $config);
  do_preprocessing($config);
  $site_config = load_yaml($config['site_config'], false);
  $config = merge_options($config, $site_config);
  replace_fields($config, $config);
  do_preprocessing($config);

  $log = replace_fields($config['log'], $config);
  if ($log)
    log::init($log['path'], $log['level']);

  $spa = &$config['spa'];
  $active = get_active_config_name();
  $spa = merge_options($spa, $spa[$active]);

  replace_fields($spa, $_REQUEST);
  replace_fields($spa, $spa);
  replace_fields($spa, $config);

  $_SESSION['config'] = $config;
  return $site_config != null;
}

