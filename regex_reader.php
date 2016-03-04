<?php

require_once("field_format.php");

class regex_reader_exception extends Exception {};

class regex_reader 
{ 
  var $line;
  var $matches;
  var $multi_line;
  var $delimiter;
  function __construct($regex, $delimiter='')
  {
    $this->delimiter = $delimiter;
    if ($regex[0] == 'm') {
      $this->multi_line = true;
      $this->regex = substr($regex, 1);
    }
    else $this->regex = $regex;
  }

  function parse(&$line)
  {
    if (!$this->multi_line) {
      $this->matches = array();
      if (!preg_match($this->regex, $line, $this->matches)) return null;
      if ($this->delimiter=='') 
        array_shift($this->matches);
      else
        $this->matches = preg_split($this->delimiter, $this->matches[0]);
      return $this->matches;
    }
    
    $line = str_replace("\n", '',$line);
    $this->line .= str_replace("\r", '',$line);
    $this->matches = array();
    if (!preg_match($this->regex, $this->line, $this->matches)) return null;
    $this->line = '';
    if ($this->delimiter=='') 
      array_shift($this->matches);
    else
      $this->matches = preg_split($this->delimiter, $this->matches[0]);
    return $this->matches;
  }     
}
?>
