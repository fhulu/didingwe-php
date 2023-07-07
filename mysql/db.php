<?php

class db_exception extends Exception {
   public function __construct($message, $code = 0)//, Exception $previous = null)
   {
	    parent::__construct($message, $code);//, $previous);
   }
};

class db
{
  var $manager;
  var $mysqli;
  var $database;
  var $user;
  var $passwd;
  var $hostname;
  var $port;
  var $socket;
  var $result;
  var $error;
  var $id;
  var $row;
  var $fields;
  var $field_names;
  var $rows_affected;
  var $config;

  function __construct($manager, $config) {
    $this->manager = $manager;
    $this->config = $config;
    $this->mysqli = null;
    $this->request = $manager->request;
    [$this->database, $this->user, $this->passwd, $this->hostname, $this->port, $this->socket] = assoc_to_array($config, 
      'database', 'user', 'password', 'host', 'port', 'socket');
  }

   function connect($newlink=false)
  {
    if ($this->connected()) return;
    log::debug("MySQL connect to $this->hostname with user $this->user");
    $this->mysqli = new mysqli($this->hostname,$this->user,$this->passwd, $this->database, $this->port, $this->socket);
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
    $q = $this->translate_sql($q);
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

  function read($sql, $fetch_type=MYSQLI_BOTH, $max_rows=0, $start=0)
  {
    $rows = array();
    $this->each($sql, function($row) use (&$rows) {
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
      if ($callback($row, $index)===false) break;
      ++$index;
    }
  }

  function page($sql, $size, $start=0, $callback=null, $options=null)
  {
    $options['start'] = $start;
    $options['size'] = $size;
    $rows = array();
    $this->each($sql, function($row, $index) use (&$rows,$callback) {
      if ($callback)
        $callback($row, $index);
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

  function pivot($sql, ...$names) {
    $result = [];
    $row = [];
    $this->each($sql, function($data, $index) use (&$result, &$row) {
      [$key, $name, $value] = $data;
      if (!sizeof($row)) {
        $row[] = $key;
      }
      else if ( $row[0] !== $key) {
        $result[] = count($names)? assoc_to_array($row, ...$names): $row;
        $row = [$key];
      }
      if (!$names || in_array($name, $names, true))
        $row[$name] = $value;
    });
    $result[] = count($names)? assoc_to_array($row, ...$names): $row;
    return $result;
  }

  function replace_sql(&$sql, $options) {
    $exclusions  = $this->config['dont_escape'];
    $sql =  replace_vars($sql, $options, function(&$val, $key) use (&$exclusions) {
      if (in_array($key, $exclusions)) return;
      if (is_array($val))
        $val = json_encode($replace_fields($val));
      $val = addslashes($val);
    });
    return $sql;
  }

  function translate_sql($sql) {
    $manager = $this->manager;
    $manager->replace_auth($sql);
    $this->replace_sql($sql, $manager->answer) ;
    $this->replace_sql($sql, $manager->context);
    return $this->replace_sql($sql, $manager->request);
  }

  function values($sql) { 
    return $this->manager->foreach? $this->read($sql, MYSQLI_ASSOC): $this->read_one($sql, MYSQLI_ASSOC);
  }

  function values_array($sql) {
    return $this->read($sql, MYSQLI_ASSOC);
  }

  static function append_dynamic_conditions(&$sql, $conditions, $combiner) {
    if (empty($conditions)) return;
    $conditions = implode(" $combiner ", $conditions);

    if (($pos = strrpos($sql, ' where ')) !== false)
      $sql = substr($sql, 0, $pos + 7) . " ($conditions) and " . substr($sql, $pos + 7);
    else if (($pos = strrpos($sql, ' group by ')) !== false)
      $sql = substr($sql, 0, $pos) . " where ($conditions) " . substr($sql, $pos);
    else
      $sql .= " where ($conditions) ";
  } 

  static function get_sql_fields($sql) {
    $sql = preg_replace('/\s/', ' ', $sql);
    $matches = [];
    if (!preg_match('/^\s*select\s+(?:distinct\s+)?(.*)\s+from/im', $sql, $matches)) return [];

    if (!preg_match_all("/(\"[^\"]+\"|'[^']+'|\w*\((?:[^()]|(?R))*\)|\w+(?:\.\w+)?)(?:(?: *as)? +\w*)?/im", $matches[1], $matches, PREG_SET_ORDER)) return [];
    array_walk($matches, function(&$match) {
      $match = $match[1];
    });
    return $matches;
  }

  function update_custom_filters(&$sql) {
    $sql = preg_replace('/\s/', ' ', $sql);
    $fields = db::get_sql_fields($sql);
    $index = 0;
    $filters = [];
    $terms = [];
    $term = at($this->request, 'term', null);
    foreach($fields as $name) {
      $value = at($this->request, "f$index", null);
      ++$index;
      if (!is_null($value) && $value !== "" && $value != "=") {
        if ($value[0 ]== '=' ) {
          $value = substr($value, 1);
          $filters[] = "$name = '$value'";
        }
        else
          $filters[] = "$name like '%$value%'";
      }
      if (!is_null($term))
        $terms[] = "$name like '%$term%'";
    }

    db::append_dynamic_conditions($sql, $filters, "and");
    db::append_dynamic_conditions($sql, $terms, "or");
  }

  function data($sql) {
    $sql = $this->translate_sql($sql);
    $this->update_custom_filters($sql);
    $offset = on_null($this->request['offset'], 0);
    $size = on_null($this->request['size'], 0);
    if ($size > 0)
      $sql = preg_replace('/^(\s*SELECT)/i', '$1 SQL_CALC_FOUND_ROWS', $sql);
  
    $sort = $this->request['sort'];
    if (isset($sort))
      $sql .= "order by $sort " . $this->request['sort_order'];
  
    
    return ['data'=>$this->page($sql, $size, $offset, null, ['fetch'=>MYSQLI_NUM]), 'count'=>$this->row_count()];
  }

  function sql($sql) {
    if (preg_match('/^\s*select/im', $sql)) return $this->data($sql);
    $sql = $this->translate_sql($sql);
    return ['data'=>$this->exec($sql),'count'=>$this->row_count()];
  }

  function json_array($sql) {
    $values = $this->values_array($sql);
    foreach($values as &$value) {
      list($key, $object) = assoc_element($value);
      if (is_array($object)) continue;
      $id = $value['id'];
      if (!isset($id)) {
        $id =  strtolower(str_replace(' ', '_', $value['name']));
      }
      else {
        unset($value['id']);
      }
      $value = [$id => $value];
    }
    return $values;
  }

  function get_sql_pair($arg) {
    $manager = $this->manager;
    if (!is_array($arg)) return [$manager->get_db_name($arg), "'\$$arg'"];

    list($arg,$value) = assoc_element($arg);
    if (is_string($value) && at($value, 0) === '/')
      $value = substr($value,1);
    else if (!is_array($value) && !in_array($arg, $this->config['dont_escape']))
      $value = "'". addslashes($value). "'";
    return [$manager->get_db_name($arg), $value];
  }

  function get_sql_pairs($args) {
    $pairs = [];
    foreach($args as $arg) {
      $pairs[] = $this->get_sql_pair($arg);
    }
    return $pairs;
  }

  function update(...$args) {
    $table = array_shift($args);
    list($key_name,$key_value) = $this->get_sql_pair(array_shift($args));
    if (!isset($key_value)) $key_value = "\$$key_name";
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for db.update()");


    $this->manager->parse_delta($args);

    if (!sizeof($args)) return null;

    $fields = $this->field_names($table);
    $sets = array();
    foreach($args as $arg) {
      list($arg,$value) = $this->get_sql_pair($arg);
      if (!in_array($arg, $fields)) continue;
      $sets[] = "$arg = $value";
    }
    if (!sizeof($sets)) return null;

    $sets = implode(',', $sets);

    $sql = "update $table set $sets where $key_name = $key_value";
    return $this->exec($sql);
  }

  function insert(...$args) {
    $table = array_shift($args);
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for sql_insert");
    $values = array();
    foreach($args as &$arg) {
      list($arg,$value) = $this->get_sql_pair($arg);
      $values[] = $value;
    }
    $args = implode(',', $args);
    $values = implode(',', $values);
    $sql = "insert $table($args) values($values)";
    $this->exec($sql);
    $table_spec = explode(".", $table);
    $table = sizeof($table_spec)>1? $table_spec[1]: $table_spec[0];
    return $this->values("select last_insert_id() new_${table}_id");
  }

  function update_insert(...$args) {
    $rows_updated = call_user_func_array([$this, 'update'], $args);
    if ($rows_updated) return $rows_updated;
    array_splice($args, 1, 1);
    $result = call_user_func_array([$this, 'insert'], $args);
    $table = $args[0];
    return [$table => $result["new_{$table}_id"] ];
  }

  function select(...$args) {
    $table = array_shift($args);
    $key = array_shift($args);
    if (!sizeof($args))
      throw new Exception("Invalid number of arguments for db.select");
    $sql = "select from $table where $key = '\$$key'";
    return $this->exec($sql);
  }

  function decode_sql($message)
  {
    $matches = [];
    preg_match_all('/sql\s*\((.+)\)/ims', $message, $matches, PREG_SET_ORDER);
    foreach($matches as $match) {
      $data = $this->read_one($match[1], MYSQLI_NUM);
      $message = str_replace($match[0], implode(' ', $data), $message);
    }
    return $message;
  }

}