<?php
log::debug("loading $page");
?>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<link type="text/css" rel="stylesheet" href="<?=$_GET['_code']; ?>.css"></link>
<script type='text/javascript' src="common/form.js"></script>
<script type='text/javascript' src="common/page_wizard.js"></script>
<script>
$(function() {
  $("#<?=$_GET['_code']; ?>").form();
});
</script>
<script type='text/javascript' src="<?=$_GET['_code']; ?>.js"></script>
<div id="<?=$_GET['_code']; ?>"></div>