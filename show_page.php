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
<?php
function pre_load_custom($page)
{
  if (file_exists("pre-$page.php")) {
    require_once "pre-$page.php";
  } 
  if (file_exists("$page.css")) { 
    echo "<link type='text/css' rel='stylesheet' href='$page.css'></link>";
  } 
  if (file_exists("$page.js")) { 
    echo "<script type='text/javascript' src='$page.js'></script>";
  }
}
?>
<script type='text/javascript' src="common/page.js"></script>
<script>
$(function() {
  $("#<?=$page; ?>").page();
});
</script>
<?php 
echo "<div id='$page'></div>";
pre_load_custom($page);
if (isset($_REQUEST['content']))   pre_load_custom($_REQUEST['content']);