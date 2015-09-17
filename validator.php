<?php
require_once('db.php');
require_once 'curl.php';

class validator_exception extends Exception {};
class validator
{
  var $request;
  var $db;
  var $name;
  var $prev_name;
  var $value;
  var $predicates;
  var $error;
  var $has_error;
  var $fields;
  var $title;
  var $checked_provided;
  var $report_error;
  var $failed_auto_provided;
  function __construct($request, $fields, $predicates=null, $db_conn=null)
  {
    $this->request = $request;
    $this->fields = $fields;
    $this->predicates = $predicates;
    $this->optional = false;
    $this->error = null;
    $this->has_error = false;
    $this->report_error = true;
    $this->checked_provided = false;
    global $db;
    $this->db = is_null($db_conn)? $db: $db_conn;
  }

  function regex($regex)
  {
    return preg_match($regex, $this->value) != 0;
  }

  function url($option)
  {
    $result = $this->regex('/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/');
    if ($result !== true) return $result;

    if ($option == 'visitable') {
      $curl = new curl();
      return $curl->read($this->value,512) != 0;
    }
    return true;
  }

  function value_of($name)
  {
    if (is_numeric($name) || !isset($this->request[$name])) return $name;
    return $this->request[$name];
  }

  function less($name)
  {
    return $this->value < $this->value_of($name);
  }

  function less_equal($name)
  {
    return $this->value <= $this->value_of($name);
  }

  function greater($name)
  {
    return $this->value > $this->value_of($name);
  }

  function greater_equal($name)
  {
    return $this->value >= $this->value_of($name);
  }


  function find_in($table)
  {
    if ($table == '') {
      $table = $this->table;
      if ($table == '')
        throw new validator_exception('checking for existence with no db table supplied');
      $field = $this->name;
    }
    else {
      list($dbname, $table, $field) = explode('.', $table);
      if (!isset($table)) {
        $table = $dbname;
        $field = $this->name;
      }
      else if (!isset($field)) {
        $field = $table;
        $table = $dbname;
      }
      else {
        $table = "$dbname.$table";
      }
    }
    $value = addslashes($this->value);
    return $this->sql("select 1 from $table where $field = '$value'");
  }

  function in($table=null)
  {
    return $this->find_in($table);
  }

  function exist($table=null)
  {
    return $this->in($table);
  }

  function exists($table=null)
  {
    return $this->in($table);
  }

  function either()
  {
    return in_array($this->value, func_get_args());
  }

  function depends($field, $arg)
  {
    $validator = new validator($this->request, $this->fields, $this->predicates, $this->db);
    log::debug("DEPENDS $field $arg");
    return $validator->check($field)->is($arg);
  }

  function provided()
  {
    return $this->value != '';
  }

  function call($function)
  {
    $params = array_slice(func_get_args(), 1);
    log::debug_json("VALIDATE CALL $function PARAMS:", $params);
    list($class, $method) = explode('::', $function);
    $file = "$class.php";
    if (isset($method)) {
      if (file_exists($file))
        require_once("$class.php");
      else if (file_exists("../common/$file"))
        require_once("$class.php");
      else {
        log::error("No such file $file");
        return;
      }
    }

    if (!is_callable($function)) {
      log::warn("Uncallable function $function");
      return;
    }

    //replace_fields($params, $this->request);
    foreach($params as &$param) {
      $param = trim($param);
      if ($param=='this') $param = $this->name;
      if (array_key_exists($param, $this->request)) $param = $this->request[$param];
      $param = replace_vars ($param, $this->request);
    }
    return call_user_func_array($function, $params);
  }

  function get_title($code, $field=null)
  {
    if (is_array($field)) $name = $field['name'];
    if ($name == '') $name = ucwords(str_replace ('_', ' ', $code));
    return $name;
  }

  static function is_static_method($name)
  {
    return strpos($name, '::') !== false;
  }

  function check($name, $title_or_field=null)
  {
    $this->name = $name;
    $this->value = trim(at($this->request,$name));
    $this->checked_provided = $this->failed_auto_provided = false;
    if ($this->prev_name != $name)
      $this->optional = false;
    $this->prev_name = $name;
    if (is_array($title_or_field))
      $this->title = $this->get_title($name, $title_or_field);
    else if (is_string($title_or_field))
      $this->title = $title_or_field;
    else
      $this->title = null;
    $this->error = null;
    return $this;
  }

  function get_custom($func)
  {
    $found = $this->predicates[$func];
    if ($found) return $found;
    $found = $this->fields[$func];
    if (!$found) return null;
    return $found['valid']? $found: null;
  }

  function get_internal_function($func)
  {
    if ($func[0] == '/') return 'regex';
    list($func) = expand_function($func);
    return method_exists($this, $func)? $func: false;
  }


