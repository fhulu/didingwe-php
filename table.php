<?php

require_once('db.php');

class table_field
{
  var $title;
  var $name;
  var $visible;
  var $width;
  var $edit;
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
  const EXPANDABLE = 0x0200;
  const EDITABLE = 0x0400;
  const DELETABLE = 0x800;
  const ADDABLE = 0x1000;

  var $fields;
  var $symbols;
  var $totals;
  var $title_rowspan;
  var $checkboxes;
  var $row_count;
  var $callback;
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
  var $key_field;
  var $request;
  var $save_url;
  var $delete_url;
  var $add_url;
  function __construct($fields=null, $flags=0, $callback=null)
  {
    $this->flags = $flags;
    $this->class = $class;
    $this->callback = $callback;
    if (!is_null($fields)) $this->set_fields($fields);
    $this->row_count = 0;
    $this->page_size = $page_size;
  }
  
  function set_callback($callback)
  {
    $this->callback = $callback;
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
  
  function set_saver($url)
  {
    $this->flags |= self::EDITABLE;
    $this->save_url = $url;
  }
  
  function set_deleter($url)
  {
    $this->flags |= self::DELETABLE;
    $this->delete_url = $url;
  }

  function set_adder($url)
  {
    $this->flags |= self::ADDABLE;
    $this->add_url = $url;
  }
  
  function set_key($key)
  {
    $this->key_field = $key;
  }
  function set_expandable($key=null)
  {
    $this->flags |= self::EXPANDABLE;
    if (!is_null($key)) 
      $this->set_key($key);
  }
  
  function set_options($request)
  {
    $this->request = $request;
    if ($request['_size'] > 0 && !is_null($request['_offset']))
      $this->set_paging($request['_size'], $request['_offset']);
      
    if (!is_null($request['_sort']) && !is_null($request['_order']))
      $this->set_sorting($request['_sort'], $request['_order']);    
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
  
  function show_headerfooter($type)
  {
    $colspan=$this->visible_field_count;
    if ($this->flags & (self::EDITABLE | self::DELETABLE)) 
      $colspan++;
    echo "<tr class=$type><th colspan=$colspan>";
    if ($type=='header') {
      if (!is_null($this->heading)) echo "<div class=heading>$this->heading</div>";
      if ($this->flags & self::FILTERABLE) $this->show_filter();
    }
    else {
      if ($this->flags & self::ADDABLE) echo "<div class=adder></div>";
    }
    if ($this->flags & self::PAGEABLE) $this->show_paging();
    echo"</th></tr>\n";
  }
  
  function show_paging()
  {
    $last_offset = min($this->page_offset + $this->page_size, $this->row_count);
    $offset = $this->row_count == 0? 0: $this->page_offset+1;
    echo <<< HEREDOC
      <div class=paging rows=$this->row_count>
        Showing from <b>$offset</b> to <b>$last_offset</b> of <b>$this->row_count</b>&nbsp;&nbsp;
        <button nav=prev></button>
        <input type=text value='$this->page_size'/>
        <button nav=next></button>
      </div>
HEREDOC;
  }
  
  function show_filter()
  {
    echo "<div class='filtering' title='Filter/Search'></div>";
  }

  function show_title($field, $rowspan, $colspan=1, $symbol=null, $name=null)
  {
    $options = explode('|', $field);
    $title = array_shift($options);   
    $options = implode(' ', $options);

    if ($this->flags & self::SORTABLE && $symbol == '~') {
      $options .= " sort";
      if ($name == $this->sort_field) $options .= " order='$this->sort_order'";
      if (strpos($options, 'filter') === false && strpos($options, 'filteroff') === false) 
        $options .= ' filter';
    }

    if (!is_null($name)) $options .= " name ='$name'";
    
    if ($rowspan > 1)
      echo "\t<th rowspan=$rowspan $options><div>$title</div></th>\n";
    else if ($colspan > 1)
      echo "\t<th colspan=$colspan><div>$title</div></th>\n";
    else 
      echo "\t<th $options><div>$title</div></th>\n";
  }

  function show_titles()
  {
    $display = $this->flags & self::TITLES? '': " style='display: none;'";
    echo "<tr class=titles$display>\n\t";
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
    if ($this->flags & (self::EDITABLE | self::DELETABLE)) 
      $this->show_title('', $this->title_rowspan);
      
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
            echo "<tr class=titles>\n";
            $has_subfields = true;
        }
          echo "<th>$sub_field</th>";
        }
        ++$i;
      }
    }
    
    if ($has_subfields) echo "\n</tr>";
  }
 
  function show_row($row_data, $index)
  {
    $attr = '';
    if (!is_null($this->key_field))
      $attr = " $this->key_field='". $row_data[$this->key_field] . "'";
   
    if ($this->flags & self::ALTROWS)
      $attr .= ($index % 2)?'':" class='alt'";
    if ($this->flags & self::EXPANDABLE)
      $attr .= " expandable";
      
    if ($this->callback && call_user_func($this->callback, &$row_data, $index, &$attr) === false) 
      return;
 
    echo "<tr$attr>\n";
    if ($this->flags & self::CHECKBOXES) {
      $keys = array_keys($row_data);
      $value = $row_data[$keys[0]];
      echo "\t<td><input type='checkbox' name='row[]' value='$value' /></td>";
    }
    reset($row_data);
    $show_expand = strpos($attr, ' expandable') !== false;
    foreach($this->symbols as $symbol) {
      list($key,$cell) = each($row_data);
      if ($symbol == '#') continue;
      if ($show_expand) {
        $cell = "<div expand=collapsed />$cell";
        $show_expand = false;
      }
      echo "<td>$cell</td>";
      if ($symbol == '+' || $symbol == '%') {
        $this->totals[$key] += $cell;
      }
    }
    if ($this->flags & (self::EDITABLE | self::DELETABLE)) {
      echo "<td class=actions>\n";
      if ($this->flags & self::EDITABLE) echo "<div edit=off></div>\n";
      if ($this->flags & self::DELETABLE) echo "<div delete></div>\n";
      echo "</td>\n";
    }
    echo "</tr>\n";
  }
  
  function show_totals()
  {
    $this->flags |= self::TOTALS;
    if (!is_null($this->callback) && 
      !call_user_func($this->callback, &$this->totals, &$attr)) {
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
  
  function set_filters()
  {
    $conjuctor .= ' where (';
    foreach($this->request as $key=>$value) {
      if ($key[0] == '_' || $key == 'a' || $key == 'PHPSESSID') continue;
      $this->sql .= " $conjuctor $key like '%$value%'";
      $conjuctor = "and";
    }
    if ($conjuctor == "and") $this->sql .= ')';
  }
  
  
  function show($sql = null)
  {
    if (!is_null($sql)) {
      $this->sql = $sql;
      if (!is_array($sql)) {
        if ($this->flags & (self::FILTERABLE | self::SORTABLE))
          $this->sql = "select * from ($sql) tmp";
        if ($this->flags & self::FILTERABLE) 
          $this->set_filters();
        if ($this->flags & self::SORTABLE)
          $this->sql .= " order by `$this->sort_field` $this->sort_order";
        if ($this->flags & self::PAGEABLE)
          $this->sql = 'select SQL_CALC_FOUND_ROWS ' . substr($this->sql, 6) . " limit $this->page_offset, $this->page_size";
        global $db;
        $rows = $db->read($this->sql, MYSQL_ASSOC);
        if ($this->flags & self::PAGEABLE) {
          $this->row_count = $db->read_one_value('select found_rows()');
        }
        
        $empty = sizeof($rows) == 0;
        if (!$empty ) {
          $this->field_names = array_keys($rows[0]);
          if (is_null($this->fields)) $this->set_fields($this->field_names);
        }
      }
      else {
        $rows = $sql;
        if (is_null($this->fields)) $this->set_fields($rows[0]);
      }
    }
    $options = '';
    
    
    echo "<table>\n<thead$options>\n";
    $this->show_titles();
    if ($this->flags & (self::FILTERABLE | self::PAGEABLE)) $this->show_headerfooter("header");
    echo "</thead>\n";
    if ($this->flags & self::EDITABLE | self::DELETABLE) {
      if ($this->key_field != '' ) $options .= " key='$this->key_field'";
      if ($this->flags & self::EDITABLE) $options .= " saver='$this->save_url'";
      if ($this->flags & self::DELETABLE) $options .= " deleter='$this->delete_url'";
    }
    if ($this->flags & self::ADDABLE) $options .= " adder='$this->add_url'";
    echo "<tbody $options>\n";
    $index = 0;
    if (!$empty) {
      foreach($rows as $row) $this->show_row($row, $index++);
    } 
    if ($this->flags & self::TOTALS) $this->show_totals();
    if ($this->flags & (self::PAGEABLE | self::ADDABLE)) $this->show_headerfooter("footer");
    echo "</tbody>\n</table>\n";
  }
}
?>