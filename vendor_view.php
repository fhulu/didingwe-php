<?php
require_once('../common/select.php');
require_once('../common/ifind.php');
require_once('../common/db.php');
require_once('home.php');
$type = $_GET['type'];
?>

<title>Registration Form </title>
<script type="text/javascript" src="../common/dom.js"></script>
<script type="text/javascript" src="../common/ajax.js"></script>
<script type="text/javascript" src="../common/select.js"></script>
<script type='text/javascript' src='../common/wizard.js'></script>
<script type='text/javascript' src='vendor_view.js'></script>

  
<style>
.input
{
  width: 280px;
  height: 20px;
}
select
{
  width: 200px;
  height: 23px;
  border-radius: 8px;
}

.button-medium
{
  width: 80px;
  border-radius: 8px;
  font-size: 11px;
}  

.nav
{
  padding-top: 10px;
  float: right;
}

p
{
  font-size: 12px;
}

</style>  
<script>
  var type = '<?=$type?>';
</script>

<div id="wizard" style="width:460px;">
  <div title="Login Details" style="width:450px;">
    <p> Information entered here will be used to allow you to log into the system.
    Please ensure that this information is entered correctly as without it you
    will not be able to get access to the system.</p>
    <p style="color:red;">	* denotes required fields</p>
    <div class="container">
      <div class="labels">
        <div class="line">* Title:</div>
        <div class="line">* First Name:</div>
        <div class="line">* Last Name: </div>
        <div class="line" applic_type=user>* Business Name:</div>
        <div class="line">* Email  </div>
        <div class="line">* Password</div>
        <div class="line">* Confirm Password</div>
        <div class="line">* Mobile Number</div>
      </div>
      <div class="controls">
        <div class="line">
          <select  name="title" class="input">
            <?= select::add_items(",--Select Title--|Mr.|Mrs.|Miss|Dr",''); ?>
          </select>
        </div>  
        <div class="line">
          <input type='text' name='first_name'  class="input" />
        </div>
        <div class="line">
          <input type='text' name='last_name'  class="input" />
        </div> 
        <div class="line" applic_type=user>
          <select name=partner_id id='partner_id' class=input>
            <?php select::add_db("select id, co_name from vendor where is_active=1",0,0,'--Select Organisation--'); ?>
          </select>
        </div>
        <div class="line" id="inputs">
          <input type='text' id='email' title="Valid Email:xxxxx@xxxxx.xx.xx" class="input"  />
        </div>
        <div class="line"  id="inputs">
          <input type='password'  name='password' title="Must be at least 6 characters." class="input"  />
        </div>
        <div class="line">
          <input type='password'  name='password2' class="input"  />
        </div>
        <div class="line">
          <input type='text' name='cellphone' class="input" />
        </div>
      </div>
    </div>
    <div class=nav>
      <button wizard=next id=login class="button-medium" >Submit</button>
    </div>
  </div>
   <div title="Thank you" style="width:500px;" >
    <p>Thank you. You will soon be receiving an email with a One Time Password to continue with your registration.</p>
    <div class ='document'>
      <p><b>Note: There are documents that are required to be submitted.</b></p>
      <ul style="font-size: 12px;">
        <li>Copy of Tax clearance certificate.</li>
        <li>Certified Copy of ID <i>(required for Sole Distributors).</i></li>
        <li>Copy of Company Registration Document</li>
      </ul>
    </div>
    <div class=nav>
      <button wizard=next id=thanku_next class="button-medium" >Next</button>
    </div>
  </div>
   <div title="One Time Password" style="width:440px;">
    <p> Please insert the Token number that has been sent to you via email or by sms.</p>
    <p style="color:red;">	* denotes required fields</p>
    <div class="container">
      <div class="labels" style="width:170px;">
        <div class="line">* One Time Password </div>
      </div>
      <div class="controls">
        <div class="line" >
          <input type='text' name='otp' class="input" style="width:200px;" />
            
        </div>
        <div class=nav>
          <button wizard=next id=otp_next class="button-medium">Next</button>
        </div>
      </div>     
    </div>   
  </div>

  <div title="Identity Information" style="width:470px;">
    <p>This information is required to verify that you are indeed a registered
      that is allowed to trade as a distributor/publisher under South African 
      regulations.
    </p>
     <p style="color:red;">	* denotes required fields</p>   
    <div class=container>
      <div class="labels" >
        <div class=line>Applicant's ID No:</div>
        <div class=line>* Business Name:</div>
        <div class=line>Trading As:</div>
        <div class=line>* Distributor Type:</div>
        <div class="line">* Company Registration No:</div>
        <div class="line">* SARS Tax Reference No:</div>
        <div class="line">* VAT No:</div>
      </div>
      <div class="controls">
        <div class="line">
          <input type='text'  name='id_no' class="input"title="ID Number must be 13 numbers." style="width:220px;"  />
        </div> 
        <div class="line" >
          <input type='text' name='co_name' class="input" style="width:220px;"  />
        </div>
         <div class="line" >
          <input type='text' name='trading_as' class="input" style="width:220px;"  />
        </div>
        <div class="line">
          <select name='type' id='type' class=input style="width:220px;">
            <?php select::add_db("select code, description from mukonin_audit.partner_group where parent_code = 'd' and code != 'rb'",'','','--Select Type--'); ?>
          </select>
        </div>
        <div class="line">
          <input type='text' id='id_reg_no' name='co_reg_no' class="input" style="width:220px;"  />
        </div> 
        <div class="line">
          <input type='text' name='tax_ref_no'  title="SARS Tax Ref No Must be 9 numbers" class="input" style="width:220px;"  />
        </div>  
        <div class="line">
          <input type='text' name='vat_no'  class="input" title="Vat Must be 10 numbers" style="width:220px;" />
        </div>        
      </div>
    </div>
    <div class=nav>
      <button wizard=back class="button-medium">Back</button>
     
      <button wizard=next id=identity class="button-medium">Next</button>
    </div>
  </div>
  <div title="Contact Information" style="width:440px;">
    <p> Please fill in information that will allow FPB to communicate with you. FPB will not pass this information to a third-party.</b>
    <p style="color:red;">	* denotes required fields</p>
    <div class=container>
      <div class="labels" style="width:130px;">
        <div class="line" style="height: 65px" >* Postal Address: </div>
        <div class="line">* Postal Code:</div>
        <div class="line">* Tel No:</div>
        <div class="line">* Fax No:</div>
        <div class="line" style="height: 65px" >* Physical Address: </div>
        <div class="line">* Postal Code:</div>
        <div class="line">* Country:</div>
         <div class="line">* Province:</div>
      </div>
      <div class="controls">
        <div class="line" style="height:65px;" >
          <textarea rows="5" cols="30" name='postal_address'  class="input" style="height:65px;width:260px;"  ></textarea>
        </div> 
        <div class="line">
          <input type='text' name='postal_code'  class="input" style="width:260px;" />
        </div> 
        <div class="line">
          <input type='text' name='tel_no'  class="input" style="width:260px;"/>
        </div> 
        <div class="line">
          <input type='text' name='fax_no' class="input" style="width:260px;"/>
        </div> 
        <div class="line" style="height: 65px" >
          <textarea rows="5" cols="30" name='physical_address'  class="input" style=" height: 65px;width:260px;" ></textarea>
        </div> 
        <div class="line">
          <input type='text' name='code'  class="input" style="width:260px;" />
        </div> 
        <div class="line">
          <select  name="country" style="width:260px;" >
            <?= select::add_db('select code, name from mukonin_contact.country order by name','za') ?>
          </select>
        </div>
        <div class="line">
          <select  name="province"  style="width:260px;">
            <?= select::add_db("select code, name from mukonin_contact.province where country_code='za' order by name",'','','--Select Province--') ?>
          </select>
        </div>
      </div>
    </div>
    <div class=nav>
      <button wizard=back>Back</button>
      <button wizard=next id=contacts class="button-medium">Next</button>
    </div>
  </div>
  
  <div title="Upload Copies" style="width:480px;">
  <p> The following set of documents are required as per legislative regulations. 
    Please scan and upload copies of these documents. </p>
    <p style="color:red;">	* denotes required fields</p>
    <div class="container">
      <div class="labels" style="width:160px;">
        <div class="line">Identity Document:</div>
        <div class="line">* Company Registration:</div>
        <div class="line">* Tax Clearance:</div>
      </div>
      <form id="docs" class="controls" action="/?a=vendor/upload" method="POST"  enctype="multipart/form-data" target="upload_target" >
        <div class="line">
          <input type='file' name='id_copy' size=22 />
        </div> 
        <div class="line">
          <input type='file' name='co_reg_copy' size=22 />
        </div> 
        <div class="line">
          <input type='file' name='tax_clearance_copy' size=22 />
        </div>
      </form>
      <iframe id="upload_target" name="upload_target" src="#" style="width:0;height:0;border:0px solid #fff;"></iframe>      
    </div>
    <p><b>Note:</b><br>In order to complete our application, 
    you will still need to <b>post / courier</b> originals to FPB head office.</p>
   <div class=nav>
      <button wizard=back class="button-medium">Back</button>
      <button wizard=next id=upload_next class="button-medium">Finish</button>

    </div>
  </div>
 
  <div title="Thank you" style="width:350px;">
    <p>Thank you for your registeration. </p>
    <p id='id_reg_fpb'>Please log in to check the status of your registration</p>
    <p id='id_reg_gct'>Your registration will only be active once it has been approved by Administrator</p>
    <p><b id=approver></b></p>
    
    
    <button id=close wizard=close class="button-medium">Close</button>
  </div>
</div>   

