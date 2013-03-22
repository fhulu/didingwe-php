<?php 
require_once "db.php";

class select
{
  static function option($value, $text, $selected=false)
  {    
   if ($selected)  
   $selected = ' selected';
   if ($text=='') $text = $value;
   return "<option value='$value'$selected>$text</option>\n";
 }

  static function add_items($items,$selected=null)
  {
    $items = explode('|', $items);
  
    $options = '';
    foreach($items as $item) {
      list($value, $text) = explode(',', $item);
      $options .= select::option($value, $text, $value==$selected);
    }
    return $options;
  }

  static function read_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    $options = '';
    if (!is_null($first_value))
      $options .= select::option($first_value,$first_text,$first_value==$selected);

    global $db;
    $db->send($sql);
    $descript_field = $db->field_count()<2?0:1; 
   
    while ($db->more_rows()) 
      $options .= select::option($db->row[0],$db->row[$descript_field],$selected==$db->row[0]);
    return $options;
  }
  
  static function add_db($sql,$selected=null,$first_value=null, $first_text=null)
  {
    echo select::read_db($sql, $selected, $first_value, $first_text);
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
  
  static function jsonLoad($request)
  {
    $params = $request['params'];
    log::debug("PARAMS $params");
    $separator = strpos($params, '|')===false?',':'|';
    $params = explode($separator, $params);
    list($table, $code, $value) = $params;
    if ($code == '') {
      $sql = "select * from $table";
    }
    else {
      if ($value == '') $value = $code;
      $sql = "select distinct $code, $value from $table";
      $filter = array_slice($params, 3);
      if (sizeof($filter) > 0) {
        $filter = implode(',', $filter);
        $sql .= " where $filter";
      }
      $sql .= " order by $value";
    }
    
    global $db;
    echo $db->json($sql);
  }
}
?>
