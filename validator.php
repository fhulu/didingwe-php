<?php

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
  var $valids;
  var $tested;
  var $manager;

  function __construct($manager) {
    $this->manager = $manager;
    $this->optional = false;
    $this->error = null;
    $this->has_error = false;
    $this->report_error = true;
    $this->checked_provided = false;
    $this->valids = [];
    $this->tested = [];
  }

  function init($values, $fields, $predicates) {
    $this->values = $values;
    $this->fields = $fields;
    $this->predicates = $predicates;
  }
  
  function regex($regex) {
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

  function visitable($bytes=512)
  {
    $curl = $this->manager->get_module('curl');
    return $curl->read($this->value,$bytes) != '';
  }

  function value_of($name)
  {
    if (is_numeric($name) || !isset($this->values[$name])) return $name;
    return $this->values[$name];
  }

  function equal($name) { return $this->value == $this->value_of($name); }
 
  function equals($name) { return $this->equal($name); }
  
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


  function find_in($table, $filter='')
  {
    if ($table == '') {
      $table = $this->table;
      if ($table == '')
        throw new validator_exception('checking for existence with no db table supplied');
      $field = $this->name;
    }
    else {
      list($dbname, $table, $field) = explode_safe('.', $table, 3);
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
    if ($filter[0] == '$') $filter = '';
    if ($filter != '') $filter = " and $filter";
    return $this->sql("select 1 from $table where $field = '$value'$filter");
  }

  function in($table,$filter=null)
  {
    return $this->find_in($table,$filter);
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

  function depends($field, $arg="is(1)")
  {
    $validator = new validator($this->manager);
    $validator->init($this->values, $this->fields, $this->predicates);
    log::debug("DEPENDS $field $arg");
    $validator->valids = $this->valids;
    return $validator->check($field)->is($arg);
  }

  function provided() {
    if ($this->checked_provided) return true;
    $this->checked_provided = true;
    return $this->value != '';
  }

  function blank() {
    return trim($this->value) == '';
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
      else if (file_exists("didi/$file"))
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

    //replace_fields($params, $this->values);
    foreach($params as &$param) {
      $param = trim($param);
      if ($param=='this') $param = $this->name;
      if (array_key_exists($param, $this->values)) {
        $param = $this->values[$param];
        $param = replace_vars ($param, $this->values);
      }
      else if ($param == 'request')
        $param = $this->values;
    }
    return call_user_func_array($function, $params);
  }

  function get_title($code, $field=null) {
    if (is_array($field)) $name = at($field, 'name');
    if ($name == '') $name = ucwords(str_replace ('_', ' ', $code));
    return $name;
  }

  static function is_static_method($name)
  {
    return strpos($name, '::') !== false;
  }

  function check($name, $field=null) {
    $this->field = $field;
    $this->name = $name;
    $this->value = trim(at($this->values,$name, ""));
    $this->checked_provided = $this->failed_auto_provided = false;
    if ($this->prev_name != $name)
      $this->optional = false;
    $this->prev_name = $name;
    if (is_array($field))
      $this->title = $this->get_title($name, $field);
    else if (is_string($field))
      $this->title = $field;
    else
      $this->title = null;
    $this->error = null;
    return $this;
  }

  function get_custom($func) {
    $found = at($this->predicates, $func);
    if ($found) return $found;
    $found = at($this->fields, $func);
    if (!$found) return null;
    return at($found, 'valid');
  }

  function get_internal_function($func)
  {
    if (is_array($func)) return false;
    if ($func[0] == '/') return 'regex';
    list($func) = expand_function($func);
    return method_exists($this, $func)? $func: false;
  }

  static function expand_function($func) {
    if (is_array($func)) [$func] = assoc_element($func);
    $func = trim($func);
    return $func[0] == '/'? ['regex', [$func]]: expand_function($func);
  }

  function is($funcs) {
    // convert one func to an array with on element for ease of processing
    if (!is_array($funcs) || is_assoc($funcs)) {
      $funcs = [$funcs];
    }

    // ensure each func is trimmed
    array_walk($funcs, function(&$func) { 
      if (!is_array($func)) $func = trim($func);  
    });

    log::debug_json("VALIDATE $this->name=$this->value FUNCTIONS: ", $funcs);

    $first = $funcs[0];
    $auto_provided = false;
    // when first function is 'optional', allow empty value
    if ($first== 'optional') {
      if  ($this->value === '') return true;
      array_shift($funcs);
    }

    // automatically prepend 'provided' validator for internal functions
    else if (!$this->checked_provided && !in_array('provided', $funcs, true)) {
      $pos = array_find($funcs, function($func, $key) {
        $func = $this->get_internal_function($func);
        return $func !== false && $func !== 'depends';
      });
      if ($pos !== false) {
        if ($funcs[0] != 'blank') array_splice($funcs, $pos, 0, 'provided');
        $this->checked_provided = $auto_provided = true;
      }
    }

    foreach($funcs as $func) {
      if (is_array($func)) {
        [$func, $args] = assoc_element($func);
        if (!is_array($args) || is_assoc($args)) $args = [$args];
      }
      else {
        if ($func[0] == '/') {
          $args = array($func);
          $func = 'regex';
        }
        else {
          list($func, $args) = validator::expand_function($func);
        }
      }
      $this->update_args($args);
      $module_method  = $this->manager->get_module_method($func);
      if (!is_array($args) && preg_match_all('/[^,]+|\(.*\)/', $args, $matches))
        $args = $matches[0];
      if ($module_method) {
        list($context, $method) = $module_method;
        $result = $context->$method(...$args);
        array_shift($args);
      }
      else if (validator::is_static_method($func)) {
        array_unshift($args, $func);
        $func = 'call';
        $result = call_user_func_array(array($this, $func), $args);
        array_shift($args);
      }
      else if ($func != 'is' && method_exists($this, $func)) {
        $result = call_user_func_array([$this, $func], $args);        
      }
      else if ($func == 'is' || $this->get_custom($func)) {
        array_unshift($args, $func);
        $result = call_user_func_array([$this, 'custom'], $args);
        array_shift($args);
      }
      else if (is_callable($func)) {
        replace_fields($args, $this->values);
        $result = call_user_func_array($func, $args);  
      }
      else {
        throw new validator_exception("validator method $func does not exists!");
      }
      if ($result !== false) {
        if (is_assoc($result)) $this->values = merge_options($this->values, $result);
        continue;
      }
      if ($func == 'depends') return true;
      if ($func == 'provided' && $auto_provided) $this->failed_auto_provided = true;
      $this->has_error = true;
      $this->update_error($func, $args, $result);
      return $result;
    }
    return true;
  }

  function custom($func, ...$args) {
    $predicate = $this->get_custom($func);
    if (!is_array($predicate)) {
      $this->replace_args($predicate, $args, false, true);
      $predicate = replace_vars($predicate, $this->values);
      return $this->is($predicate);
    }
    replace_field_indices($predicate, $args);
    $this->update_args($args);
    $valid = $predicate['valid'];
    if (is_array($valid)) {
      foreach($valid as &$param) {
        if (is_string($param))
          $this->replace_args($param, $args);
      }

      return $this->is($valid);
    }

    $this->replace_args($valid, $args);
    $valid = replace_vars($valid, $predicate);
    $valid = replace_vars($valid, $this->values);
    $this->replace_args($valid, $args, false, true);
    $this->replace_default_args($valid, $args, $predicate);
    // run the validation on the substituted predicate
    
    return $this->is($valid);
  }


  // replace default args not supplied, but set on the predicate 
  function replace_default_args(&$str, $args, $predicate) {
    for($i=sizeof($args)+1; isset($predicate[$i]); ++$i) {
      $str = str_replace('$'.$i, $predicate[$i], $str);
    }
  }

  // replace the string with arguments or with title and values from request
  function replace_args(&$str, $args, $set_titles=false, $force_value=false) {
    $i = 0;
    foreach($args as $arg) {
      ++$i;
      if ($arg == 'this') $arg = $this->name;
      $field = at($this->fields, $arg);
      if ($set_titles && $field)
        $name = $this->get_title($arg, $field);
      else
        $name = $arg;

      $str = str_replace('$'.$i, $name, $str);
      if (is_numeric($arg)) continue;

      $value = at($this->values, $arg);
      if (!is_null($value)) {
        if ($force_value) $value = $arg;
        $str = str_replace('$v'.$i, $value, $str);
      }
    }

    $str = str_replace('$value', $this->value, $str);
    return $str;
  }

  function update_args(&$args)
  {
    foreach($args as &$arg) {
      if (is_array($arg)) continue;
      $arg = trim($arg);
      if ($arg == 'this' || $arg == '$name')
        $arg = $this->name;
      else if ($arg == '$value')
        $arg = $this->value;
     }
  }


  function subst_error(&$error, $predicate, $args, $result) {
    $ignore = array('name');
    if (is_array($result)) $error = replace_vars_except ($error, $result, $ignore);
    $error = str_replace('$value', $this->value, $error);
    $error = replace_vars($error, $predicate);
    $this->replace_args($error, $args, true);
    $this->replace_default_args($error, $args, $predicate);
    $error = replace_vars_except($error, $this->values, $ignore);
    if ($this->title)
      $error = str_replace('$name', $this->title, $error);
  }

  function update_error($func, $args, $result=null)
  {
    $error = null;
    if ($this->failed_auto_provided) $func = 'provided';
    $predicate = $this->get_custom($func);
    $field = at($this->fields, $this->name);
    if (is_array($field) && at($field, 'error')) $predicate = $field;
    if (is_array($predicate)) $error = at($predicate, 'error');
    if (is_string($result)) $error = $result;
    if (is_null($error)) return;

    if (is_array($predicate)) {
      if (is_string($error))
        $this->subst_error($error, $predicate, $args, $result);
      else walk_leaves($error, function(&$value) use ($predicate, $args, $result) {
        $this->subst_error($value, $predicate, $args, $result);
      });
    }
    $this->error = $error;
  }

  function relate_time($format, $relation='') {
    $now = Date($format);
    return $relation==='' || $relation === 'future' && $this->value > $now
      || $relation === 'past' && $this->value < $now;
  }

  function valid()
  {
    return !$this->has_error;
  }

  function sql(...$args) {
    $sql = implode(',', $args);
    $sql = replace_vars($sql, $this->values);
    $sql = str_replace('$name', $this->name, $sql);

    $db = $this->manager->get_module('db');
    if (preg_match('/^(update|insert)\b/ims',$sql)) {
      $db->exec($sql);
      return true;
    }
    $result = $db->read_one($sql, MYSQLI_BOTH);
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

  function ref_list()
  {
    $field = $this->field;
    $this->manager->expand_ref_list($field, $this->name);
    $sql = str_replace('$value', $this->value, $field['valid_sql']);
    return $this->sql($sql);
  }

  function validate($code, $value, $valid)
  {
    $result = $this->check($code, $value)->is($valid);
    if ($result === true) $this->valids[] = $code;
    $this->tested[] = $code;
    return $result;
  }

  function validated()
  {
    return in_array($this->name, $this->valids, true);
  }


  function read_sql($sql) {
    $sql = replace_vars($sql, $this->values, function($v, $k) {
      addslashes($v);
    });
    $result = $this->db->read_one($sql, MYSQLI_ASSOC);
    if ($result)
      $this->values = array_merge($this->values, $result);
    return true;
  }

  function set($values) {
    $this->values = array_merge($this->values, $values);
    return true;
  }

  function checked($code)
  {
    return in_array($code, $this->tested, true);
  }

  function validator() {
    $matches = [];
    preg_match('/^(\w+)(?:\(.*\))?$|(^\/.*\/$)/', $this->value, $matches);
    return preg_match('/^(\w+)(?:\(.*\))?$|(^\/.*\/$)/', $this->value, $matches) &&
      (isset($matches[2]) // regex validator
      || in_array($matches[1], array_keys($this->predicates), true)); // named validator with/without parameters 
  }
}
