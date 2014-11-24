<?php
require_once('../common/session.php');
require_once('../common/select.php');
require_once('../common/db.php');
require_once('vendor.php');

                      
if (sizeof($_POST) > 0) {
  log::init('session', log::DEBUG);
   // echo $_POST["title"],$_POST["reg_number"] ,$_POST['Telephone'],$_POST["user_id"],$_POST["country"],$_POST["fname"],$_POST["lname"] ;
  $title = $_POST['title'];
  $fname = $_POST['fname'];
  $lname = $_POST['lname'];
  $postal_address = $_POST['postal_address'];
  $postal_code = $_POST['postal_code'];
  $cell_no = $_POST['cell_no'];
  $fax_no = $_POST['fax_no'];
  $province = $_POST['province'];
  $applicants_id_no = $_POST['applicants_id_no'];
  $business_name = $_POST['business_name'];
  $registration_no = $_POST['registration_no'];
  $tax_ref_no = $_POST['tax_ref_no'];
  $fpb_registration_no = $_POST['fpb_registration_no'];
  $email = $_POST['email'];
  $password = $_POST['password'];

  global $db, $user_id;
  
  $user = user::create($email, $password, $fname, $lname);

  $sql ="INSERT INTO vendor(id, title,postal_address,postal_code,cell_no,fax_no,province,applicants_id_no,business_name,registration_no,
        tax_ref_no,fpb_registration_no)
        VALUES($user->id,'$title','$postal_address','$postal_code','$cell_no','$fax_no','$province','$applicants_id_no','$business_name',
        '$registration_no','$tax_ref_no','$fpb_registration_no')";      
  $db->exec($sql);
} 

?>

<meta http-equiv="X-UA-Compatible" content="chrome=1">
<title>Application Form </title>
<script type="text/javascript" src="../common/select.js"></script>
<script type="text/javascript" src="../common/ajax.js"></script>
<script type="text/javascript" src="../common/dom.js"></script>
<link type="text/css" rel="stylesheet" href="gct.css"></link>
<script type="text/javascript">
  setInterval("disable_unset('submit','title,fname,lname,postal_address,postal_code,cell_no,fax_no,province,applicants_id_no,business_name,registration_no,tax_ref_no,fpb_registration_no,identity,company_registration,tax_clearance,email,password,password2')",100);

    function is_valid_msisdn(msisdn)
    {

      var control = getElementByIdOrName(msisdn);
      

      if (control.value.search (/^0[1-8][1-9][0-9]+$/) == -1)
      {
        alert("Invalid Cell Number");
        return false;
      }
      return true;
    }
    function verifyform(form)
    {
     
      if (!is_valid_msisdn('cell_no'))
        return false;
      // require the right format of the Email
    var emailRegEx = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i;
    
      if (form.email.value.search(emailRegEx) == -1)
      {
        alert ("Please enter a valid email address.");
        return false;
      }
        
    
      if (form.password.value.length < 5)
      {
        alert("Please enter at least 5 characters in the \"Password\" field.");
        form.password.focus();
        return false;
      }

      // check if both password fields are the same
      if (form.password.value != form.password2.value)
      {
        alert("The two passwords are not the same.");
        form.password2.focus();
        return false;
      }

        
      if (!ajax_mconfirm('do.php/vendor/check_applicant_id_no,check_registration_no,check_email','check_applicant_id_no,check_registration_no,email'))
         return false;
      return true;
    }	
    
     </script>
  
<style>
.input
{
  width: 280px;
}

input[type='file'] 
{
  size: 40;
  
}

.labels
{
  width: 180px;
}
.controls
{
  right: 0px;
  
}
</style>  

