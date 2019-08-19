<?php

class memcacher extends Memcached {

  function __construct($page) {
    parent::__construct();
    $config = $page->read_config("memcache_config");
    log::debug_json("memcache config", $config);
    $this->addServer($config["server"], $config["port"], $config["weight"]);
  }

  function read($key, $var) {
    log::debug_json("memcache config", $config);
    return [$var => parent::get($key) ];
  }
 
  function __destruct() {}
}
