<?php
session_start();

if (!isset($daemon_mode)) {
  global $session;
  if (isset($_SESSION['instance']))
    $session = unserialize($_SESSION['instance']);
  else
    $session = null;
}
