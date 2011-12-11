<?php
require_once('session.php');
require_once('config.php');
require_once('log.php');

class db_exception extends Exception {
   public function __construct($message, $code = 0)//, Exception $previous = null)
   {
	parent::__construct($message, $code);//, $previous);
   }
};

class db
{
  var $handle;
  var $dbname;
  var $user;
  var $passwd;
  var $hostname;
  var $result;
  var $error;
  var $id;
  var $row;


  function __construct($dbname,$user,$passwd,$hostname="localhost")
  {
    $this->handle = null;
    $this->dbname = $dbname;
    $this->user = $user;
    $this->passwd = $passwd;
    $this->hostname = $hostname;
 }

 function connect($newlink=false)
 {
    if ($this->connected()) return;
    $this->handle = mysql_connect($this->hostname,$this->user,$this->passwd, $newlink);
    if (!$this->handle) throw new db_exception("Could not connect to '$this->dbname' :" . mysql_error());
    @mysql_select_db($this->dbname, $this->handle);// or throw new db_exception(mysql_error());
  }

  function disconnect()
  {
    $this->handle = null;
  }
  
  function reconnect()
  {
    if ($this->connected()) $this->disconnect();
    $this->connect();
  }
  
  function connected() { return $this->handle != null; }

  function dup($newlink=false)
  {
     $other = new db($this->dbname,$this->user, $this->passwd, $this->hostname);
     if ($this->connected() || $newlink) $other->connect($newlink);
     return $other;
  }

  function exec($q, $max_rows=0)
  {
    log::debug("SQL: $q");
    if ($max_rows > 0) $q .= " limit 0, $max_rows";
    $this->result = mysql_query($q, $this->handle);
    if (!$this->result) throw new db_exception("SQL='$q', ERROR=".mysql_error());
  }

  function send($q) { return $this->exec($q); }

  function row_valid() { return $this->row != null; }

  function get_row($fetch_type=MYSQL_BOTH) { $this->row = mysql_fetch_array($this->result, $fetch_type); }

  function more_rows($fetch_type=MYSQL_BOTH)
  {
    $this->get_row($fetch_type);
    return $this->row_valid();
  }

  function exists($q, $fetch_type=MYSQL_BOTH, $max_rows = 0)
  {
    $this->exec($q, $max_rows);

    $this->row = mysql_fetch_array($this->result, $fetch_type);
    if (!$this->row) return false;

    $this->id = $this->row[0];
    return true;
  }

  function insert($q)
  {
    $this->exec($q);
    $this->id = mysql_insert_id();
    return $this->id;
  }

  function delete($table, $key, $value)
  {
    $q = "delete from $table where $key  = '$value'";
    $this->exec($q);
  }

  function field_count()
  {
    return mysql_num_fields($this->result);
  }

  static function connect_default()
  {
    global $db;
    if ($db == null) {
      $db = new db(config::$audit_db, config::$audit_user, config::$audit_passwd);
      $db->connect();
    }
  }

  function read($sql, $fetch_type=MYSQL_BOTH, $max_rows=0)
  {
    $this->exec($sql, $max_rows);
    $rows = array();
    $row_count = 0;
    while (($row = mysql_fetch_array($this->result, $fetch_type))) $rows[] = $row;
    return $rows;
  }

  function read_column($sql,$column_idx=0)
  {
    $this->exec($sql);
    $rows = array();
    while (($row = mysql_fetch_row($this->result))) $rows[] = $row[$column_idx];
    return $rows;
  }

  function read_one($sql, $fetch_type=MYSQL_BOTH)
  {
    $rows = db::read($sql, $fetch_type, 1);
    return $rows[0];
  }
  
  function read_one_value($sql)
  {
    $this->exec($sql);
    $row = mysql_fetch_row($this->result);
    return $row[0];
  }

}

$db = null;
db::connect_default();

?>
