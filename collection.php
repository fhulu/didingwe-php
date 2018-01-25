<?php
require_once("module.php");

class collection extends module
{
  var $tables;
  var $hidden_columns;
  var $combined_columns;
  var $star_columns;
  var $columns;
  var $sort_columns;
  static $sys_columns = ['partner', 'user', 'access', 'active', 'create_time'];
  static $sys_collections = ['partner', 'user', 'session', 'audit'];
  function __construct($page)
  {
    parent::__construct($page);
    $this->auth = $page->get_module('auth');
    $this->sys_fields = array_merge($this->auth->get_owner(), ['access'=>777, 'active'=>'1', 'create_time'=>'now()']);
    $this->read_tables();
    $this->columns = [];
    $this->hidden_columns = [];
    $this->combined_columns = [];
    $this->star_columns = [];
    $this->sort_columns = [];
    $this->dynamic_sorting = true;
    $this->foreigners = [];
    $this->sorts = [];
  }

  function read_tables()
  {
    global $config;
    foreach($config['collections'] as $table=>$collections) {
      if ($table=='default')
        $this->tables['default'] = $collections;
      else foreach($collections as $collection) {
        $this->tables[$collection] = $table;
      }
    }
  }
  function get_table($collection)
  {
      $table = $this->tables[$collection];
      return $table? $table: $this->tables['default'];
  }

  function extract_grouping(&$attr)
  {
    $matches = [];
    if (!preg_match('/^(\w[\w\.]*)(\s*\+\d+)?(\s+group\s*)?(\s+asc\s*|\s+desc\s*)?$/', $attr['alias'], $matches)) return;
    if (!$attr['aliased'])
      $attr['name'] = $attr['alias'] = $matches[1];
    else
      $attr['alias'] = $matches[1];

    if ($matches[3]) $attr['group'] = true;
    if (!$matches[4]) retjurn;

    $attr['sort_order'] = $matches[4];
    if ($matches[2]) $attr['sort_convert'] = $matches[2];
  }


  function init_filters($partner)
  {
    foreach($this->filters as &$filter) {
      list($name, $value) = assoc_element($filter);
      $attr = $this->init_attr($name, $partner);
      if (in_array($name, collection::$sys_columns) && is_null($value)) {
        $value = $this->sys_fields[$name];
        $value = " = if('\$$name'='',$value, '\$$name')";
      }
      else if (is_null($value))
        $value = " = '\$$name'";
      else if ($value[0] == '/')
        $value = substr($value,1);
      else if (!is_array($value))
        $value = " = '". addslashes($value). "'";
      $filter = $attr;

      $collection = $filter['collection'];
      $column = $this->get_column_name($attr['name'], $collection, $partner);
      if ($column)
        $filter['criteria'] = "`$collection`.$column $value";
    }
  }

  function init_attr($arg, $partner)
  {
    if (is_string($arg))
      $name = $alias = $arg;
    else if (($aliased = is_assoc($arg))) {
      list($alias, $name) = assoc_element($arg);
      if (is_assoc($name)) $name = $alias;
    }
    list($local_name, $foreign_collection, $foreign_name) = explode('.', $name);
    if (isset($foreign_collection)) {
      $name = $foreign_name;
      if (!isset($foreign_name)) {
        $name = $foreign_name = $foreign_collection;
        $foreign_collection = $local_name;
      }
      $attr['local_name'] = $local_name;
      $attr['foreign_name'] = $foreign_name;
      $attr['table'] = $this->get_table($foreign_collection);
      $attr['collection'] = $foreign_collection;
      if (!in_array($foreign_name, $this->foreigners))
        $this->foreigners[] = $foreign_collection;
      if (!$aliased) $alias = $foreign_name;
    }
    else {
      $attr['table'] = $this->main_table;
      $attr['collection'] = $this->main_collection;
    }

    $attr['name'] = $name;
    $attr['alias'] = $alias;
    $attr['aliased'] = $name != $alias;
    $this->extract_grouping($attr);

    $name = $attr['name'];
    if ($name == '')
      $attr['column'] = "''";
    else
      $attr['column'] = $this->get_column_name($name, $attr['collection'], $partner);
    $attr['derived'] = $name[0] == '/' || $name == '';
    return $attr;
  }

