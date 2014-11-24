<style>


#banner
{
  zoom: 1;
  width:100%;
  text-align: center;
  border-radius: 5px;
  background: white;
    /* Mozilla: */
  background: -moz-linear-gradient(left, #FFFFFF, whitesmoke); 
  /* Chrome, Safari:*/
  background: -webkit-gradient(linear,
              left top, left bottom, from(#FFFFFF), to(whitesmoke));
  /* MSIE */
  filter: progid:DXImageTransform.Microsoft.Gradient(
              StartColorStr='#FFFFFF', EndColorStr='whitesmoke', GradientType=0);
  height: 190px;
}

#banner>div
{
  zoom: 1;
  display: inline-block;
  vertical-align: top;
  line-height: 16px;
  haslayout:1;
}


div.image
{
  float: left;
}

 div.title
{
  top: 60px;
  margin-left: auto;
  margin-right: auto;
  width:300px;
  height: 60px;
  background-color: red;
  color: white;
  border-radius: 4px;
  -moz-box-shadow: 3px 3px 4px #0f0f0f;
  -webkit-box-shadow: 3px 3px 4px #0f0f0f;
  box-shadow: 3px 3px 4px #0f0f0f;
}

div.menu
{
  font-size: 11px; 
  color: #006cb7; 
  float: right; 
  padding-right: 10px  
}

.title p
{
  line-height: 1px;
  top: 10px;
}
</style>
<div class="image">
 <img src="fpb-new-ratings-logo.png" style="width: 355px; padding-right:20px"></img>
</div>
<div class="title">
  <p style="font-size:20px;font-weight: bold">FPB ONLINE</p>
  <p style="font-size:11px">version 1.1</p>
</div>
<div class="menu">
  <ul>
    <?php
      require_once("../common/menu.php");
      $menu = new menu('quik');
      $menu->show();
    ?>
  </ul>
  <div style="float: right" >
    <img src="shades.png" style="width: 110px" ></img>
    <br>
    <b>Film and Publication Board</b><br>
    <i>We Inform. You choose</i>
  </div>
</div>

