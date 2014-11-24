<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('vendor.php');
?>

<link type="text/css" rel="stylesheet" href="default.style.css"></link>
<script src='common/wizard.js'></script>
<script src='common/table.js'></script>
<script> 
var decision;
var row;
var dialogs;
var table_div;
 
$(function() {
  $('#certif_yes').click(function() {
    window.location.href='/?c=vendor/view_cert&id='+row.attr('id');
  });

  $('#reason_ok').click(function(event) {
    var reason = $('#reason option:selected');
    if (reason.val() == '') {
      alert('Must select reason for your decision');
      event.stopImmediatePropagation();
      return;
    }
    row.children().eq(8).html(reason.text());
    $.post('/?a=vendor/update_status', {id: row.attr('id'), reason: reason.val(), code: decision }, function() {
      table_div.refresh();
    });
  });

  dialogs = $('#dialogs').wizard().data('wizard');
  table_div = $('#vendors').table({
    sortField: 'date', 
    sortOrder: 'desc',
    url: '/?a=vendor/table',
    onRefresh: function(table) 
    {  
    
      table.find("tbody td:first_child").css('width',300);
      table.find("div[title='Approve']").click(function() {
        row = $(this).parent().parent();
        $.get('/?a=vendor/approve', {id: row.attr('id')}, function() {
          dialogs.start(0);
          table_div.refresh();
        });
      });
     
      table.find("div[title='Notify']").click(function() {
        row = $(this).parent().parent();
        $.post('/?a=vendor/notify',{id: row.attr('id')} ); 
      });

      table.find("div[title='Suspend'],div[title='Withdraw'],div[title='Reject']").click(function() {
        row = $(this).parent().parent();
        decision = $(this).attr('title').toLowerCase();
        dialogs.start(1);
      });
    },
    
    onExpand: function(row) 
    {
      var next = row.next();
      if (next.attr('class') == 'detail') {
        next.show();
        return true;
      }
      row.after("<tr class=detail><td colspan="+row.children().length+"></td></tr>");
      var detail_row = row.next();
      var id = row.attr('id');
      $('#detail').clone().appendTo(detail_row.find("td")).show();
      detail_row.find('#detail').removeAttr('id');
      load_text(detail_row, '/?a=vendor/detail', {id:id}, function() {
        detail_row.find('a').each(function() {
          if ($(this).attr('proto') !== undefined) return;
          var href = $(this).attr('href');
          if (href == undefined || href == null || href == '' || href == 'null')
            $(this).removeAttr('href');
          else $(this).attr('href','/?a=vendor/view_doc&id='+encodeURIComponent($(this).attr('href')));
        });
      });
      
      return true;
    },
    
    onCollapse: function(row) 
    {
      var next = row.next();
      if (next.attr('class') == 'detail') next.hide();
      return true;
    }
  }).data('table');
});
</script>
<style>
table.game td input[type='button']
{
  width: 55px;
  height: 20px;
  font-size: 11px;
  display: block;
}

table.game td div[title]
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

table.game td div[title='Approve']
{
  background-image:url('ok16.png');
}

table.game td div[title='Reject'],
table.game td div[title='Withdraw']
{
  background-image:url('cancel16.png');
}

table.game td div[title='Suspend']
{
  background-image:url('pause16.png');
}

table.game td div[title='Notify']
{
  background-image:url('notify16.png');
}

#reason
{
  width: 100%;
}

table.game tr.registered td
{
  background-color: #35d59d;
  /*#3bda00;*/
  /*00DD00;*/
}

table.game tr.expired td
{
  background-color: #ff9a00;
}

table.game tr.suspended td
{
  background-color: #ff4e40;
}

table.game tr.rejected td,
table.game tr.withdrawn td
{
  background-color: #FF0A00
  ;
}

table.game tr.cancelled:hover td,
table.game tr.expired:hover td,
table.game tr.withdrawn:hover td,
table.game tr.suspended:hover td,
table.game tr.registered:hover td
{
  color: white;
}

table.game tr.detail,
table.game tr.detail td,
table.game tr.detail:hover,
table.game tr.detail:hover td
{
  background-color: #FEFED8;
}