  function is($funcs)
  {
    if (!is_array($funcs)) $funcs = array($funcs);
    log::debug_json("VALIDATE $this->name=$this->value FUNCTIONS: ", $funcs);

    $first = $funcs[0];
    $auto_provided = false;
    if ($first== 'optional') {
      if  ($this->value === '') return true;
      array_shift($funcs);
    }
    else if (!$this->checked_provided && !in_array('provided', $funcs, true)) {
      $pos = array_find($funcs, function($func, $key) {
        $func = $this->get_internal_function($func);
        return $func !== false && $func !== 'depends';
      });
      if ($pos !== false) {
        array_splice($funcs, $pos, 0, 'provided');
        $this->checked_provided = $auto_provided = true;
      }
    }

    foreach($funcs as $func) {
      $func = trim($func);
      if ($func[0] == '/') {
        $args = array($func);
        $func = 'regex';
      }
      else {
        list($func, $args) = expand_function($func);
        $matches = array();
        preg_match_all('/[^,]+|\(.*\)/', $args, $matches);
        $args = $matches[0];
      }
      $this->update_args($args);

      if (validator::is_static_method($func)) {
        array_unshift($args, $func);
        $func = 'call';
        $result = call_user_func_array(array($this, $func), $args);
        array_shift($args);
      }
      else if ($func == 'is' || !method_exists($this, $func)) {
        if (!$this->get_custom($func))
          throw new validator_exception("validator method $func does not exists!");
        array_unshift($args, $func);
        $result = call_user_func_array(array($this, 'custom'), $args);
        array_shift($args);
      }
      else
        $result = call_user_func_array(array($this, $func), $args);
      if ($result === true) continue;
      if ($func == 'depends') return true;
      if ($func == 'provided' && $auto_provided) $this->failed_auto_provided = true;
      $this->has_error = true;
      $this->update_error($func, $args, $result);
      return $result;
    }
    return true;
  }

  function custom($func)
  {
    $args = array_slice(func_get_args(), 1);

    $predicate = $this->get_custom($func);
    if (!is_array($predicate)) {
      $this->replace_args($predicate, $args, false, true);
      $predicate = replace_vars($predicate, $this->request);
      return $this->is($predicate);
    }
    replace_field_indices($predicate, $args);
    $this->update_args($args);
    $valid = $predicate['valid'];
    if (is_array($valid)) {
      foreach($valid as &$param) {
        $this->replace_args($param, $args);
      }
      return $this->is($valid);
    }

    $this->replace_args($valid, $args);
    $valid = replace_vars($valid, $predicate);
    $valid = replace_vars($valid, $this->request);
    $this->replace_args($valid, $args, false, true);
    return $this->is($valid);
  }

  function replace_args(&$str, $args, $set_titles=false, $force_value=false)
  {
    $i = 0;
    foreach($args as $arg) {
      ++$i;
      if ($arg == 'this') $arg = $this->name;
      $field = $this->fields[$arg];
      if ($set_titles && isset($field))
        $name = $this->get_title($arg, $field);
      else
        $name = $arg;

      $str = str_replace('$'.$i, $name, $str);
      if (is_numeric($arg)) continue;

      $value = $this->request[$arg];
      if (isset($value) && $force_value) $value = $arg;
      $str = str_replace('$v'.$i, $value, $str);
    }

    $str = str_replace('$value', $this->value, $str);
    return $str;
  }

  function update_args(&$args)
  {
    foreach($args as &$arg) {
      $arg = trim($arg);
      if ($arg == 'this' || $arg == '$name')
        $arg = $this->name;
      else if ($arg == '$value')
        $arg = $this->value;
     }
  }


  function subst_error(&$error, $predicate, $args, $result)
  {
    $ignore = array('name');
    if (is_array($result)) $error = replace_vars_except ($error, $result, $ignore);
    $error = str_replace('$value', $this->value, $error);
    $error = replace_vars($error, $predicate);
    $this->replace_args($error, $args, true);
    $error = replace_vars_except($error, $this->request, $ignore);
    $error = str_replace('$name', $this->title, $error);
  }

  function update_error($func, $args, $result=null)
  {
    if ($this->failed_auto_provided) $func = 'provided';
    $predicate = $this->get_custom($func);
    if (!is_array($predicate)) return;
    $error = $predicate['error'];
    if (is_string($result)) $error = $result;
    if (is_null($error)) return;

    if (is_string($error))
      $this->subst_error($error, $predicate, $args, $result);
    else walk_leaves($error, function(&$value) use ($predicate, $args, $result) {
      $this->subst_error($value, $predicate, $args, $result);
    });

    $this->error = $error;
  }

  function relate_time($format, $relation)
  {
    $now = Date($format);
    if ($relation == 'future' && $this->value <= $now)
      return false;

    if ($relation == 'past' && $this->value >= $now)
      return false;
    return true;
  }

  function valid()
  {
    return !$this->has_error;
  }

  function sql()
  {
    $sql = implode(',', func_get_args());
    $sql = replace_vars($sql, $this->request);
    if (preg_match('/^(update|insert)\b/ims',$sql)) {
      $this->db->exec($sql);
      return true;
    }
    $result = $this->db->read_one($sql, MYSQLI_BOTH);
    if (!$result) return false;
    if ($result[0]) return true;
    return $result;
  }

  function not($predicate)
  {
    $had_error = $this->has_error;
    $result = $this->is($predicate);
    $this->error = null;
    if ($result) return false;
    $this->has_error = $had_error;
    return true;
  }

}
