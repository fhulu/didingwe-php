
$(function(){
 
  if (type ==  "user") {
      $('#id_reg_fpb').hide() ;
      $('#reg_type_next').attr('steps',1);
      $('#login').attr('steps',2);
      $('#otp_next').attr('steps',4);
    }
    else {
        //$('#upload_next').attr('steps',0);
        $('#id_reg_fpb').show() ;
        $('#id_reg_gct').hide();
        $('.document').show();
      $("[applic_type='user']").hide();
    }
    
  $('#login').click(function(event) {
    var emailRegEx = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i;
    var telRegEx = /^(0[1-8])|\+[1-9][0-9]{8,}$/; 

    if ($("#email").val().search(emailRegEx) == -1)
    {
      alert ("Please enter a valid email address.");
      event.stopImmediatePropagation();
      return false;
    }
     

    if ($("input[name='password']").val().length < 6)
    {
      alert("Please enter at least 6 characters in the \"Password\" field.");
      event.stopImmediatePropagation();
      return false;
    }
    // check if both password fields are the same
    if ($("input[name='password']").val()!= $("input[name='password2']").val())
    {
      alert("The two passwords are not the same.");
      event.stopImmediatePropagation();
      return false;
     
    }
    
    if ($("input[name='cellphone']").val().search(telRegEx)== -1)
    {
      alert("Invalid Phone Number");
      event.stopImmediatePropagation();
      return false;
    }

    if (!jq_confirm('/?a=user/check', 'email')) {
      event.stopImmediatePropagation();
      return false;
    }
    // Submit login details to the server and Send email to the user
    jq_submit('/?a=user/register','first_name,last_name,email,password,cellphone,partner_id');
    $('#thanku_next').removeAttr('disabled');
   
  });
  
  $('#otp_next').click(function(event) {
    if (!jq_confirm('/?a=user/check_otp','otp')) {
      event.stopImmediatePropagation();
      return false;
    }
    
    if ($('#partner_id').val() != undefined) {
      jq_submit('/?a=user/start_approval', 'partner_id');
    }
  });
  
  $('#identity').click(function(event) {
  
    var idRegEx = /^\d{2}((0[1-9])|(1[0-2]))(([012][1-9])|(3[01]))\d{7}$/;
    var fpbNoRegEx=/^FPB[0-9]\\[0-9]{4}\\[0-9]{4}$/i;
    var SARStaxrfRegEx=/^[0-9]{9}$/;
    var vatRegEx=/^[0-9]{10}$/;
    var coRegNoRegEx=  /^(19|20)[0-9]{2}\/?[0-9]{6}\/?[0-9]{2}$/;
  
  
    var id_no = $("input[name='id_no']");
    if (id_no.val() != '' && id_no.val().search(idRegEx) == -1) 
      {
        alert ("Please enter a valid ID Number.");
        event.stopImmediatePropagation();
        return false;
      }   
      
    if($("input[name='tax_ref_no']").val().search(SARStaxrfRegEx) ==-1)
    {
      alert("Please enter a valid SARS Ref no");
      event.stopImmediatePropagation();
      return false;
    }
    if($("input[name='co_reg_no']").val().search(coRegNoRegEx) ==-1)
    {
      alert("Please enter a valid Company Reg Ref no");
      event.stopImmediatePropagation();
      return false;
    }
    
    if ($("input[name='vat_no']").val().search(vatRegEx) == -1)
    {
      alert ("Please enter a valid VAT Number.");
      event.stopImmediatePropagation();
      return false;
    }
    
    if (id_no.is(":visible") && !jq_confirm('/?a=vendor/check_applicant_id','id_no')) {
      event.stopImmediatePropagation();
      return false;
    }
    
    if (!jq_confirm('/?a=vendor/check_co_name,check_trading_as,check_co_reg_no,check_vat_no,check_tax_ref_no','co_name,trading_as,co_reg_no,vat_no,tax_ref_no')) {
      event.stopImmediatePropagation();
      return false;
    }
    
  });

  $('#contacts').click(function(event) {
  
    
    var codeRegEx=/^[0-9]{4}$/;
    
    if($("input[name='postal_code']").val().search(codeRegEx) == -1) 
    {
      alert ("Please enter a valid postal code.");
      event.stopImmediatePropagation();
      return false;
    }   
      
    if($("input[name='code']").val().search(codeRegEx) ==-1)
    {
      alert("Please enter a valid physical code");
      event.stopImmediatePropagation();
      return false;
    }
     
  });
  
  $('#upload_next').click(function(event) {

    if (!jq_confirm('/?a=vendor/save','id_no,partner_group,title,co_name,trading_as,type,co_reg_no,tax_ref_no,vat_no,fpb_reg_no,postal_address,postal_code,tel_no,fax_no,physical_address,code,country,province', 'get')) {
      event.stopImmediatePropagation();
      return false;
    }
    
    $('#docs').submit();
 
  });

  $('#close').click(function(event) {

   
   // jq_submit('/?a=session/logout');
  });

   
  var wizard = $('#wizard').wizard().data('wizard');
  wizard.last_url = '/?c=home';
  wizard.start();
  
  setInterval("disable_unset('contacts','title,first_name,last_name,postal_address,postal_code,cell_no,fax_no,province')",500);
  setInterval("disable_unset('otp_next','token')",500);
 
  setInterval("disable_unset('upload_next','co_reg_copy,tax_clearance_copy')",500);
  setInterval("disable_unset('login','title,fname,lname,email,password,password2,cellphone')",500);
  
});