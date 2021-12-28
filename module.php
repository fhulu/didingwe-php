<?php

class module
{
  var $page;
  function __construct($context=null)
  {
    global $page;
    $this->page = $context? $context: $page;
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
