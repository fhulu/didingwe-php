<style>
  #login
  {
    position: relative;
    width: 400px;
    border: 2px solid #006CB7;
    border-radius : 10px; 
    padding: 0px 20px 20px 20px;
  }
  #login p
  {
    color: #E7DA31;
    font-weight: bold;
    font-size: 18px;
    width: 100px;    
  }
  
  #login .labels
  {
    background-image: url('login64.png');
    background-repeat:no-repeat;
    color: #FFFFFF;
  }
  
  .input
  {
    width: 200px;
  }
</style>
<script>
function login()
{
  var resultbox = $("#result");
  resultbox.removeClass("line");
  var result = jq_submit('/?a=session/login', 'email,password');
  if (result[0] != '!') {
    location.replace(result);
    return;
  }
  resultbox.html(result.substr(1));
  resultbox.addClass("line");
}
</script>
<!--link href="login.css" media="screen" rel="stylesheet" type="text/css" /-->
<div class=container style="padding-top: 100px;">
  <div id=login class=hcentre>
    <p class=hcenter >GCT Login</p>
    <div class=container>
      <div class="labels" style="width:150px">
        <div class=line>User Name:</div>
        <div class=line>Password:</div>
      </div>
      <div class="controls">
        <div class=line><input type="text" name="email" id="email" class="input" /></div>
        <div class=line><input type="password" name="password" id="password" class="input" /></div>
      </div>
      <div class=line style="right:0px">
        <button class="mediumbutton" onclick="login()">Login</button>
      </div>
      <div id=result style='color:red'></div>
    </div>
  </div>
</div>
