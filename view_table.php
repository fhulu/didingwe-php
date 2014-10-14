<?php 
require_once('../common/user.php'); 
if (is_null($function))
  log::warn ("Possible SECURITY flaw: Unverified access");
else
  user::verify($function);
if (is_null($sortField)) $sortField = 'null';
if (is_null($sortOrder)) $sortOrder = 'asc';
if (is_null($pageSize)) $pageSize = 100;
?>
<link type="text/css" rel="stylesheet" href="table.css"></link>
<link type="text/css" rel="stylesheet" href="bootstrap.min.css"></link>
<script src="common/table.js"></script>
<script src="bootstrap.min.js"></script>
<script src="bootbox.min.js"></script>
<script>
$(function(){
  var table = $('#table').table({
    sortField: "<?=$sortField;?>",
    sortOrder: "<?=$sortOrder;?>",
    pageSize: "<?=$pageSize;?>",
    url:"<?=$url;?>"
  }).data('table');
});
</script>
<div class="ajax_result"></div>
<table id="table"></table> 
