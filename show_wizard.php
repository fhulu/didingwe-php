<script type='text/javascript' src='common/page_wizard.js'></script>
<link type="text/css" rel="stylesheet" href="page_wizard.css"></link>
<link type="text/css" rel="stylesheet" href="<?=$_GET['_code']; ?>_wizard.css"></link>
<script>
$(function() {
  $("#<?=$_GET['_code']; ?>").loadWizard();
});
</script>
<script type='text/javascript' src="<?=$_GET['_code']; ?>_wizard.js"></script>
<div id="<?=$_GET['_code']; ?>"></div>