  function init_attributes($args, $partner, $parent=null, &$index=1, &$aliases=[])
  {
    foreach($args as $arg) {
      list($alias, $name) = assoc_element($arg);
      if (is_array($name)) {
        $aliases[] = $alias;
        $this->init_attributes($name, $partner,  $alias, $index);
        continue;
      }
      list($alias, $star_args) = $this->expand_star($arg, $aliases);
      if (sizeof($star_args)) {
        $this->init_attributes($star_args, $partner, $alias? $alias: $parent, $index, $aliases);
        continue;
      }

      $attr = $this->init_attr($arg, $partner);
      if (in_array($attr['alias'], $this->sort_columns) || in_array("$index", $this->sort_columns)) {
        if (!$attr['sort_order']) $attr['sort_order'] = $this->page->request['sort_order'];
      }
      ++$index;
      $attr['parent'] = $parent;
      $this->attributes[] = $attr;
      $aliases[] = $attr['alias'];
    }
  }

  function get_sort_sql()
  {
    $cols = [];
    foreach($this->attributes  as $attr) {
      $order = $attr['sort_order'];
      if (!$order) continue;
      $collection = $attr['collection'];
      $column = $attr['column'];
      $convert = $attr['convert'];
      $cols[] = "`$collection`.$column$convert $order";
    }
    return sizeof($cols)? " order by ". implode(",", $cols): "";
  }


  function get_attribute_filter($collection, $foreigners=[], $alias="")
  {
    $names = [];
    $set_names = function($collection, $foreign) use (&$names) {
      foreach($this->attributes as $attr) {
        if ($attr['aggregated'] || $attr['collection'] != $collection || $attr['name'] == 'identifier') continue;
        if ($foreign)
          $name = $attr['local_name'];
        else if ($attr['foreign_name'])
          $name = $attr['foreign_name'];
        else
          $name = $attr['name'];
        $names[] = "'$name'";
      }
    };
    $set_names($collection, false);
    foreach($foreigners as $foreigner) {
      $set_names($foreigner, true);
    }
    if (!$alias) $alias = "`$collection`";
    $sql = "$alias.collection = '$collection' ";
    if (empty($names)) return $sql;

    $names = implode(",", $names);
    return $sql . " and $alias.attribute in ($names)";
  }

  private function get_filter_sql($collection)
  {
    $criteria = [];
    foreach($this->filters as $filter) {
      if ($collection != $filter['collection'] || !$filter['criteria']) continue;
      $criteria[] = $filter['criteria'];
    }
    if (empty($criteria)) return "";
    return " and " . implode(" and ", $criteria);
  }

  private function get_filter_joins_sql($processed, $partner)
  {
    $main_collection = $this->main_collection;
    $sql = "";
    foreach($this->filters as $filter) {
      $collection = $filter['collection'];
      if (in_array($collection, $processed)) continue;
      $table = $filter['table'];
      $sql .= " join $table `$collection` on "
        . " `$collection`.collection = '$collection' "
        . " and `$collection`.id = `$main_collection`." . $this->get_column_name($collection, $main_collection, $partner)
        . $this->get_filter_sql($collection);
      $processed[] = $collection;
    }
    return $sql;
  }

  function create_outer_select($use_custom_filters, $term, $partner)
  {
    $prev_parent = null;
    $values = [];
    $siblings = [];
    $count = sizeof($this->attributes);
    $main_collection = $this->main_collection;
    $collections = [$main_collection];
    $likes = [];
    foreach ($this->attributes as $attr) {
      ++$counted;

      $alias = $attr['alias'];
      if (in_array($alias, $this->hidden_columns)) continue;

      $name = $attr['foreign_name']? $attr['foreign_name']: $attr['name'];
      $parent = $attr['parent'];
      if ($parent)
        $alias = "";
      else
        $alias = "`$alias`";
      $collection = $attr['collection'];
        $value = $attr['column'];
      if (!$attr['derived'])
        $value =  "`$collection`.$value";

      $parent = $attr['parent'];
      if ($prev_parent && $parent != $prev_parent) {
        if (sizeof($siblings)) $values[] = "concat_ws(' ',". implode(',', $siblings) . ") `$prev_parent`";
        $siblings = [$value];
        $prev_parent = $parent;
      }
      else if ($parent)
        $siblings[] = $value;
      else {
        $values[] = "$value $alias";
        $siblings = [];
      }
      if ($term)
        $likes[] = "$value like '%$term%'";

      $prev_parent = $parent;
      if (in_array($collection, $collections)) continue;
      $collections[] = $collection;
      $table = $attr['table'];
      $local_name = $attr['local_name'];
      $id_column = $this->get_column_name('id', $collection, $partner);
      $joins .= " join $table `$collection` on "
        . " `$collection`.collection = '$collection' "
        . " and `$collection`.$id_column = `$main_collection`." . $this->get_column_name($local_name, $main_collection, $partner)
        . $this->get_filter_sql($collection);

    }
    if (!sizeof($values)) return null;
    if (sizeof($siblings))
      $values[] = "concat_ws(' ',". implode(',', $siblings) . ") `$parent`";

    $values = implode(",\n", $values);

    if ($use_custom_filters) $this->update_custom_filters($this->filters);
    $joins .= $this->get_filter_joins_sql($collections, $partner);
    $sql =  "select $values from $this->main_table `$main_collection` $joins"
      . " where `$main_collection`.collection = '$main_collection'"
      . $this->get_filter_sql($main_collection, $use_custom_filters);
    if (sizeof($likes))
      $sql .= " and (" . implode(" or ", $likes) . ")";
    return $sql . $this->get_sort_sql();
  }

