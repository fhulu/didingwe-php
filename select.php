<?php 
class select
{
  static function option($value, $text, $selected=false)
  {              
   $selected = $selected? ' selected': '';
   if ($text=='') $text = $value;
   return "<option value='$value'$selected>$text</option>";
 }

  static function add_items($items,$selected=null)
  {
    $items = explode('|', $items);
  
    foreach($items as $item) {
      list($value, $text) = explode(',', $item);
      echo select::option($value, $text, $value==$selected);
    }
  }

  static function read_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    if (!is_null($first_value))
      return select::option($first_value,$first_text,$first_value==$selected);

    global $db;
    $db->send($sql);
    $descript_field = $db->field_count()<2?0:1; 
   
    $options = '';
    while ($db->more_rows()) 
      $options .= select::option($db->row[0],$db->row[$descript_field],$selected==$db->row[0]);
    return $options;
  }
  
  static function add_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    echo select::read_db($sql, selected, $first_value, $first_text);
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
