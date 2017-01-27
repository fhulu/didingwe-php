<?php

class module
{
  var $page;
  var $collection;
  var $db;
  function __construct($page)
  {
    $this->page = $page;
    if (get_class($this) != 'collection')
      $this->collection = $page->get_module('collection');
    $this->db = $this->page->db;
  }

  function set($vars)
  {
    foreach($vars as $name=>$value) {
      $this->$name = $value;
    }
  }

}
