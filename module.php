<?php

class module
{
  var $page;
  var $collection;
  var $db;
  function __construct($page)
  {
    $this->page = $page;
    $this->collection = $page->get_module('collection');
    $this->db = $this->page->db;
  }
}