table.game tr.detail *
{
  font-size: 11px;
}

table.game tr.detail fieldset
{
  position: relative;
  vertical-align: top;
  display: inline-block;
}


table.game tr.detail fieldset>div 
{
  vertical-align: top;
  position: relative;
  padding-bottom: 4px;
  display: inline-block;
}

table.game tr.detail .labels
{
  width: 150px;
  text-align: right;
  font-weight: normal;
}

table.game tr.detail .values 
{
  text-align: left;
  font-weight: bold;
  width: 250px;
  word-wrap: break-word;
}

table.game tr.detail fieldset>div>div
{
  height: 18px;
}
</style>
 <table id="vendors" class="game"> </table>
<div id=dialogs>
  <div title="Download/Print Certificate" style="width:250px; height: 150px">
    <p>Would you like to download and print certificate?</p>
    <div class=hcentre style="width:100px">
      <button id=certif_yes wizard=close>Yes</button><button wizard=close>No</button>
    </div>
  </div>
  <div title="Decision Reason" style="width: 400px; height:200px">
    <p>Please select the reason for your decision from the list below. Your decision will be emailed to the distributor with the reason that you have selected.</p>
    <select id=reason>
      <?php select::add_db("select code, description from reason where code != 'aapp'", '','','--Select Reason--'); ?>
    </select>
    <br><br>
    <div class=container style="float:right">
      <button id=reason_ok wizard=next>OK</button><button wizard=next>Cancel</button>
    </div>
  </div>
</div>

<div id=detail style="display:none" width="100%">
  <div style="width:45%; display: inline-block">
    <fieldset class=container>
      <legend>Company Information</legend>
      <div class="labels" >
        <div>Company Name:</div>
        <div>Trading As:</div>
        <div>Distributor Type:</div>
        <div>Company Registration No:</div>
        <div>SARS Tax Reference No:</div>
        <div>VAT No:</div>
        <div>Identity Number:</div>
      </div>
      <div class="values">
        <div name="co_name"></div>
        <div name="trading_as"></div>
        <div name="type"></div>
        <div><a name="co_reg_doc"><div name="co_reg_no"></div></a></div>
        <div><a name="tcc_doc"><div name="tax_ref_no"></div></a></div>
        <div name="vat_no"></div>
        <div><a name="id_doc"><div name="applicant_id"></div></a></div>
      </div>
    </fieldset>
    <fieldset class="container" style="margin-top: 38px">
      <legend>FPB Registration Information</legend>
      <div class="labels">
        <div>License Number:</div>
        <div>Status:</div>
        <div>Status Reason:</div>
        <div>Last Update Time:</div>
        <div>Last Update User:</div>
      </div>
      <div class="values">
        <div><a name="fpb_reg_no"><div name="fpb_reg_no"></div></a></div>
        <div name="status"></div>
        <div name="status_reason"></div>
        <div name="change_time"></div>
        <div name="update_user"></div>
      </div>
    </fieldset>
  </div>
  <fieldset class="container" >
    <legend>Contact Information</legend>
    <div class="labels">
      <div>Title:</div>
      <div>First Name:</div>
      <div>Last Name:</div>
      <div>Email:</div>
      <div>Mobile Number:</div>
      <div>Tel No:</div>
      <div>Fax No:</div>
      <div style="height: 45px" >Postal Address: </div>
      <div>Postal Code:</div>
      <div style="height: 45px" >Physical Address: </div>
      <div>Postal Code:</div>
      <div>Province:</div>
      <div>Country:</div>
    </div>
    <div class="values">
      <div name="title"></div>
      <div name="first_name"></div>
      <div name="last_name"></div>
      <div><a name="email" proto="mailto:"><div name="email"></div></a></div>
      <div name="cellphone"></div>
      <div name="telephone"></div>
      <div name="fax_no"></div>
      <div style="height: 45px" name="postal_address" ></div>
      <div name="postal_code"></div>
      <div style="height: 45px"  name="physical_address"></div>
      <div name="postal_code"></div>
      <div name="province"></div>
      <div name="country"></div>
    </div>
  </fieldset>
</div>