<form id ="myform" name="main_form" action= "vendor_view.php" id=main_form method="POST" onsubmit="return verifyform(this);" >   
  <div class = "info" style="width:500px;">
    <h2><strong>Application Form</strong></h2>
      <fieldset class = "infomation">
        <LEGEND>Contact Information</LEGEND>
          <div class="container">	      
            <div class="labels">
              <div class="line" >Date Of Application  </div>
              <div class="line">Applicant </div>
              <div class="line" style="height: 50px" >Postal Address  </div>
              <div class="line">Postal Code </div>
              <div class="line">Tel No</div>
              <div class="line">Fax No</div>
              <div class="line">Contact Person</div>
            </div>
            <div class="controls">
              <div class="line">
                <input type='text' name='date_of_application'  class="input" />
              </div>
              <div class="line">
                <input type='text' name='applicant'  class="input" />
              </div> 
              <div class="line" style="height: 50px" >
                <textarea rows="5" cols="30" name='postal_address'  class="input" style=" height: 50px;" ></textarea>
              </div> 
              <div class="line">
                <input type='text' name='postal_code'  class="input"  />
              </div> 
              <div class="line">
                <input type='text' name='tel_no'  class="input" />
              </div> 
              <div class="line">
                <input type='text' name='fax_no' class="input" />
              </div> 
              <div class="line">
                <input type='text' name='contact_person' class="input" />
              </div>
            </div>
          </div>
      </fieldset>
      <fieldset class = "infomation">
        <LEGEND>Game Information</LEGEND> 
          <div class="container">	
            <div class="labels">
              <div class="line" >Title Of Game  </div>
              <div class="line">Country Of Origin </div>
              <div class="line">Year Of Production </div>
              <div class="line">Publisher </div>
              <div class="line">Genre</div>
              <div class="line" >Format</div>
            </div>
            <div class="controls">
              <div class="line">
                <input type='text' name='game_title'  class="input" />
              </div>
              <div class="line">
                <input type='text' name='country'  class="input" />
              </div> 
              <div class="line" >
                <input type='text' name='year_of_production'  class="input" />
              </div> 
              <div class="line">
                <input type='text' name='publisher'  class="input"  />
              </div> 
              <div class="line">
                <select id = "genre_code" name = "genre_code" class="input" >
                   <?= select::add_db('select code, description from genre','','','--Select a genre--');?>
                </select>
              </div>
              <div class="container">
                 <?php
         
                    global $db;
                    
                    $sql = 'select code, description from platform';
                    $titles = array('platform');
                    table::display($sql, $titles,table::TITLES | table::ALTROWS | table::CHECKBOXES,"game");
                    
                  ?>     
              </div>
            </div>
          </div>            
      </fieldset>
      
      <fieldset  class = "infomation">
        <LEGEND>Legislative Documents</LEGEND>
        <P><B> PROOF OF RIGHT TO DISTRIBUTE THE GAME IN THE REPUBLIC OF SOUTH AFRICA </B></P>
        <p> The following set of documents are required as per legislative regulations. 
          Please scan and upload copies of these documents. </p>
        <div class="container">
          <div class="labels">
            <div class="line">Identity Document</div>
            <div class="line">Company Registration</div>
            <div class="line">Tax Clearance </div>
          </div>
          <div class="controls">
            <div class="line">
              <input type='file' name='identity' size=30 />
            </div> 
            <div class="line">
              <input type='file' name='company_registration' size=30 />
            </div> 
            <div class="line">
              <input type='file' name='tax_clearance' size=30 />
            </div> 
          </div>
        </div>
        <p><b>Note:</b><br>In order to complete our application, 
            you will still need to post / courier originals to FPB head office.</p>
      </fieldset>
      <fieldset  class = "infomation">
        <LEGEND>Game Description</LEGEND>
        <P><B> Description Of Game Play And Target Market </B></P>
        <div class="container">
          <div class="container" style="right: 0px;left: 0px; ">
            <label  class="line">Original</label><input type='checkbox' name='original'  />
            <label class="line">Add-On</label><input type='checkbox' name='add_on'  />
            <label class="line">Compilation </label><input type='checkbox' name='compilation' />
            <label class="line">Budget-line</label><input type='checkbox' name='budget_line'  />   
          </div>
          <div class="container" style="right: 0px;left: 0px; padding: 6px;">
            <label class="line" style="height: 100px" >Description</label>
            <textarea rows="5" cols="30" name='description'  class="input" style=" height: 100px;" ></textarea>          
          </div>
          <div class="container" style="right: 0px;left: 0px;padding: 6px; ">
            <label  class="line">ELSPA Rating</label><input type='checkbox' name='elspa_rating'  />
            <label class="line" "  >Reason For Rating</label>
            <input type= 'input' name='reason_for_rating'  class="input" style="width: 250px; " />    
          </div>
          <div class="container" style="right: 0px;left: 0px;padding: 6px; ">
            <label  class="line">ESRB Rating</label><input type='checkbox' name='esrb_rating'  />
            <label class="line" "  >Reason For Rating</label>
            <input type= 'input' name='reason_for_rating'  class="input" style="width: 250px; " />    
          </div>
        </div>
        
      </fieldset>
      <fieldset  class = "infomation">
        <LEGEND>Identity Information</LEGEND>
        <div class="container">
          <div class="labels">
            <div class="line">Applicant's ID No </div>
            <div class="line">Business Name</div>
            <div class="line">Business Registration No </div>
            <div class="line">Tax Reference No </div>
          </div>
          <div class="controls">
            <div class="line">
              <input type='text' name='applicants_id_no'  class="input" />
            </div> 
            <div class="line">
              <input type='text' name='business_name'  class="input" />
            </div> 
            <div class="line">
              <input type='text' name='registration_no' class="input" />
            </div> 
            <div class="line">
              <input type='text' name='tax_ref_no'  class="input" />
            </div> 
            
          </div>
        </div>
      </fieldset>
      
      <fieldset class = "infomation"> 
        <LEGEND>Authentication Information</LEGEND>
          <div class="container">
            <div class="labels">
              <div class="line">Email  </div>
              <div class="line">Password</div>
              <div class="line">Confirm Password</div>
            </div>
            <div class="controls">
              <div class="line" id="inputs">
                <input type='text' name='email' title="Valid Email:xxxxx@xxxxx.xx.xx" class="input"  />
              </div>
              <div class="line"  id="inputs">
                <input type='password' name='password' title="Must be at least 5 characters" class="input"  />
              </div>
              <div class="line">
                <input type='password' name='password2' class="input"  />
              </div>
            </div>
          </div>
      </fieldset>
        <div class="line" >
          <input type='submit' id='submit' value="Submit"class="button-medium"  style="width:100px;" />
          <input type='reset'  value="Reset"class="button-medium"style="width:100px;" />
        </div>
      </div>
    </div>
</form>   

