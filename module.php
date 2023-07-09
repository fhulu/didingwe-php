<?php

class module
{
  var $page;
  var $manager;
  var $config;
  var $request;
  function __construct($context=null, $config=null)
  {
    global $page;
    $this->page = $this->manager = $context? $context: $page;
    $this->config = $config;
    $this->request = $this->page->request;
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
