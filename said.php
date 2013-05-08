<?php

define ('SAID_PATTERN', '/[0-9][0-9][01][0-9][0-3][0-9][0-9]{7}/');
define ('DOB_PATTERN', '/(\b19[0-9][0-9][0-9][1-9][0-3][1-9])|(\b[0-9][0-9][0-9][1-9][0-3][1-9])|([1-9][0-9][0-9][1-9][0-3][0-9]\b)/');
class said 
{
  
  static private function addup($x,$first,$inc,$last)
  {  
	$r=0;
	for($i=$first;$i<=$last;$i+=$inc) $r = $r + substr($x,$i,1);
	return $r;
  } 

  static private function concatup($x,$first,$inc,$last)
  {
	$r="";
	for($i=$first;$i<=$last;$i+=$inc) $r = $r . substr($x,$i,1);
	return $r;
  }   

  static function valid($x)
  {
	$len=strlen($x);
	if ($len < 13) return false;
	$len=13;
	$year = substr($x,0,2) + 0;
	$month = substr($x,2,2) + 0;
	if ($month < 1 || $month > 12) return false;
	$day = substr($x,4,2) + 0;
	if ($day < 1 || $day > 31) return false;
  //uncomment below line to verify using checkdigit
  return true;
  
	$a = said::addup($x,0,2,$len-2);
 	$b = said::concatup($x,1,2,$len-2) * 2;
 	$c = said::addup($b,0,1,strlen($b)-1);
 	$d = $a + $c;
	$check = substr($x,$len-1,1);
 	if (strlen($d) < 2) {
		if ($check != $d) return false;
		return $x;
	}
 	$calc = 10 - substr($d,1,1);
	if ($calc == 10) $calc = 0;
 	if ($calc != $check) return false;
	return true;
 
  }

  static function match($subject, $matches) 
  {
    return preg_match_all(SAID_PATTERN, $subject, $matches);
  }
   
  static function get_dob($id)
  {
    $matches = array();
    if (!preg_match(DOB_PATTERN, $id, $matches)) return '';
    //note: only works for people born before 2000
    return strlen($matches[0])==6? '19'.$matches[0]: $matches[0];
  }
}

?>
