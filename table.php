<?php

require_once('db.php');
require_once('session.php');

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
  const EXPORTABLE = 0x2000;

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
  var $add_url;
  var $actions;
  var $row_actions;
  var $export_file;
  function __construct($fields=null, $flags=0, $callback=null)
  {
    $this->flags = $flags;
    $this->class = $class;
    $this->callback = $callback;
    if (!is_null($fields)) $this->set_fields($fields);
    $this->row_count = 0;
    $this->page_size = $page_size;
    $this->row_actions = array();
    $this->actions = array();
  }
  
  function set_callback($callback)
  {
    $this->callback = $callback;
  }
  
  function set_heading($heading)
  {
    $this->heading = $heading;
    if ($this->flags & self::EXPORTABLE ) $this->set_exportable($heading);
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
  
  function set_row_action($flag, $action, $value='')
  {
    $this->flags |= $flag;
    $this->set_user_row_action($action,$value);
  }
  
  function set_action($flag, $action, $value='')
  {
    $this->flags |= $flag;
    $this->set_user_action($action,$value);
  }
 
  function set_user_action($action, $value='')
  {
    $this->actions[$action] = $value;
  }
  function set_user_row_action($action, $value='')
  {
    $this->row_actions[$action] = $value;
  }
  
  function set_saver($url)
  {
    table::set_row_action(self::EDITABLE, 'edit');
    table::set_row_action(self::EDITABLE, '#save', $url);
  }
  
  function set_deleter($url)
  {
    $this->set_row_action(self::DELETABLE, 'delete', $url);
  }

  function set_adder($url)
  {
    $this->set_action(self::ADDABLE, 'add', $url);
  }
  
  function set_key($key)
  {
    $this->key_field = $key;
  }
 
  function set_expandable($url=null)
  {
    $this->set_row_action(self::EXPANDABLE, 'expand', $url);
  }
  
  function set_exportable($file_name)
  {
    $this->set_action(self::EXPORTABLE, 'export', "$file_name|Export to Excel");
  }
  
  function set_checker($url, $all_url=null)
  {
    $this->set_action(self::CHECKBOXES, 'checkrow', $url);
    $this->set_user_action('checkall',$all_url);
  }
  
  function set_options($request)
  {
    $this->request = $request;
    if ($request['_size'] > 0 && !is_null($request['_offset']))
      $this->set_paging($request['_size'], $request['_offset']);
      
    if (!is_null($request['_sort']) && !is_null($request['_order']))
      $this->set_sorting($request['_sort'], $request['_order']);    
  }
   
  function set_row_actions($actions)
  {
    if (!is_array($actions)) 
      $actions = explode(',',$actions);
    foreach($actions as $action) {
      $this->row_actions[$action] = '';
    }
  }
  
  function set_actions($actions)
  {
    if (!is_array($actions)) 
      $actions = explode(',',$actions);
    foreach($actions as $action) {
      $this->actions[$action] = '';
    }
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
    $colspan = $this->visible_field_count;
    $colspan += ($this->flags & self::CHECKBOXES)?1:0;

    if (sizeof($this->row_actions) > 0) 
      $colspan++;
    echo "<tr class=$type><th colspan=$colspan>";
    if ($type=='header') {
      if (!is_null($this->heading)) echo "<div class=heading>$this->heading</div>";
      if ($this->flags & self::FILTERABLE) $this->show_filter();
    }
    else {
      echo "<div class=actions>";
      foreach($this->actions as $action=>$value) {
        if ($action == 'checkrow' || $action == 'checkall') continue;
        echo "<div action='$action' ";
        if ($action == 'export') {
          $url = $this->curPageURL(). "&_action=export";
          echo "url='$url'";
        }
        list($value, $desc) = explode('|', $value);
        echo " title = '$desc'></div>";
      }
      echo "</div>";
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
        <button nav=prev><</button>
        <input type=text value='$this->page_size'/>
        <button nav=next>></button>
      </div>
HEREDOC;
  }
  
  function curPageURL() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") { $pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
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
      echo "<th rowspan=$this->title_rowspan><input type='checkbox' name=checkall action=checkall title='Check all items'/></th>\n";
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
    if (sizeof($this->row_actions) > 1)
      $this->show_title('', $this->title_rowspan);
      
    echo "</tr>\n\t";
    $i = 0;
    $has_subfields = false;
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
 
  function show_row_actions($actions)
  {
    echo "<td class=actions>\n";
    if (!is_array($actions)) 
      $actions = explode(',',$actions);
    else
      $actions = array_keys ($actions);
    foreach($actions as $action) {
      if ($action[0] == '#' || $action == 'checkrow' || $action == 'expand') continue;
      list($value, $desc) = explode('|', $this->row_actions[$action]);
      if ($desc=='') $desc = $action;
      echo "<div action='$action' title='$desc'></div>\n";
    }
    echo "</td>\n";
  }
  
  function show_row($row_data, $index)
  {
    $attr = '';
    if (!is_null($this->key_field)) {
      $key_field = $this->key_field;
      $key_value = $row_data[$this->key_field];
      $attr = "$key_field = '$key_value'";
    }
    if ($this->flags & self::ALTROWS)
      $attr .= ($index % 2)?'':" class='alt'";
    if ($this->flags & self::EXPANDABLE)
      $attr .= " expandable";
   
    if ($this->callback && call_user_func($this->callback, &$row_data, $index, &$attr) === false) 
      return;
 
    echo "<tr$attr>\n";
    if ($this->flags & self::CHECKBOXES) 
      echo "\t<td><input type='checkbox' action='checkrow' name='$key_field' value='$key_value'/></td>";
    reset($row_data);
    $show_expand = strpos($attr, ' expandable') !== false;
    $actions_shown = false;
    foreach($this->symbols as $symbol) {
      list($key,$cell) = each($row_data);
      if ($symbol == '#') continue;
      if ($show_expand) {
        $cell = "<div action=expand />$cell";
        $show_expand = false;
      }
      if ($key == 'actions' && sizeof($this->row_actions) > 0) {
        $actions_shown = true;
        $this->show_row_actions($row_data['actions']);
      }
      else 
        echo "<td>$cell</td>";
      if ($symbol == '+' || $symbol == '%') {
        $this->totals[$key] += $cell;
      }
    }
    if (sizeof($this->row_actions) > 0 && !$actions_shown) $this->show_row_actions($this->row_actions);
    echo "</tr>\n";
  }
  
  function show_totals()
  {
    $this->flags |= self::TOTALS;
    if (!is_null($this->callback) && 
      !call_user_func($this->callback, &$this->totals)) {
      return;
    }
      
    $span = ($this->flags & self::CHECKBOXES)?1:0;
    $title = "Total";

    echo "<tr class=totals>\n";

    foreach($this->symbols as $symbol) {
      if ($symbol == '#') continue;
      if ($symbol == '+' || $symbol == '%') {      
        list(,$total) = each($this->totals);
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
    $conjuctor = '';
    $where_pos = strripos($this->sql, "where ");
    $sql = $where_pos === false? ' where ': '';
    $filtered = false;
    foreach($this->request as $key=>$value) {
      $key = str_replace('~', '.', $key);
      if ($key[0] == '_' || $key == 'a' || $key == 'PHPSESSID') continue;
      $sql .= "$key like '%$value%' and ";
      $filtered = true;
    }
    if (!$filtered) return;
    if ($where_pos === false) 
      $this->sql .= substr($sql, 0, strlen($sql)-5);
    else $this->sql = substr($this->sql, 0, $where_pos + 6) . $sql . substr($this->sql, $where_pos + 6);
    
  }
  
  function show($sql = null)
  {
    
    if (!is_null($sql)) {
      if (!is_array($sql)) {
        $this->sql = $sql;
        if ($this->flags & self::FILTERABLE) 
          $this->set_filters();
        if ($this->flags & self::SORTABLE)  {
          if ($this->sort_field[0] == '~')
            $field = substr($this->sort_field,1);
          else
            $field = str_replace('~','.',$this->sort_field);
          $this->sql .= " order by $field $this->sort_order";
        }
        if ($this->request['_action'] == 'export') {
          $this->export();
          return $this;
        }
        if ($this->flags & self::PAGEABLE)
          $this->sql = 'select SQL_CALC_FOUND_ROWS ' . substr($this->sql, 6) . " limit $this->page_offset, $this->page_size";
        global $db;
        $rows = $db->read($this->sql, MYSQLI_ASSOC);
        
        $empty = sizeof($rows) == 0;
        if (!$empty ) {
          foreach ($db->fields as $field) {
            $this->field_names[] = $field->table.'~'.$field->orgname;
          }
          if (is_null($this->fields)) $this->set_fields($this->field_names);
        }
        if ($this->flags & self::PAGEABLE) {
          $this->row_count = $db->read_one_value('select found_rows()');
        }
     }
      else {
        $rows = $sql;
        if (is_null($this->fields)) $this->set_fields($rows[0]);
      }
    }
    $options = '';
    
    if (sizeof($this->actions) > 0) echo "<form>";
    echo "<table>\n<thead$options>\n";
    $this->show_titles();
    if ($this->flags & (self::FILTERABLE | self::PAGEABLE)) $this->show_headerfooter("header");
    echo "</thead>\n";

    if ($this->key_field != '') 
      $options .= " key='$this->key_field'";
    
    foreach(array_merge($this->row_actions, $this->actions) as $name=>$value) {
      if($name[0] == '#') $name = substr($name, 1);
      list($value) = explode('|', $value);
      $options .= " $name='$value'";
    }
    echo "<tbody $options>\n";
    $index = 0;
    if (!$empty) {
      foreach($rows as $row) $this->show_row($row, $index++);
    } 
    if ($this->flags & self::TOTALS) $this->show_totals();
    if ($this->flags & (self::EXPORTABLE | self::ADDABLE) || sizeof($this->actions) > 0) 
      $this->show_headerfooter("footer");
    echo "</tbody>\n</table>\n";
    if (sizeof($this->actions) > 0) echo "</form>";
  }
  
  static function cell_name($col, $row)
  {
    return chr($col+65).$col;
  }
  function export()
  {
    require_once '../PHPExcel/Classes/PHPExcel.php';
    global $db;
    $rows = $db->exec($this->sql);

    if ($db->result !== true ) {
      foreach ($db->fields as $field) {
        $this->field_names[] = $field->table.'~'.$field->orgname;
      }
      if (is_null($this->fields)) $this->set_fields($this->field_names);
    }
    
    $excel = new PHPExcel();   
    global $session; 
    $user = $session->user->first_name ." ".$session->user->last_name;
    $excel->getProperties()->setCreator($user)
							 ->setLastModifiedBy($user)
							 ->setTitle($this->heading)
							 ->setSubject($this->heading)
							 ->setDescription($this->heading)
							 ->setKeywords($this->heading)
							 ->setCategory($this->heading);

    
    $sheet = $excel->setActiveSheetIndex(0);
    $col = 0;
    $fields = $this->fields;
    foreach($this->symbols as $symbol) {
      list($key,$cell) = each($fields);
      list($cell) = explode('|',$cell);
      if ($symbol == '#' || ($key == 'actions' && sizeof($this->row_actions) > 0)) continue;
      $richText = new PHPExcel_RichText();
      $text = $richText->createTextRun($cell);
      $text->getFont()->SetBold(true);
      $sheet->setCellValueByColumnAndRow($col, 1, $richText);
      ++$col;
    }
    $row = 2;
    while ($db->more_rows(MYSQLI_ASSOC)) {
      $col = 0;
      $data = $db->row;
      foreach($this->symbols as $symbol) {
        list($key,$cell) = each($data);
        if ($symbol == '#' || ($key == 'actions' && sizeof($this->row_actions) > 0)) continue;
        $sheet->setCellValueByColumnAndRow($col, $row, $cell);
        ++$col;
      }
      ++$row;
    }
    if ($this->export_file == '') $this->export_file = $this->heading;
    // Redirect output to a client’s web browser (Excel5)
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment;filename=\"$this->export_file.xls");
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
    $objWriter->save('php://output');    
  }
  
  static function remove_prefixes($request,$separator='~')
  {
    $replace  = $request;
    foreach ($request as $key=>$value) {
      list($prefix,$col) = explode($separator,$key);
      if ($col == '')
        $replace[$prefix] = $value;
      else
        $replace[$col] = $value;
    }
    return $replace;
  }
}
?>