  function expand_star($arg, $ignore)
  {
    $matches = [];
    list($alias, $name) = assoc_element($arg);
    if (!$name) {
      $name = $alias;
      $alias = null;
    }
    if (!is_string($name) || !preg_match('/^(?:(\w+)\.)?\*/', $name, $matches))
      return [];
    $collection = $this->main_collection;
    if (($foreign=$matches[1]))
      $collection = $matches[1];

    if (empty($this->columns[$collection]))
      $this->columns[$collection] = $this->get_fields($collection);

    $expanded = array_filter($this->columns[$collection], function($element) use(&$ignore) {
      return !in_array($element, $ignore);
    });
    if ($foreign) {
      $expanded = array_map(function($v) use ($collection) {
        return "$collection.$v";
      }, $expanded);
    }
    return [$alias, $expanded];
  }

  function update_custom_filters()
  {
    $index = -1;
    $request = $this->page->request;
    $filters = [];
    foreach($this->attributes as $attr) {
      $alias = $attr['alias'];
      if (in_array($alias, $this->hidden_columns, true)) continue;
      ++$index;
      $term = $request["f$index"];
      if ($term=='') continue;
      $filter = $attr;
      $filter['criteria'] = $attr['column'] . " like '%$term%'";
      $this->filters[] = $filter;
    }
  }

  function extract_header(&$args)
  {
    if (is_numeric($args[0])) {
      if (is_numeric($args[1]))
        list($offset, $size) = array_splice($args, 0, 2);
      else
        $size = array_shift($args);
    }
    else {
      $offset = $this->page->request['offset'];
      $size = $this->page->request['size'];
    }
    list($collection, $filters) = array_splice($args, 0, 2);
    list($collection) = assoc_element($collection);
    $this->identifier_filter = null;
    if ($filters == '')
      $filters = [];
    else if (is_string($filters) || is_numeric($filters))
      $filters = [ ['id'=>$filters] ] ;
    else if (is_assoc($filters))
      $filters = [$filters];
    $this->main_collection = $collection;
    $this->main_table = $this->get_table($collection);
    $this->filters = $filters;
    $this->offset = $offset;
    $this->size = $size;
    $this->attributes = [];
    $this->fields = [];
  }


  function expand_args(&$args)
  {
    $size = sizeof($args);
    switch($size) {
      case 0: $args = [$this->page->path[sizeof($this->page->path)-2], [], "id", "name asc"]; break;
      case 1: $args = [$args[0],[],'*']; break;
      case 2: $args[] = '*';
    }
    return $size;
  }

  function set_limits(&$sql, $offset, $size)
  {
    if (!is_null($offset) && !is_null($size))
      $sql .= " limit $offset, $size";
    else if (!is_null($size))
      $sql .= " limit $size";
  }

  function read($args, $use_custom_filters=false, $term="")
  {
    $args = page::parse_args($args);
    $this->expand_args($args);
    $this->extract_header($args);
    $partner = $this->get_header_partner($this->main_collection, $args);
    $fields = $this->get_fields($this->main_collection, $partner);
    if (empty($fields)) return null;
    $this->init_attributes($args, $partner);
    $this->init_filters($partner);
    $sql = $this->create_outer_select($use_custom_filters, $term, $partner);
    if (!$sql) return null;
    $this->set_limits($sql, $this->offset, $this->size);
    return $this->page->translate_sql($sql);
  }

