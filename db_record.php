<?php

require_once ('db.php');

class db_record_exception extends Exception {};

class db_record
{
  var $db;
  var $table;
  var $keys;
  var $values;
  var $in_db;
  var $db_fields;
  var $is_new;
  static $field_names;

  function __construct($table, $key_names=null, $data=null, $dest_db=null, $value_names=null)
  {
    global $db;

    if (is_null($dest_db)) 
      $this->db = $db;
    else
      $this->db = $dest_db;

    $this->table = $table;
    $this->values = array();
    $this->keys = array();
    if (is_null($key_names) || is_null($value_names)) {
      $db_fields = db_record::load_field_names($this->db, $table);
      $data_fields = array_keys($data);
      if (!is_null($key_names) && !is_array($key_names)) $key_names = explode(",", $key_names);
      if (!is_null($value_names) && !is_array($value_names)) $value_names = explode(",", $value_names);

      if (is_null($value_names)) {
        $value_names = is_null($data)? $this->db_fields: array_intersect($db_fields, $data_fields);
        if (is_array($key_names)) $value_names = array_diff($value_names, $key_names);
      }
        
      if (is_null($key_names)) 
        $key_names = array_diff(is_null($data)?$db_fields:$data_fields, $value_names);
    }
    if (is_null($data))  {
      db_record::reset_data($this->keys, null, $key_names); 
      db_record::reset_data($this->values, null, $value_names); 
    } 
    else {
      db_record::set_data($this->keys, $data, $key_names); 
      db_record::set_data($this->values, $data, $value_names); 
    }
  }
  
  static function load_field_names($db, $table_name)
  {
    if (is_array(db_record::$field_names[$table_name])) 
      return db_record::$field_names[$table_name];
      

    $db->exec("show columns from $table_name");
    
    $field_names = array();
    while ($db->more_rows()) {
      $field_names[] = $db->row['Field'];
    }
    db_record::$field_names[$table_name] = $field_names;
    return $field_names;
  }

  static function set_data(&$fields, $data, $field_names=null)
  {
    if (is_null($field_names)) $field_names = array_keys($fields);
    if (is_null($field_names)) return;
    foreach($field_names as $name)
      $fields[$name] = $data[$name];
  }

  static function reset_data(&$fields, $data, $field_names=null)
  {
    if (is_null($field_names)) $field_names = array_keys($fields);
    if (is_null($field_names)) return;
    foreach($field_names as $name)
      $fields[$name] = $data;
  }

  function all_field_names()
  {
    return array_merge(array_keys($this->keys), array_keys($this->values));
  }
  
  function trim_all()
  {
    foreach($this->keys as $name => &$value) {
      if (!is_array($value)) $value = trim($value);
    }

    foreach($this->values as $name => &$value) {
      if (!is_array($value)) $value = trim($value);
    }
  }

  function set_keys($data, $field_names=null)
  {
    db_record::set_data($this->keys, $data, $field_names);
  }

  function set_values($data, $field_names=null)
  {
    db_record::set_data($this->values, $data, $field_names);
  }

  function reset_values($data, $field_names=null)
  {
    db_record::reset_data($this->values, $data, $field_names);
  }

  function reset_keys($data, $field_names=null)
  {
    db_record::reset_keys($this->keys, $data, $field_names);
  }



  static function get_name_value_pairs($fields, $pair_separator=",", $name_value_separator="=")
  {
    $pair = null;
    foreach ($fields as $name => $value) 
      $pair .= "$pair_separator$name$name_value_separator'" . addslashes($value) . "'";

    return substr($pair, strlen($pair_separator));
  }

  static function get_sql_where($keys)
  {
    return " where " . db_record::get_name_value_pairs($keys, " and ");
  }

  
  function update($values=null)
  {
    if (is_null($values)) $values = &$this->values;
    $sql = "update $this->table set " . db_record::get_name_value_pairs($values) . db_record::get_sql_where($this->keys);
    $this->db->exec($sql);
  }  

  function update_better($new_values)
  {
    foreach ($this->values as $name => &$current_value) {
      $current_value = trim($current_value);
 
      if (is_numeric($current_value)) {
        if ((int)$current_value != 0) continue;
      }
      else if ($current_value != "0000-00-00" && $current_value != "")
        continue;
 
      $new_value =trim($new_values[$name]);
      if ($new_value == "") continue;
      $current_value = $new_value;
      $updated_values[$name] = $new_value;
    }
    
    if (sizeof($updated_values) > 0) 
      $this->update($updated_values); 
  }
  
