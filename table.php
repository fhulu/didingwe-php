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
  const HEADING = 0x0100;

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
  var $sort_field;
  var $sort_order;
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
  
  function set_paging($size, $offset)
  {
    $this->flags |= self::PAGEABLE;
    $this->page_size = $size;
    $this->page_offset = $offset;
  }
  
  function set_sorting($field, $order)
  {
    $this->flags |= self::SORTABLE;
    $this->sort_field = $field;
    $this->sort_order = $order;
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
    if ($symbol == '+' || $symbol == '#' || $symbol == '%' || $symbol == '~') {
      $field = substr($field,1);
    }
    if (!$hidden && $symbol[0] != '#') ++$this->visible_field_count;
    $this->symbols[] = $hidden?'#':$symbol;
  }
  
  function show_title($field, $rowspan, $colspan, $symbol, $name)
  {
    if ($this->flags & self::SORTABLE && $symbol == '~') {
      $sort = " sort";
      if ($name == $this->sort_field) $sort .= " order='$this->sort_order'";
    }
    if ($rowspan > 1)
      echo "\t<th rowspan=$rowspan name='$name'$sort>$field</th>\n";
    else if ($colspan > 1)
      echo "\t<th colspan=$colspan>$field</th>\n";
    else 
      echo "\t<th name='$name'$sort>$field</th>\n";
  }

  function show_heading()
  {
    echo "<tr><th class=heading colspan=$this->visible_field_count>$this->heading";
    if ($this->flags & self::FILTERABLE) $this->show_filter();
    if ($this->flags & self::PAGEABLE) $this->show_paging();
    echo"</th></tr>\n";
  }
  
    function show_paging($new_row=false)
  {
    $last_offset = min($this->page_offset + $this->page_size, $this->row_count-1)+1;
    $offset = $this->page_offset+1;
    if ($new_row) echo "<tr><th colspan=$this->visible_field_count >\n";
    echo <<< HEREDOC
      <div class=paging>
        Showing from <b>$offset</b> to <b>$last_offset</b> of <b>$this->row_count</b>&nbsp;&nbsp;
        <button nav=prev>Prev</button>
        <input type=text value='$this->page_size'/>
        <button nav=next>Next</button>
      </div>
HEREDOC;
    if ($new_row) echo "</th></tr>";

  }
  
  function show_filter()
  {
    echo "<div class='filtering'></div>";
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
      $symbol = $this->symbols[$i];
      if (is_numeric($key)) {
        if ($symbol != '#') $this->show_title($field, $this->title_rowspan, 1, $symbol,$this->field_names[$i]);
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
      $this->show_title($key, 1, $colspan, $symbol, $this->field_names[$i]);
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
  
  
  function show($sql = null)
  {
    if (!is_null($sql)) {
      $this->sql = $sql;
      if (!is_array($sql)) {
        if ($this->flags & self::SORTABLE)
          $sql .= " order by '$this->sort_field' $this->sort_order";
        if ($this->flags & self::PAGEABLE) 
          $sql = 'select SQL_CALC_FOUND_ROWS ' . substr($this->sql, 6) . " limit $this->page_offset, $this->page_size";
        global $db;
        $rows = $db->read($sql, MYSQL_ASSOC);
        if (sizeof($rows) == 0) return;
        if ($this->flags & self::PAGEABLE) {
          $this->row_count = $db->read_one_value('select found_rows()');
        }
        $this->field_names = array_keys($rows[0]);
        if (is_null($this->fields)) $this->set_fields($field_names);
      }
      else {
        $rows = $sql;
        if (is_null($this->fields)) $this->set_fields($rows[0]);
      }
    }
    $class=is_null($this->class)?'':"class=$this->class";
    if ($this->flags & self::PAGEABLE) 
      $paging = " rows=$this->row_count";
    echo "<table $class $paging>\n";
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