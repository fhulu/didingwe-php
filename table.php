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
  const FILTERABLE = 0x0040;
  const SORTABLE = 0x0080;

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
  var $page_offset;
  var $last_page_offset;
  var $sql;
  var $visible_field_count;
  var $dataset;
  function __construct($fields=null, $flags=0, $class=null, $page_size=0, $row_callback=null, $cell_callback=null, &$user_data=null)
  {
    $this->flags = $flags;
    $this->class = $class;
    $this->row_callback = $row_callback;
    $this->cell_callback = $cell_callback;
    $this->user_data = &$user_data;
    if (!is_null($fields)) $this->set_fields($fields);
    $this->row_count = 0;
    $this->page_size = $page_size;
  }
  
  function set_callback($callback)
  {
    $this->row_callback = $callback;
  }
  
  function set_heading($heading)
  {
    $this->heading = $heading;
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

  function show_heading()
  {
    echo "<tr><th class=heading colspan=$this->visible_field_count>$this->heading";
    if ($this->flags & self::PAGEABLE) $this->show_paging();
    echo"</th></tr>\n";
  }
  
  function show_titles()
  {
    echo "<tr>\n\t";
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
  }
 
  function show_row($row_data)
  {
    if ($this->flags & self::ALTROWS)
      $attr = ($this->row_count % 2)?'':" class='alt'";
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
  
  function show_paging($new_row=false)
  {
    $last_offset = $this->page_offset + $this->page_size;
    if ($new_row) echo "<tr><th colspan=$this->visible_field_count >\n";
    echo <<< HEREDOC
      <div class=paging>
        Showing <b>$this->page_offset</b> to <b>$last_offset</b> of <b>$this->max_rows</b>&nbsp;&nbsp;
        <button class=prev onclick='prev_page(this)'>Prev</button>
        <button class=next onclick='next_page(this)'>Next</button>
      </div>
HEREDOC;
    if ($new_row) echo "</th></tr>";

  }
  
  function show($sql = null)
  {
    if (!is_null($sql)) {
      $this->sql = $sql;
      if (!is_array($sql)) {
        if ($this->page_size > 0) 
          $sql = 'select SQL_CALC_FOUND_ROWS ' . substr($this->sql, 6) . " limit $this->page_offset, $this->page_size";
        global $db;
        $rows = $db->read($sql, MYSQL_ASSOC);
        if (sizeof($rows) == 0) return;
        if ($this->page_size > 0 && !is_array($sql)) {
          $this->max_rows = $db->read_one_value('select found_rows()');
          $this->last_page_offset = (int)ceil($this->max_rows / $this->page_size) - 1;
        }
        if (is_null($this->fields)) $this->set_fields(array_keys($db->row));
      }
      else {
        $rows = $sql;
        if (is_null($this->fields)) $this->set_fields($rows[0]);
      }
    }
    $class=is_null($this->class)?'':"class=$this->class";
    echo "<table $class>\n";
    if ($this->flags & (self::TITLES | self::FILTERABLE | self::PAGEABLE)) {
      echo "<thead>\n";
      if ($this->flags & (self::FILTERABLE | self::PAGEABLE)) $this->show_heading();
      if ($this->flags & self::TITLES) $this->show_titles();
      echo "</thead>\n";
    }
    echo "<tbody>\n";
    foreach($rows as $row) $this->show_row($row);
    if ($this->flags & self::TOTALS) $this->show_totals();
    if ($this->flags & self::PAGEABLE) $this->show_paging(true);
    echo "</tbody>\n</table>\n";
  }
  
  static function display($sql_or_data, $fields=null, $flags=null, $class=null, $page_size=0, $row_callback=null, $cell_callback=null, &$user_data=null)
  {
    $table = new table($fields, $flags, $class, $page_size, $row_callback, $cell_callback, &$user_data);
    $table->show($sql_or_data);
    return $table;
  }
}
?>