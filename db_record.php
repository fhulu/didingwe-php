<?php

require_once ("db.php");

class db_record_exception extends Exception {};

class db_record
{
  var $db;
  var $table;
  var $keys;
  var $values;
  var $in_db;

  function __construct($dest_db, $table, $key_names=null, $value_names=null, $data=null)
  {
    global $db;

    if (is_null($dest_db)) 
      $this->db = $db;
    else
      $this->db = $dest_db;

    $this->table = $table;
    $this->values = array();
    $this->keys = array();
    
    if (!is_array($key_names)) $key_names = is_null($key_names)?null:explode(",", $key_names);
    if (!is_array($value_names)) $value_names = is_null($value_names)?null: explode(",", $value_names);

    if (is_null($value_names)) {
      $value_names = db_record::load_field_names($this->db, $table);
       if (is_array($key_names)) $value_names = array_diff($value_names, $key_names);
    }
      
    if (is_null($key_names)) 
      $key_names = array_diff(db_record::load_field_names($this->db, $table), $value_names);
     
    
    if (is_null($data))  {
      db_record::reset_data(&$this->keys, null, $key_names); 
      db_record::reset_data(&$this->values, null, $value_names); 
    } 
    else {
      db_record::set_data(&$this->keys, $data, $key_names); 
      db_record::set_data(&$this->values, $data, $value_names); 
    }    
  }
  
  static function load_field_names($db, $table_name)
  {
    $db->exec("show columns from $table_name");
    while ($db->more_rows()) {
      $field_names[] = $db->row['Field'];
    }
    return $field_names;
  }

  static function set_data($fields, $data, $field_names=null)
  {
    if (is_null($field_names)) $field_names = array_keys($fields);
    foreach($field_names as $name)
      $fields[$name] = $data[$name];
  }

  static function reset_data($fields, $data, $field_names=null)
  {
    if (is_null($field_names)) $field_names = array_keys($fields);
    foreach($field_names as $name)
      $fields[$name] = $data;
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
    db_record::set_data(&$this->keys, $data, $field_names);
  }

  function set_values($data, $field_names=null)
  {
    db_record::set_data(&$this->values, $data, $field_names);
  }

  function reset_values($data, $field_names=null)
  {
    db_record::reset_data(&$this->values, $data, $field_names);
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


  static function create_width_data($db, $table, $key_names, $value_names, $data, $updatable=true)
  {
    if ($updatable) {
      db_record::set_data(&$keys, $key_names, $data);
      $record = create_from_db($db, $table, $keys, $value_names);
    }
    
    if (!is_null($record)) 
      $record->update_better($data);
    else 
       $record = new db_record($db, $table, $key_names, $values_name, $data);
    return $record;
  }
  

  function exists($reload = false)
  {
    if (isset($this->in_db) && !$reload) return $this->in_db;
    $sql = "select " . implode(",", array_keys($this->keys)) . " from $this->table " . db_record::get_sql_where($this->keys);
    global $db;
    $this->in_db = $db->exists($sql);
    return $this->in_db;
  }    
  
  static function get_field_values($fields, $separator=",")
  {
    $values = '';
    foreach ($fields as $name => $value) 
      $values .= "$separator'" . addslashes($value) . "'";

    return substr($values, strlen($separator));
  }


  function insert() 
  {
    $data = array_merge($this->keys, $this->values);
    $sql = "insert $this->table (" . implode(",", array_keys($data));
    $sql .= ") values (" . db_record::get_field_values($data) . ")";

    return $this->db->insert($sql);
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
  
  function save($force_update = false)
  {
    $new_values = array();
    foreach($this->values as $key=>$value) {
      $new_values[$key] = $value;
    }
    
    if ($this->load_by_key())
      $force_update? $this->update(): $this->update_better($new_values);
    else 
      $this->insert();
/*
    $data = array_merge($this->keys, $this->values);
    $sql = "insert into $this->table (" . implode(",", array_keys($data)) . ")";
    $sql .= " values (" . db_record::get_field_values($data) . ")";
    $sql .= " on duplicate key update " . db_record::get_name_value_pairs($this->values);
    $this->db->exec($sql);
*/
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
