<?php
  require_once('../common/session.php');
  require_once('rate_game.php');
?>
<script src="../common/table.js"></script> 
<style>
#ratings td div
{
  display: inline-block;
  width: 16px;
  height: 16px;
  padding-right: 5px;
  background-repeat:no-repeat;
  background-position:center; 
  cursor: pointer;
  vertical-align: top;
  background-image:url('edit16.png');
}
</style>
<script>
$(function() {
  $('#ratings').table({
    sortField: 'date_rated', 
    sortOrder: 'desc',
    url: '/?a=rating/history',
    onRefresh: function() 
    {
      $('#ratings .rerate').click(function() {
        var row = $(this).parent().parent();
        rate_a_game(row.attr('id'));
      });
    }
  });
});
</script>
<table id="ratings" class="game"></table>