  private function get_header_partner($collection, $args)
  {
    if (in_array($collection, collection::$sys_collections)) return 0;
    foreach ($args as $arg) {
      list($name, $value) = assoc_element($arg);
      if ($name != 'partner') continue;
      return is_null($value)? '$partner': $value;
    }
    return $this->sys_fields['partner'];
  }

  function values()
  {
    $a = func_get_args();
    if (!is_numeric($a[0])) array_splice($a, 0, 0, 1);
    $this->dynamic_sorting = false;
    $sql = $this->read($a);
    if (!$sql) return [];
    $this->dynamic_sorting = true;
    $result = $this->db->read($sql, MYSQLI_ASSOC);
    if ($result) $result = $result[0];
    return $result;
  }

  function listing()
  {
    $args = func_get_args();
    page::verify_args($args, "collection.list", 3);
    $last_arg = array_slice($args, -1)[0];
    list($name) = $this->page->get_sql_pair($last_arg);
    $sql = $this->read($args);
    if (!$sql) return [];
    return [$name=>$this->db->read_column($sql)];
  }

  function data()
  {
    $sql = $this->read(func_get_args(), true);
    if (!$sql) return ['data'=>[], 'count'=>0];
    if ($this->page->foreach)
      return $this->db->read($sql, MYSQLI_ASSOC);
    return ['data'=>$this->db->read($sql, MYSQLI_NUM), 'count'=>$this->db->row_count()];
  }

  private function subst_variables($value, $collection, $partner)
  {
    return preg_replace_callback('/("[^"]*")|(\'[^\']*\')|(\$\w*+)|(\w+\()|(\d+)|(\w+)/', function($matches) use ($collection, $partner){
      $full_match = array_shift($matches);
      if (count($matches) < 6) return $full_match;
      $variable = array_pop($matches);
      $column = $this->get_column_name($variable, $collection, $partner);
      if (!is_null($column)) $variable = "`$collection`.$column";
      return implode($matches).$variable;
    }, $value);
  }

