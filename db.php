<?php
require_once('log.php');
require_once('utils.php');

class db_exception extends Exception {
   public function __construct($message, $code = 0)//, Exception $previous = null)
   {
	    parent::__construct($message, $code);//, $previous);
   }
};

class db
{
  var $mysqli;
  var $dbname;
  var $user;
  var $passwd;
  var $hostname;
  var $result;
  var $error;
  var $id;
  var $row;
  var $fields;
  var $field_names;
  var $rows_affected;

  function __construct($dbname,$user,$passwd,$hostname="localhost")
  {
    $this->mysqli = null;
    $this->dbname = $dbname;
    $this->user = $user;
    $this->passwd = $passwd;
    $this->hostname = $hostname;
 }

 function connect($newlink=false)
 {
    if ($this->connected()) return;
    log::debug("MySQL connect to $this->hostname with user $this->user");
    $this->mysqli = new mysqli($this->hostname,$this->user,$this->passwd, $this->dbname);
    if ($this->mysqli->connect_errno) throw new db_exception("Could not connect to '$this->dbname' :" . $this->mysqli->connect_error);
    $this->mysqli->set_charset('utf8');
    log::debug("User $this->user connected to MySQL on $this->hostname");
  }

  function disconnect()
  {
    if ($this->mysqli != null) $this->mysqli->close();
    $this->mysqli = null;
    log::debug("User $this->user disconnected to MySQL on $this->hostname");
  }

  function reconnect()
  {
    if ($this->connected()) $this->disconnect();
    $this->connect();
  }

  function connected() { return $this->mysqli != null; }

  function dup($newlink=false)
  {
     $other = new db($this->dbname,$this->user, $this->passwd, $this->hostname);
     if ($this->connected() || $newlink) $other->connect($newlink);
     return $other;
  }

  function exec($q, $max_rows=0, $start=0)
  {
    global $config;
    if ($start=='') $start = 0;
    if ($max_rows > 0) $q .= " limit $start, $max_rows";
    log::debug("SQL: $q");
    $this->connect();
    $this->result = $this->mysqli->query($q);
    if (!$this->result) throw new db_exception("SQL='$q', ERROR=".$this->mysqli->error);
    if ($this->result === true) {
      $this->rows_affected = $this->mysqli->affected_rows;
      if (preg_match('/^\s*update\s*/i', $q)) {
        preg_match_all ('/(\S[^:]+): (\d+)/', $this->mysqli->info, $matches);
        $info = array_combine ($matches[1], $matches[2]);
        $this->rows_affected = $info['Rows matched'];
      }
      return $this->rows_affected;
    }

    $this->fields = $this->result->fetch_fields();
    $this->field_names = array();
    foreach($this->fields as $field) {
      $this->field_names[] = $field->name;
    }
   }

  function send($q) { return $this->exec($q); }

  function row_valid() { return $this->row != null; }

  function get_row($fetch_type=MYSQLI_BOTH) { return $this->row = $this->result->fetch_array($fetch_type); }

  function more_rows($fetch_type=MYSQLI_BOTH)
  {
    $this->get_row($fetch_type);
    return $this->row_valid();
  }

  function exists($q, $fetch_type=MYSQLI_BOTH, $max_rows = 0)
  {
    $this->exec($q, $max_rows);

    $this->row = $this->result->fetch_array($fetch_type);
    if ($this->row == null) return false;

    $this->id = $this->row[0];
    return true;
  }

  function insert($q)
  {
    $this->exec($q);
    $this->id = $this->mysqli->insert_id;
    return $this->id;
  }

  function delete($table, $key, $value)
  {
    $q = "delete from $table where $key  = '$value'";
    $this->exec($q);
  }

  function field_count()
  {
    return $this->result->field_count;
  }

  function row_count()
  {
    return $this->read_one_value("select found_rows()");
  }

  static function init_default()
  {
    global $db;
    if ($db != null) return;
    global $config;
    $db = new db($config['db_name'], $config['db_user'], $config['db_password'], $config['db_host']);
  }

  function read($sql, $fetch_type=MYSQLI_BOTH, $max_rows=0, $start=0)
  {
    $rows = array();
    $this->each($sql, function($index, $row) use (&$rows) {
      $rows[] = $row;
    }, array('fetch'=>$fetch_type, 'size'=>$max_rows, 'start'=>$start));

    return $rows;
  }

  function each($sql, $callback, $options=null)
  {
    if (!is_null($options)) {
      $start = (int)$options['start'];
      $size = (int)$options['size'];
      $fetch = (int)$options['fetch'];
      if ($fetch == '') $fetch = MYSQLI_NUM;
    }
    else {
      $start = $size = 0;
      $fetch = MYSQLI_NUM;
    }
    $this->exec($sql, $size, $start);
    $index = 0;

    while (($row = $this->result->fetch_array( $fetch))) {
      if ($callback($index, $row)===false) break;
      ++$index;
    }
  }

  function page($sql, $size, $start=0, $callback=null, $options=null)
  {
    $options['start'] = $start;
    $options['size'] = $size;
    $rows = array();
    $this->each($sql, function($index, $row) use (&$rows,$callback) {
      if ($callback)
        $callback($index, $row);
      else
        $rows[] = $row;
    }, $options);
    return $rows;
  }

  function page_names($sql, $size, $start=0, $callback=null, $options=null)
  {
    $options['fetch'] = MYSQLI_ASSOC;
    return $this->page($sql, $size, $start, $callback, $options);
  }

