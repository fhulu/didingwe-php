<?php 
require_once('../common/user.php'); 
user::verify($function);
if (is_null($sortField)) $sortField = 'null';
if (is_null($sortOrder)) $sortOrder = 'asc';
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
    pageSize: 100,
    url:"<?=$url;?>"
  }).data('table');
});
</script>
<div class="ajax_result"></div>
<table id="table"></table> 
