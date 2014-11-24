<?php
  require_once('../common/session.php');
  require_once('../common/db.php');
  require_once('../common/select.php');
  require_once('../common/table.php');
 
  
?>

<link type="text/css" rel="stylesheet" href="default.style.css"></link>
<script type='text/javascript' src="../common/dom.js"></script>
<script type="text/javascript" src="../common/ajax.js"></script> 
<script type="text/javascript" src="jquery/min.js"></script>
<script> 
  
  
  function deleteUser(button)
  {
    var row = $(button).parent().parent();
    var id = row.attr('id');
    $(button).parent().parent().remove();
    // ajax for deleting
    if(id != '')
    {
      $.get('/do.php/user/deactivate',{id: id} );
  
    }
    
  };
  function activateUser(button)
  { 
    var row = $(button).parent().parent();
    var id = row.attr('id');
    $(button).parent().parent().remove();
    
    var row = $(button).parent().parent();
    var id = row.attr('id');
    var role = row.find("select").val();
    if(id != '')
    {
      $.get('/do.php/user/update_role',{id: id,role: role} );
  
    }
    
  };
  
 
</script>
<fieldset class = "details"  style="width:700px;">
  <div class="centerbox">
    <h2  style="padding-left:9px;"><strong>Activate Users</strong></h2>
  </div >
  <div class="container">
    <div class "line">
      <?php
 
        global $db,$session;
        
        $partner_id = $session->user->partner_id;
        $sql = "select code, name from mukonin_audit.role where code not in ('unreg','base')";
        $role_options = select::read_db($sql, 'reg');
        $roles_dropdown = "<select>$role_options</select>";
        $sql = "select id,email_address, first_name ,last_name, '' role, '' edit  
          from mukonin_audit.user u, mukonin_audit.user_role ur
          where u.id=ur.user_id and ur.role_code in ('reg','unreg') and partner_id = $partner_id ";    
        $headings = array('#id','Email Address','First Name','Last Name','Role','');
        table::display($sql,$headings,table::TITLES | table::ALTROWS,"game",0,
          function (&$user_data, &$row_data, $row_num, &$attr) use ($roles_dropdown)
          {
            $attr .= " id=" .$row_data['id'];
            $row_data['role'] = $roles_dropdown;
            $row_data['edit'] = "<input type='image' name='activate' src='activate.jpg' onclick='activateUser(this);' /> ".	
                                "<input type='image' src='remove16.png' onclick='deleteUser(this);'/>";

            return true;
          }
        );        
      ?>
    </div>
  </div>
</fieldset>