  function load_by_key($keys=null, $value_names=null)
  {
    if (is_null($keys)) $keys = $this->keys;
    $this->in_db = false;
    if (is_null($value_names)) 
      $value_names = array_keys($this->values);
    else if (!is_array($value_names)) 
      $value_names = explode(",", $value_names);
      
    $sql = "select " . implode(",", $value_names) . " from $this->table " . db_record::get_sql_where($keys);
  
    if (!$this->db->exists($sql)) return false;

    $this->in_db = true;
    $this->set_values($this->db->row, $value_names);
    return true;  
  }

   function load_keys($keys=null)
  {
    if (is_null($keys)) $keys = $this->keys;
    $this->in_db = false;
    $sql = "select " . implode(",", $keys) . " from $this->table " . db_record::get_sql_where($keys);
  
    if (!$this->db->exists($sql)) return false;

    $this->in_db = true;
    $this->set_keys($this->db->row, $value_names);
    return true;  
  }
 
  
  function load_values($field_names=null)
  {
    return $this->load_by_key($this->keys, $field_names);
  }

  static function create_from_db($db, $table, $keys, $value_names)
  {
    $record = new db_record($db, $table, array_keys($keys), $value_names);

    $record->keys = $keys;
    if ($record->load_values()) return null;
    
    return $record;
  }


  static function create_with_data($db, $table, $key_names, $value_names, $data, $updatable=true)
  {
    if ($updatable) {
      db_record::set_data($keys, $key_names, $data);
      $record = create_from_db($db, $table, $keys, $value_names);
    }
    
    if (!is_null($record)) 
      $record->update_better($data);
    else 
       $record = new db_record($db, $table, $key_names, $values_name, $data);
    return $record;
  }
  

  function get_search_keys($key_names)
  {
    $keys = null;
    if (is_null($key_names)) {
      $keys = $this->keys;
      $key_names = array_keys($keys);
    }
    else {     
      db_record::set_data($keys, $this->keys, $key_names); 
      db_record::set_data($keys, $this->values, $key_names); 
    }
    return $keys;
  }
  
  function exists($keys=null)
  {
    if (is_null($keys)) $keys = $this->keys;
    $sql = "select " . implode(',', array_keys($this->keys))
    . " from $this->table " . db_record::get_sql_where($keys);
    if (!$this->db->exists($sql)) return false;
    db_record::set_data($this->keys, $this->db->row);
    return true;
  }    
  
  static function get_field_names($fields, $separator=",")
  {
    return implode($separator, array_keys($fields));
  }
  
  static function get_field_values($fields, $separator=",")
  {
    $values = '';
    foreach ($fields as $name => $value) 
      $values .= "$separator'" . addslashes($value) . "'";

    return substr($values, strlen($separator));
  }


  function insert($keys=null) 
  {
    $data = array_merge($this->keys, $this->values);
    $sql = "insert $this->table (" . implode(",", array_keys($data));
    $sql .= ") values (" . db_record::get_field_values($data) . ")";
    $this->db->exec($sql);
    if (!is_null($keys)) $this->exists($keys);
  }
 
  function remove($keys)
  {
    if (is_null($keys)) $keys = &$this->keys;
    $sql = "delete $this->table ". db_record::get_sql_where($keys);
    $this->db->exec($sql);
    echo $sql .  "\n";
  }
  
  function get_value($value_name)
  {
    return $this->values[$value_name];
  }

  
  function save($key_names=null)
  {   
    if (!is_null($key_names)) {
      if (!is_array($key_names)) $key_names = explode(',',$key_names);
      $keys = $this->get_search_keys($key_names);
      $keys_custom = true;
    }
    else {
      $keys = $this->keys;
      $keys_custom = false
      ;
    }
    
    $data = array_merge($keys, $this->values);
    $columns = implode(',',array_keys($data));
    $values = db_record::get_field_values($data);
    $where = db_record::get_sql_where($keys);
    $sql = "insert $this->table ($columns) select $values from dual "
        ." where not exists (select 1 from $this->table $where);";
   
    $db = $this->db;
    $db->exec($sql);
    $key_columns = implode(',',array_keys($this->keys));  
    if ($keys_custom || $key_columns != $columns) {
      $sql = " select $key_columns from $this->table $where";
      $db->exists($sql);
      db_record::set_data($this->keys, $this->db->row);
    }
  }

  
  static function load_matched($db, $table, $keys, $value_names=null)
  {
    if (is_null($value_names)) 
      $value_names = db_record::get_field_names($db, $table); 

    if (!is_array($value_names)) 
      $value_names = explode(",", $value_names);
      
    $sql = "select " . implode(",", $value_names) . " from $table " . db_record::get_sql_where($keys);

    while ($db->more_rows()) {
      $record = new db_record($db, $table);
      $this->set_values($value_names, $db->row);
      $records[] = $record;
    }
    return $records;  
  }
}

?>