  function page_indices($sql, $size, $start=0, $callback=null, $options=null)
  {
    $options['fetch'] = MYSQLI_NUM;
    return $this->page($sql, $size, $start, $callback, $options);
  }


  function page_through($sql, $size, $offset=0, $callback=null, $options=['fetch'=>MYSQLI_BOTH])
  {
    $options['size'] = $size;
    $options['start'] = $offset;
    $index = 0;
    $rows = [];
    do {
      $batch = $this->read($sql, $options['fetch'], $size, $offset);
      if (is_null($callback))
        $rows = array_merge($rows, $batch);
      else
        $callback($batch, $index++);
      $offset += $size;
    } while (sizeof($batch) == $size);
    return $rows;
  }

  function page_through_names($sql, $size=500, $offset=0, $callback=null, $options=null)
  {
    $options['fetch'] = MYSQLI_ASSOC;
    return $this->page_through($sql, $size, $offset, $callback, $options);
  }

  function page_through_indices($sql, $size=500, $offset=0, $callback=null, $options=null)
  {
    $options['fetch'] = MYSQLI_NUM;
    return $this->page_through($sql, $size, $offset, $callback, $options);
  }

  function read_column($sql, $column_idx=0)
  {
    $this->exec($sql);
    $rows = array();
    while (($row = $this->result->fetch_row())) $rows[] = $row[$column_idx];
    return $rows;
  }

  function read_one($sql, $fetch_type=MYSQLI_BOTH)
  {
    $rows = db::read($sql, $fetch_type, 1);
    return empty($rows)? $rows: $rows[0];
  }

  function read_one_value($sql)
  {
    $this->exec($sql, 1);
    $row = $this->result->fetch_row();
    return at($row,0);
  }

  function json($sql, $max_rows=0, $fetch_type=MYSQLI_ASSOC)
  {
    return json_encode($this->read($sql, $fetch_type, $max_rows));
  }

  function lineage(&$values, $key, $parent_key, $table, $other='')
  {
    $value = $values[sizeof($values)-1];
    $sql = "select $parent_key from $table where $key = '$value' $other";
    $value = $this->read_one_value($sql);
    if ($value == null) return;
    $values[] = $value;
    $this->lineage($values, $key, $parent_key, $table, $other);
  }

  function listing($sql, $separator=',')
  {
    return implode($separator, $this->read_column($sql));
  }
  function encode_listing($sql, $separator=',')
  {
    $list = $this->read_column($sql);
    $result = '';
    foreach($list as $val) {
      if ($result != '') $result = $result.',';
      $result .= rawurlencode($val);
    }
    return $result;
  }

  static function addslashes($array)
  {
    $output = array();
    foreach($array as $key => $val) {
      $output[$key] = is_string($val)? addslashes($val): $val;
    }
    return $output;
  }

  static function quote($array)
  {
    return db::addslashes($array);
  }
  static function stripslashes($array)
  {
    $output = array();
    foreach($array as $key => $val) {
      $output[$key] = stripslashes($val);
    }
    return $output;
  }

  static function unquote($array)
  {
    return db::stripslashes($array);
  }

  static function parse_column_name($name)
  {
    $list = explode(' ',$name); //todo: use regex for calculated fields
    $spec = $list[0];
    $alias = at($list,1);
    $parts = explode('.', $spec);
    $schema = null;
    $table = null;
    switch(sizeof($parts)) {
      case 0:
      case 1: $column = $spec; break;
      case 2: list($table, $column)= $parts; break;
      case 3: list($schema, $table, $column)= $parts; break;
    }
    if (is_null($alias)) $alias = $column;
    return array('spec'=>$spec, 'schema'=>$schema, 'table'=>$table, 'column'=>$column, 'alias'=>$alias);
  }

  function field_names($table)
  {
    return $this->read_column("show columns from $table");
  }

  static function name_value($arg, $values)
  {
    if (is_array($arg))
      list($arg,$value) = assoc_element($arg);
    else if (is_array($values[$arg]))
      return [$arg];
    else
      $value = $values[$arg];

    if ($value[0] == '/') {
      $value = substr($value,1);
      if ($value[0] == '/') $value = "'". addslashes($value). "'";
    }
    else
      $value = "'". addslashes($value). "'";
    return [$arg, $value];
  }

  function insert_array($table, $options)
  {
    $names = func_get_args();
    array_splice($names, 0, 2, array_keys($options));
    $fields = $this->field_names($table);
    $temp = $names;
    $names = $values = [];
    foreach($temp as $name) {
      list($name,$value) = db::name_value($name, $options);
      if (is_null($value) || !in_array($name, $fields, true)) continue;
      $values[] = $value;
      $names[] = $name;
    }
    $names = implode(',', $names);
    $values = implode(',', $values);
    $sql = "insert $table($names) values($values)";
    return $this->insert($sql);
  }

  function update_array($table, $options)
  {
    $names = array_slice(func_get_args(), 2);
    list($key_name,$key_value) = db::name_value(array_shift($names), $options);


    $fields = $this->field_names($table);
    $sets = array();
    $temp = $names;
    foreach($temp as $name) {
      list($name,$value) = db::name_value($name, $options);
      if (is_null($value) || !in_array($name, $fields, true)) continue;
      $sets[] = "$name = $value";
    }
    if (!sizeof($sets)) return;

    $sets = implode(',', $sets);

    $sql = "update $table set $sets where $key_name = $key_value";
    return $this->exec($sql);
  }

}

$db = null;
if (!isset($daemon_mode)) db::init_default();
