<?php require_once('session.php');
require_once('table.php');

class ifind
{ 
  var $input_name;
  var $dest_key;
  var $hint_len;
  var $field_count;
  var $page;
  var $max_page;
  var $max_rows;
  var $key_fields;
  var $extra_fields;
  const PAGESIZE=15;
  
  static function get_dest_key(&$fields)
  {
    foreach($fields as &$field) {
      if ($field[0] == '#') {
        $field = substr($field, 1);
        return $field;
      }
    }
    return null;
  }
  
  static function add($key_fields, $extra_fields, $table)
  {
    $table = $table;
    $key_fields = explode(',', $key_fields);
    $extra_fields = explode(',', $extra_fields);
    
    $dest_key = ifind::get_dest_key($key_fields);
    if (is_null($dest_key))
      $dest_key = ifind::get_dest_key($extra_fields);

    $input_name = $key_fields[0];
    $key_fields = urlencode(implode(',', $key_fields));
    $extra_fields = urlencode(implode(',', $extra_fields));
    echo <<<EOT
      <input type='text' name='$input_name' size=40
        onkeyup="ajax_inner('div_$input_name', 'do.php/ifind/drop?k=$key_fields,$input_name,x=$extra_fields,t=$table,d=$dest_key')" />
      <div class=dropdown id=div_$input_name></div>
EOT;
  }

  static function drop()
  {
    $key_fields = urldecode($_GET[k]);
    $ifind = new ifind;
    $ifind->key_fields = $key_fields;
    $key_fields = explode(',',$key_fields);
    $ifind->input_name = $key_fields[0];
    $ifind->hint = trim($_GET[$ifind->input_name]);
    if ($ifind->hint == '') return;
    $ifind->dest_key = $_GET[d];
    $ifind->page = (int)$_GET[p];
    $ifind->extra_fields = urldecode($_GET[x]);
    $ifind->table = $_GET[t];
    $row = $ifind->page * self::PAGESIZE;

    $where = " where $ifind->input_name like '$ifind->hint%'";
    $split_hints = explode(' ', $ifind->hint);
    if (sizeof($split_hints) > 1) {
      $prefix = ' or (';
      $idx = 0;
      $fields = explode(',', $ifind->key_fields);
      foreach($fields as $field) {
        $hint = $split_hints[$idx];
        $where .= $prefix . $field . " like '$hint%'";
        $prefix = ' and ';
        ++$idx;
      }
      $where .= ')';
    }
    global $db;
    $fields = $ifind->key_fields;
    if ($ifind->extra_fields != '') $fields .= ','.$ifind->extra_fields;
    $sql = "select $fields from $ifind->table $where order by $fields limit $row,".self::PAGESIZE;
    $ifind->max_rows = (int)$db->read_one_value("select count(1) from $ifind->table $where"); 
    //todo: use faster found_rows() instead of count(1)
    $ifind->max_page = (int)ceil($ifind->max_rows / self::PAGESIZE) - 1;
    table::display($sql,null, table::TOTALS, 'dropdown', 0, 'ifind::set_attr', null, &$ifind);
  }

  static function set_attr($ifind, &$row_data, $row_num, &$attr)
  {
    if (!is_null($row_num)) {
      $ifind->field_count = sizeof($row_data);
      $key = $row_data[$ifind->input_name];
      $len = strlen($ifind->hint);
      $row_data[$ifind->input_name] = '<b>'.substr($key, 0, $len).'</b>'.substr($key, $len);
      $dest = $row_data[$ifind->dest_key];
      $attr = <<<EOT
      onclick = "getElementByName('$ifind->input_name').value='$key'; 
        getElementByName('$ifind->dest_key').value=$dest;  
        hide(this.parentNode);"
EOT;
      return true;
    }
    
    $colspan = $ifind->field_count-1;
    $key_fields = urlencode(urlencode($ifind->key_fields));
    $extra_fields = urlencode(urlencode($ifind->extra_fields));
    $input_name = $ifind->input_name;
    echo "<tr class=nav><td colspan=$colspan>\n";
    if ($ifind->page > 0) {
      $prev_page = $ifind->page-1;
      echo "
        <a href=\"javascript:ajax_inner('div_$input_name',
          'do.php/ifind/drop?p=$prev_page,$input_name=$ifind->hint,k=$key_fields,x=$extra_fields,t=$ifind->table,d=$ifind->dest_key')\">
            <img src='prev16.png'></a>";
    }
    echo "</td>\n<td>\n";
//  if ($row_num== self::PAGESIZE) {
    if ($ifind->page < $ifind->max_page) {
      $next_page = $ifind->page+1;
      echo " 
        <a href=\"javascript:ajax_inner('div_$input_name',
          'do.php/ifind/drop?p=$next_page,$input_name=$ifind->hint,k=$key_fields,x=$extra_fields,t=$ifind->table,d=$ifind->dest_key')\">
            <div class=right><img src='next16.png'></div></a>";
    
    }
    echo "</td></tr>\n";
    
    return false;
  }
}
?>
