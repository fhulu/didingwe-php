<!DOCTYPE html>
<style>

div.head
{
  text-align: center;
}  
div.head p
{
  color: #0033CC;
}
div.head img
{
  height: 150px;
  width: 150px;
}

select.about 
{
	height: 100px;
	width: 100%;
}
select.fpbteam 
{
	height: 50px;
	width: 100%;
}

#dialog select,p
{
  font-size:11px;
}
</style>
<script>
$(function() {
	$( "#dialog" ).dialog();
});
</script>



<div id="dialog" title="About">
  <div class="head">
	<img src="fpb-logo-transparent.png"></img>
	<p><b>Online Submission Tool</b></p>
  </div>
  <div class=center >
	<p> Commissioned by Film and Publication Board </p> 
	<p><b>FPB Team:</b></p>
	<select class=fpbteam size=3>
	  <option>Mmapula Fisha</option>
	  <option>Yewande Langa</option>
	  <option>Cornelius Maesela</option>
	</select>
	<p>Developed by Mukoni Software</p>        
	<p>Development Team:</p>
	<select class=about size=9>
	  <option>Fhulu Lidzhade</option>
	  <option>Elisha Dibakoane</option>
	  <option>Linden Zietsman</option>
	  <option>Mampo Plessie</option>
	  <option>Nonqaba Maseko</option>
	  <option>Tshifhiwa Ramusandiwa</option>
	  <option>Mpfare Matshili</option>
	  <option>Lungelwa Mdingazwe</option>
	  <option>Mzuvukile Mfobo</option>
	</select>
  </div>
</div>

