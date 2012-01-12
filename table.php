<?php

require_once('db.php');

class table_field
{
  var $visible;
  var $max_len;
  var $sum;
  var $sum_type;
  var $value_type;  // numeric / text / selection
};

class table
{
  const TITLES = 0x0001;
  const TOTALS = 0x0002;
  const CHECKBOXES = 0x0004;
  const ALTROWS = 0x0008;
  const SCROLLABLE = 0x0010;
  const PAGEABLE = 0x0020;

  var $fields;
  var $symbols;
  var $totals;
  var $title_rowspan;
  var $checkboxes;
  var $row_count;
  var $row_callback;
  var $cell_callback;
  var $user_data;
  var $flags;
  var $class;
  var $field_names;
  var $page_size;
  var $page_index;
  var $last_page_index;
  var $sql;
  var $visible_field_count;
  function __construct($fields=null, $flags=null, $class=null, $page_size=0, $row_callback=null, $cell_callback=null, &$user_data=null)
  {
    $this->flags = is_null($flags)?  (self::TITLES | self::TOTALS): $flags;
    $this->class = $class;
    $this->row_callback = $row_callback;
    $this->cell_callback = $cell_callback;
    $this->user_data = &$user_data;
    if (!is_null($fields)) $this->set_fields($fields);
    $this->row_count = 0;
    $this->page_size = $page_size;
  }
  
  function set_fields($fields)
  {
    $this->totals = array();
    $this->symbols = array();
    $this->fields = $fields;
    $this->title_rowspan = 1;
    foreach($this->fields as $key=>&$field) {
      if (is_numeric($key)) {
        $this->add_symbol(&$field);
        continue;
      }
      if (is_numeric($field)) {
        $num_fields = (int)$field;
        for ($i = 0; $i < $num_fields; $i++) $this->add_symbol($field);
        continue;
      }
         
      foreach($field as &$sub_field) {
        $this->add_symbol(&$sub_field, $key[0]=='#');
      }
      $this->title_rowspan = 2;
    }
  }
  
  function add_symbol(&$field, $hidden=false)
  {
    $symbol = $field[0];
    if ($symbol == '+' || $symbol == '#' || $symbol == '%') {
      $field = substr($field,1);
    }
    if (!$hidden && $symbol[0] != '#') ++$this->visible_field_count;
    $this->symbols[] = $hidden?'#':$symbol;
  }
  
  static function show_title($field, $rowspan, $colspan=1)
  {
    if ($rowspan > 1)
      echo "\t<th rowspan=$rowspan>$field</th>\n";
    else if ($colspan > 1)
      echo "\t<th colspan=$colspan>$field</th>\n";
    else 
      echo "\t<th>$field</th>\n";
  }

  function show_titles()
  {
    echo "<thead><tr>\n\t";
    $this->flags |= self::TITLES;
    if ($this->flags & self::CHECKBOXES) {
      echo "<th rowspan=$this->title_rowspan><input type='checkbox' name='checkall' onclick='checkAll(\"row[]\", this.checked)'/></th>\n";
    }

    $i = 0;    
    foreach ($this->fields as $key=>&$field) {
      if (is_numeric($key)) {
        if ($this->symbols[$i] != '#') $this->show_title($field, $this->title_rowspan);
        ++$i;
        continue;
      }
      if ($key[0] == '#') {
        $i += is_numeric($field)? (int)$field: sizeof($field);
        continue;
      }
      
      if (is_numeric($field)) {
        $this->show_title($key, 1, $field);
        continue;
      }
      $colspan = 0;
      foreach($field as &$sub_field) {
        if ($this->symbols[$i] != '#') ++$colspan;
        ++$i;
      }
      $this->show_title($key, 1, $colspan);
    }
    echo "</tr>\n\t";
    $i = 0;
    foreach ($this->fields as $key=>&$field) {
      if (is_numeric($key) || is_numeric($field)) {
        ++$i;
        continue;
      }
      foreach ($field as &$sub_field) {
        if ($this->symbols[$i] != '#')  {
            if (!$has_subfields) {
                echo "<tr>\n";
                $has_subfields = true;
            }
            echo "<th>$sub_field</th>";
        }
        ++$i;
      }
    }
    
    if ($has_subfields) echo "\n</tr>";
    echo "\n</thead>\n";
  }

  function show_cells($reset_totals=true)
  {
    global $db;
    echo "<tbody>\n";
    if ($reset_totals) $this->totals = array();
    $this->row_count = 0;
    do {
      $this->show_row($db->row);
      ++$this->row_count;
    } while ($db->more_rows(MYSQL_ASSOC));
    if ($this->flags & self::TOTALS) $this->show_totals();
    echo "</tbody>\n";
    return $row_idx;
  }

