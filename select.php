<?php 

/* remove these comments for functionality - session php is highly needed
require_once('session.php');*/

class select
{
  static function add($value, $text=null, $selected=false)
  {              
    $selected = $selected? ' selected': '';
    if (is_null($text)) 
      echo "<option$selected>$value</option>\n";
    else
      echo "<option value='$value'$selected>$text</option>\n";
  }

  static function add_items($items,$selected=null)
  {
    $items = explode('|', $items);
  
    foreach($items as $item) {
      list($value, $text) = explode(',', $item);
      select::add($value, $text, $value==$selected);
    }
  }

  static function add_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    if (!is_null($first_value))
      select::add($first_value,$first_text,$first_value==$selected);

    global $db;
    $db->send($sql);
    $descript_field = $db->field_count()<2?0:1; 
   
    while ($db->more_rows()) 
      select::add($db->row[0],$db->row[$descript_field],$selected==$db->row[0]);
  }
  static function load_from_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    select::add_db($sql, $selected, $first_value, $first_text);
  }  
  static function add_months($selected=null)
  {
    $selected = is_null($selected) ? date('n', time()) : $selected;

    for ($i = 1; $i <= 12; $i++)
    {
      select::add($i, date("F", mktime(0, 0, 0, $i+1, 0, 0, 0)), $selected==$i);
    }
  }
  
}
?>
