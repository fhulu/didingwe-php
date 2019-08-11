<?php

class module
{
  var $page;
  var $collection;
  var $db;
  function __construct($context=null)
  {
    global $page;
    $this->page = $context? $context: $page;
    if (get_class($this) != 'collection')
      $this->collection = $page->get_module('collection');
    $this->db = $this->page->db;
  }

  function set()
  {
    $args = func_get_args();
    foreach($args as $name) {
      if (is_array($name))
        list($name,$value) = assoc_element($name);
      else
        $value = $this->page->answer[$name];
      $this->$name = $this->page->translate_context($value);
    }
  }

}
