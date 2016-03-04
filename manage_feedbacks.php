<?php 
require_once ('../common/user.php');
//require_once('../common/feedback.php');
//user::verify('manage_submit_film');
?>
<link type="text/css" rel="stylesheet" href="table.css"></link>
<script src='common/wizard.js'></script>
<script src='common/table.js'></script>
<script> 
 var global = this;
$(function() {
 
 $('#features').table(
    { pageSize: 50, sortField: 'create_time', sortOrder: 'desc',url: '/?a=feedback/manage_feedback'}
  )
  .on('refresh', function() {
    $('#features tr').on('action', function(event, action, data) {
      global.row = $(this);
      global.data = data;
    })

  });
});
</script>
<style>
table td input[type='button']
{
  width: 55px;
  height: 20px;
  font-size: 11px;
  display: block;
}

table td div[action]
{
  display: inline-block;
  width: 16px;
  height: 16px;
  padding-right: 5px;
  background-repeat:no-repeat;
  background-position:center; 
  cursor: pointer;
  vertical-align: top;
}

table td:last-child
{
  width: 60px;
}
</style>
<table id="features"></table>