  function show_row($row_data)
  {
    if ($this->flags & self::ALTROWS)
      $attr = ($this->row_count % 2)?'':" class = 'alt'";
    else
      $attr = '';
      
    if ($this->row_callback && 
      !call_user_func($this->row_callback, &$this->user_data, &$row_data, $this->row_count, &$attr)) {
      return;
    }
      
    echo "<tr$attr>\n";
    if ($this->flags & self::CHECKBOXES) {
      $keys = array_keys($row_data);
      $value = $row_data[$keys[0]];
      echo "\t<td><input type='checkbox' name='row[]' value='$value' /></td>";
    }
    reset($row_data);
    foreach($this->symbols as $symbol) {
      list($key,$cell) = each($row_data);
      if ($symbol == '#') continue;
      /*
      $attr='';
      if (!is_null($this->cell_callback)) {
        call_user_func($this->cell_callback, &$this->user_data, &$row_data, array($this->row_count, $key), &$attr, &$cell);
      }*/
      echo "<td>$cell</td>";
      if ($symbol == '+' || $symbol == '%') {
        $this->totals[$key] += $cell;
      }
    }
    echo "</tr>\n";
  }
  
  function show_totals()
  {
    $this->flags |= self::TOTALS;
    if (!is_null($this->row_callback) && 
      !call_user_func($this->row_callback, &$this->user_data, &$this->totals, null, &$attr)) {
      return;
    }
      
    $span = ($this->flags & self::CHECKBOXES)?1:0;
    $title = "Total";

    echo "<tr>\n";

    foreach($this->symbols as $symbol) {
      if ($symbol == '#') continue;
      if ($symbol == '+' || $symbol == '%') {      
        list($key,$total) = each($this->totals);
        if ($symbol == '%') $total = round($total/$this->row_count,1);
        if ($span != 0) {
          echo "<th colspan=$span>$title</th>\n";
          $span = 0;
          $title = '';
        }
        echo "<th>$total</th>\n";
      }
      else {
        ++$span;
      }
    }
    if ($span != 0) echo "<th colspan=$span></th>\n";
    echo "</tr>\n";
  }
  
  function show_paging()
  {
  /*
    $colspan = $this->visible_field_count-1;
    echo "<tr class=nav><td colspan=$colspan>\n";
    if ($this->page > 0) {
      $page = $this->page_index-1;
      echo "
        <a href=\"javascript:ajax_inner(this.parentNode.parentNode.parent_node.name,
          'do.php/table/next?p=$prev_page,$input_name=$ifind->hint,k=$key_fields,x=$extra_fields,t=$ifind->table,d=$ifind->dest_key')\">
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
    */
  }
  
  function show($sql = null, $page_index=0)
  {
    if (!is_null($sql)) {
      $this->sql = $sql;
      if ($this->page_size > 0) {
        $sql = 'select SQL_CALC_FOUND_ROWS ' . substr($sql, 6) . " limit $page_index, $this->page_size";
      }
      global $db;
      if (!$db->exists($sql,MYSQL_ASSOC)) return;
      if (is_null($this->fields)) $this->set_fields(array_keys($db->row));
   }
    $class=is_null($this->class)?'':"class=$this->class";
    echo "<div $class><table $class>\n";
    if ($this->flags & self::TITLES) $this->show_titles();
    $this->show_cells();
    if ($this->page_size > 0) {
      $this->page_index = $page_index;
      $this->max_rows = $db->read_one_value('select found_rows()');
      $this->last_page_index = (int)ceil($this->max_rows / $this->page_size) - 1;
    }
    echo "</table>\n";
  }
  
  //table::display($fields, $sql, $class, $altrows, $has_totals, $has_titles)
  static function display($sql, $fields=null, $flags=null, $class=null, $page_size=0, $row_callback=null, $cell_callback=null, &$user_data=null)
  {
    $table = new table($fields, $flags, $class, $page_size, $row_callback, $cell_callback, &$user_data);
    $table->show($sql);
    return $table;
  }
  
  /*
    row_callback: 
    signature: static [boolean] function my_row_callback($user_data, $row_data, $row_num, &$attr, $inner) 
    returns boolean: true means display, false means hide
  */
  
  /*
    cell_callback: 
    signature: static [void] function my_row_callback($user_data, $row_data, $field, &$attr, $inner) 
    $field = array(row, col);
    returns $td
  */
}
?>