  function update()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.update", 3);
    $this->extract_header($args);
    $this->page->parse_delta($args);
    $collection = $this->main_collection;
    $partner = $this->update_header($collection, $args);
    $this->init_filters($partner);
    $sets = [];
    foreach($args as $arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $column = $this->get_column_name($name, $collection, $partner);
      $value = $this->subst_variables($value, $collection, $partner);
      $sets[] = "$column = $value";
    }
    $sets = implode(',', $sets);
    $sql = "update `$this->main_table` `$collection` set $sets where collection = '$collection' ". $this->get_filter_sql($collection);
    $this->page->sql_exec($sql);
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 2);
    $collection = $this->main_collection = array_shift($args);
    $partner = $this->update_header($collection, $args);
    $columns = collection::$sys_columns;
    $values = array_values($this->sys_fields);
    $custom_id = false;
    foreach ($args as $arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $column = $this->get_column_name($name, $collection, $partner);
      $value = json_encode_array($value);
      $sys_index = array_search($name, collection::$sys_columns);
      if ($sys_index !== false) {
        $values[$sys_index] = $value;
        continue;
      }
      $columns[] = $column;
      $values[] = $value;
      if (in_array($name,  ['id', 'identifier']))
        $custom_id = $column;

    }
    $columns = implode(',', $columns);
    $values = implode(',', $values);
    $table = $this->get_table($collection);
    $sql = $this->page->translate_sql("insert into `$table` (collection, $columns) values('$collection', $values)");
    $new_id = $this->db->insert($sql);
    if ($custom_id)
      $id = $this->db->read_one_value("select $custom_id from `$table` where id = last_insert_id()");
    else
      $id = $new_id;
    return ["new_${collection}_id"=>$id];
  }


  function search()
  {
    $args = page::parse_args(func_get_args());
    $last = array_slice($args,-1)[0];
    if (is_string($last))
      array_splice($args, -1, 1, explode(',', $last));
    $sql = $this->read($args, false, $this->page->request['term']);
    $result = $sql? $this->db->read($sql, MYSQLI_NUM): [];
    return ['data'=> $result];
  }

  function exists()
  {
    $args = func_get_args();
    $size = sizeof($args);
    if ($size < 2) return false;
    if ($size == 2) {
      $args[1] = ['id'=>$args[1]];
      $args[] = 'id';
    }
    else {
      $filters = [];
      for ($i=1; $i < $size; $i+=2) {
        $filters[] = [$args[$i]=>$args[$i+1]];
      }
      $args = [$args[0], $filters, 'id' ];
    }
    $sql = $this->read($args);
    return $sql? $this->db->exists($sql): false;
  }

  function unique()
  {
    return !call_user_func_array([$this, "exists"], func_get_args());
  }

  function scroll()
  {
    $request = $this->page->request;
    $page_num = $request['page_num'];
    $page_size = $request['page_size'];
    $args = array_merge([$page_num*$page_size, $page_size], func_get_args());
    return call_user_func_array([$this, "data"], $args);
  }

  private function get_fields($collection, $partner, $exclusions=[])
  {
    if (!empty($this->fields["$collection@$partner"]))
      return array_exclude($this->fields["$collection@$partner"], $exclusions);
    $table = $this->get_table($collection);
    $sql = "select * from `$table` where partner = $partner and collection = '$collection-fields'";
    if ($partner)
      $sql .= " union select * from `$table` where partner = 0 and collection = '$collection-fields'";
    $names = $this->db->read_one($this->page->translate_sql($sql), MYSQLI_NUM);
    if (empty($names)) return $names;
    $names = array_exclude($names, [null]);
    array_splice($names, 0, sizeof(collection::$sys_columns)+2, collection::$sys_columns);
    $this->fields["$collection@$partner"] = $names;
    return $names;
  }

  private function get_column_name($field_name, $collection, $partner)
  {
    if (in_array($field_name, collection::$sys_columns))
      return $field_name;
    if ($field_name[0] == '/')
      return $this->subst_variables(substr($field_name,1), $collection, $partner);

    $fields = $this->get_fields($collection, $partner);
    $index = array_search($field_name, $fields);
    if ($index !== false) return "v".($index-sizeof(collection::$sys_columns));
    if (in_array($field_name, ['id','identifier'])) return 'id';
    return null;
  }

  private function create_header($collection, $args, $partner)
  {
    $index = 0;
    $columns = $sys_names = $fields = collection::$sys_columns;
    $values = array_values($this->sys_fields);
    foreach($args as $arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $sys_index = array_search($name, $sys_names);
      if ($sys_index !== false) {
        if (!in_array($collection, collection::$sys_collections)) $values[$sys_index] = $value;
        continue;
      }
      $columns[] = "v$index";
      $values[] = "'$name'";
      $fields[] = $name;
      ++$index;
    }
    $result = [$columns, $values];
    $columns = implode(",", $columns);
    $values = implode(",", $values);
    $table = $this->get_table($collection);
    $this->page->sql_exec("insert into `$table` (collection, $columns) values('$collection-fields', $values)");
    $this->fields["$collection@$partner"] = $fields;
  }

  private function update_header($collection, $args)
  {
    $partner = $this->get_header_partner($collection, $args);
    $fields = $this->get_fields($collection, $partner);
    $columns = $values = [];
    $count = count($fields);
    if (!$count) {
      $this->create_header($collection, $args, $partner);
      return $partner;
    }

    $sets = [];
    $count -= count(collection::$sys_columns);
    foreach($args as &$arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $column = $this->get_column_name($name, $collection, $partner);
      if ($column) continue;
      $fields[] = $name;
      $sets[] = "v$count='$name'";
      ++$count;
    }
    if (empty($sets)) return $partner;
    $sets = implode(',', $sets);
    $table = $this->get_table($collection);
    $this->page->sql_exec("update `$table` set $sets where collection = '$collection-fields' and partner = $partner");
    $this->fields["$collection@$partner"] = $fields;
    return $partner;
  }

  function fields($collection, $key)
  {
    // $exclusions = array_slice(func_get_args(), 2);
    // $data = $this->get_fields($collection, $key, $exclusions);
    // return ['data'=>$data, 'count'=>sizeof($data)];
  }

  function hide()
  {
    $this->hidden_columns = array_merge($this->hidden_columns, func_get_args());
  }

  function unhide()
  {
    $args = func_get_args();
    if (sizeof($args) == 0)
      $this->hidden_columns = [];
    else
      $this->hidden_columns = array_diff($this->hidden_columns, $args);
  }

  function combine()
  {
    $this->combined_columns = array_merge($this->combined_columns, func_get_args());
  }

  function sort_on()
  {
    foreach(func_get_args() as $arg) {
      $columns[] = $this->page->request[$arg];
    }
    $this->sort_columns = array_merge($this->sort_columns, $columns);
  }
}
