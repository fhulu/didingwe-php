<?php 
require_once ('../common/user.php');
user::verify('manage_feature_request');
?>
<title>Manage Feature Requests</title>
<link type="text/css" rel="stylesheet" href="table.css"></link>
<script src='common/wizard.js'></script>
<script src='common/table.js'></script>
<script> 
  var decision;
  var row;
  var dialogs;
  var table;
  var data = {};
  
  var global = this;
 
$(function() {
  var dialogs = $('#dialogs').wizard().data('wizard');
  $('#reason_ok').enableOnSet('#reason').click(function(event) {
    var reason = $('#reason');
    data.reason = reason.val();
    data.code = decision;
    $.send('/?a=feature/reject', {data: data, method:'get' }, function() {
      table_control.refresh();
    });
  });
    $('#comment [wizard=close]').enableOnSet('#message').click(function() {
    var comment = $('#message').val();
    data.comments = comment;
    $.send('/?a=feature/add_comment',{data: data}, function() {
      table_control.refresh();
    });
  });

  var table = $('#features');
  table_control = table.table({
    sortField: 'create_time', 
    sortOrder: 'desc',
    pageSize: 20,
    url: '/?a=feature/manage_features'
  }).data('table');

  table.on('refresh', function() {
    $('#features tr').on('action', function(event, action, data) {
      global.row = $(this);
      global.data = data;
    })

    .on('Approve',function(event, data) {
        $.send('/?a=feature/approve', {data: data,method:'get'}, function() {
          table_control.refresh();
        });  
     })
   
    .on('Decline', function(event) {
      decision = event.type.toLowerCase(4);
      dialogs.start();        
    })
    
    .on('Comment', function() {
      dialogs.start(1);        
    });
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
table td div[action='Decline']
{
  background-image:url('remove16.png');
}
table td div[action='Approve']
{
  background-image:url('tick16.png');
}
table td div[action='Comment']
{
  background-image:url('note24.png');
}
#reason
{
  width: 100%;
}

table tr.aproved td
{
  background-color: #35d59d;

}

table tr.pending td
{
  background-color: #ff4e40;
}

table tr.rejected td
{
  background-color: #FF0A00
  ;
}


table td div[disabled]
{
  width: 16px;
  height: 16px;
  padding-right: px;
}

table td:last-child
{
  width: 80px;
}
</style>
<table id="features"></table>
<div id=dialogs>

  <div title="Decision Reason" style="width: 450px; height:200px">
    <p>Please select the reason for your decision from the list below. Your decision will be emailed to the requestor with the reason that you have selected.</p>
    <select id=reason>
      <?php select::add_db("select code, description from reason where code != 'aapp'", '','','--Select Reason--'); ?>
    </select>
    <br><br>
    <div class=nav>
      <button id=reason_ok wizard=close>OK</button>  <button wizard=close>Cancel</button>
    </div>
  </div>
  
  <div id="comment" title="Add Comment" style="width: 450px; height:200px">
    <p>Please use this form to write your comment. <br>Your comment will be email to the requestor.</p>
    <textarea id="message" style="width:420px;height:150px"></textarea>
    <br><br>
    <div class=nav>
      <button id=reason_ok wizard=close>OK</button>  <button wizard=close>Cancel</button>
    </div>
  </div>
  
</div>