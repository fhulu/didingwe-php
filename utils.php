<?php

function at($array, $index) 
{
  return isset($array[$index])? $array[$index]: null;
}

function GET($item) { return at($_GET, $item); }
function POST($item) { return at($_POST, $item); }
function REQUEST($item) { return at($_REQUEST, $item); }
function SESSION($item) { return at($_SESSION, $item); }
function last($array) { return at($array, sizeof($array)-1); }
function null_at($array, $index) { return is_null(at($array,$index)); }
function set_valid(&$dest, $source, $index) 
{
  $val = $source[$index];
  if (!is_null($val)) $dest[$index] = $val;
}