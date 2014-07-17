<?php
require_once ('log.php');
$page = $_REQUEST['page'];
log::debug("loading $page");
?>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link href="default.style.css" media="screen" rel="stylesheet" type="text/css" />	
    <link href="jquery/smoothness/ui.css" media="screen" rel="stylesheet" type="text/css" />	

    <script type='text/javascript' src='jquery/min.js'></script>
    <script type='text/javascript' src='jquery/ui-min.js'></script>
    <script type="text/javascript" src='common/mukoni.jquery.js'></script> 
    <script type="text/javascript" src='common/mukoni.jquery-ui.js'></script> 
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<?php
if (file_exists("$page.css")) { 
  echo "<link type='text/css' rel='stylesheet' href='$page.css'></link>";
} 
?>
<script type='text/javascript' src="common/page.js"></script>
<script type='text/javascript' src="common/page_wizard.js"></script>
<script>
$(function() {
  $("#<?=$page; ?>").page();
});
</script>
<?php 
if (file_exists("$page.js")) { 
  echo "<script type='text/javascript' src='$page.js'></script>";
}
echo "<div id='$page'></div>";