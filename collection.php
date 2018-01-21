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
  function __construct($page)
  {
    parent::__construct($page);
    $this->auth = $page->get_module('auth');
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


  function init_filters()
  {
    foreach($this->filters as &$filter) {
      list($name, $value) = assoc_element($filter);
      $attr = $this->init_attr($name);
      if (is_null($value))
        $value = "= '\$$name'";
      else if ($value[0] == '/')
        $value = substr($value,1);
      else if (!is_array($value))
        $value = "= '". addslashes($value). "'";
      $filter = $attr;

      $collection = $filter['collection'];
      $column = $this->get_column_name($attr['name'], $collection);
      if ($column)
        $filter['criteria'] = "`$collection`.$column$value";
    }
  }

  function init_attr($arg)
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
    $attr['aggregated'] = $aggregated = $name[0] == '/';
    if ($aggregated) $this->aggregated = true || $attr['group'];

    $attr['column'] = $this->get_column_name($attr['name'], $attr['collection']);
    return $attr;
  }

  function init_attributes($args, $parent=null, &$index=1, &$aliases=[])
  {
    foreach($args as $arg) {
      list($alias, $name) = assoc_element($arg);
      if (is_array($name)) {
        $aliases[] = $alias;
        $this->init_attributes($name, $alias, $index);
        continue;
      }
      list($alias, $star_args) = $this->expand_star($arg, $aliases);
      if (sizeof($star_args)) {
        $this->init_attributes($star_args, $alias? $alias: $parent, $index, $aliases);
        continue;
      }

      $attr = $this->init_attr($arg);
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
      if (!$order || $attr['aggregated']) continue;
      $collection = $attr['collection'];
      $column = $attr['column'];
      $convert = $attr['convert'];
      $cols[] = "`$collection`.$column$convert $order";
    }
    return sizeof($cols)? "order by ". implode(",", $cols): "";
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

  private function get_filter_joins_sql($processed)
  {
    $main_collection = $this->main_collection;
    $sql = "";
    foreach($this->filters as $filter) {
      $collection = $filter['collection'];
      if (in_array($collection, $processed)) continue;
      $table = $filter['table'];
      $sql .= " join $table `$collection` on "
        . " `$collection`.collection = '$collection' "
        . " and `$collection`.id = `$main_collection`." . $this->get_column_name($collection)
        . $this->get_filter_sql($collection);
      $processed[] = $collection;
    }
    return $sql;
  }

  function create_outer_select($use_custom_filters, $term)
  {
    $prev_parent = null;
    $values = [];
    $siblings = [];
    $count = sizeof($this->attributes);
    $main_collection = $this->main_collection;
    $collections = [$main_collection];
    foreach ($this->attributes as $attr) {
      ++$counted;
      if (!$attr['column']) continue;
      if ($attr['aggregated']) continue;

      $alias = $attr['alias'];
      if (!$this->aggregated && in_array($alias, $this->hidden_columns)) continue;

      $name = $attr['foreign_name']? $attr['foreign_name']: $attr['name'];
      $parent = $attr['parent'];
      if ($parent)
        $alias = "";
      else
        $alias = "`$alias`";
      $collection = $attr['collection'];
      $value =  "`$collection`." . $attr['column'] . " $alias";

      $parent = $attr['parent'];
      if ($prev_parent && $parent != $prev_parent) {
        if (sizeof($siblings)) $values[] = "concat_ws(' ',". implode(',', $siblings) . ") `$prev_parent`";
        $siblings = [$value];
        $prev_parent = $parent;
      }
      else if ($parent)
        $siblings[] = $value;
      else {
        $values[] = $value;
        $siblings = [];
      }
      $prev_parent = $parent;
      if (in_array($collection, $collections)) continue;
      $collections[] = $collection;
      $table = $attr['table'];
      $joins .= " join $table `$collection` on "
        . " `$collection`.collection = '$collection' "
        . " and `$collection`.id = `$main_collection`." . $this->get_column_name($collection)
        . $this->get_filter_sql($collection);

    }
    if (!sizeof($values)) return null;
    if (sizeof($siblings))
      $values[] = "concat_ws(' ',". implode(',', $siblings) . ") `$parent`";

    $values = implode(",\n", $values);

    $joins .= $this->get_filter_joins_sql($collections);
    $sql =  "select $values from $this->main_table `$main_collection` $joins"
      . " where `$main_collection`.collection = '$main_collection'"
      . $this->get_filter_sql($main_collection)
      . $this->get_sort_sql();
    //   . $this->create_filter_joins($this->filters)
    //   . $this->join_custom_filters($use_custom_filters)
    //   . $this->join_foreigners()
    //   . $this->create_search_join($term)
    //   . "$sort_joins where "
    //   . $this->get_attribute_filter($this->main_collection, $this->foreigners);


    return $sql;
  }

  function aggregate($sql)
  {
    $groups = [];
    foreach ($this->attributes as $attr) {
      $alias = $attr['alias'];
      if (in_array($alias, $this->hidden_columns)) continue;
      if ($attr['aggregated'])
        $names[] = substr($attr['name'], 1) . " `$alias`";
      else
        $names[] = "`$alias`";
      if ($attr['group']) $groups[] = $alias;
    }
    $names = implode(",", $names);
    $sql = "select $names from ($sql) tmp";
    if (sizeof($groups))
      $sql .= " group by " . implode(",", $groups);
    return $sql;
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

  function join_custom_filters($use)
  {
    if (!$use) return "";

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
      $filter['value'] = " like '%$term%'";
      $filters[] = $filter;
    }
    return $this->create_filter_joins($filters, "_custom");
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
    else if (is_string($filters)) {
      $this->identifier_filter = $filters;
      $filters = [];
    }
    else if (is_assoc($filters))
      $filters = [$filters];
    $this->main_collection = $collection;
    $this->main_table = $this->get_table($collection);
    $this->filters = $filters;
    $this->offset = $offset;
    $this->size = $size;
    $this->aggregated = false;
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
    $table = $this->get_table($this->collection);
    $fields = $this->get_fields($this->collection);
    if (!$fields) return null;
    $this->init_attributes($args);
    $this->init_filters();
    $sql = $this->create_outer_select($use_custom_filters, $term);
    if (!$sql) return null;
    if ($this->aggregated)
      $sql = $this->aggregate($sql);
    $this->set_limits($sql, $this->offset, $this->size);
    return $this->page->translate_sql($sql);
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

  private function subst_variables($value, $collection=null)
  {
    return preg_replace_callback('/[a-zA-Z_]\w*/', function($matches) use ($collection){
      $match = $matches[0];
      $variable = $this->get_column_name($match, $collection);
      return $variable? $variable: $match;
    }, $value);
  }

  function update()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.update", 3);
    $this->extract_header($args);
    $this->page->parse_delta($args);
    $this->init_filters();
    list($columns, $values) = $this->update_header($args);

    $index = 0;
    $sets = [];
    $collection = $this->main_collection;
    foreach($columns as $column) {
      $value = $this->subst_variables($values[$index++], $collection);
      $sets[] = "$column = $value";
    }
    $sets = implode(',', $sets);
    $sql = "update `$this->main_table` set $sets where collection = '$collection' ". $this->get_filter_sql($collection);
    $this->page->sql_exec($sql);
  }

  function encode_array($value)
  {
    if (!is_array($value)) return $value;
    $encoded = json_encode($value);
    if (!$encoded) return $value;
    return "'". addslashes($encoded) . "'";
  }


  private function update_header($args)
  {
    $collection = $this->main_collection;
    $table = $this->get_table($collection);
    $fields = $this->get_fields($collection);
    $columns = [];
    $values = [];
    $new_names = [];
    $$first_index = null;
    $id_column = null;
    $last_index = sizeof($fields);
    foreach($args as &$arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $index = array_search($name, $fields);
      if ($index === false) {
        $index = $last_index++;
        if (!$first_index) $first_index = $index;
        $new_names[] = $name;
      }
      $column = "v$index";
      $columns[] = $column;
      $values[] = $this->encode_array($value);
      if ($name == 'id') $id_column = $column;
    }

    $db = $this->db;
    if (empty($fields)) {
      $new_names = implode("','", $new_names);
      $new_name_columns = implode(',', $columns);
      $db->exec("insert into `$table` (collection, $new_name_columns) values('$collection-fields', '$new_names')");
    }
    else if (!empty($new_names)) {
      foreach($new_names as $name) {
        $sets[] = "v$first_index = '$name'";
        ++$first_index;
      }
      $sets = implode(',', $sets);
      $db->exec("update `$table` set $sets where collection = '$collection-fields'");
    };
    return [$columns, $values, $id_column];
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 2);
    $collection = $this->main_collection = array_shift($args);
    $table = $this->get_table($collection);
    list($columns, $values, $id_column) = $this->update_header($args);
    $columns = implode(',', $columns);
    $values = implode(',', $values);
    $sql = $this->page->translate_sql("insert into `$table` (collection, $columns) values('$collection', $values)");
    $db = $this->db;
    $db->exec($sql);
    if (is_null($id_column))
      $id = $db->read_one_value("select last_insert_id()");
    else
      $id = $db->read_one_value("select $id_column from `$table` where id = last_insert_id()");
    return ["new_${collection}_id"=>$id];
  }


  function search()
  {
    $args = page::parse_args(func_get_args());
    $last = array_slice($args,-1)[0];
    if (is_string($last))
      array_splice($args, -1, 1, explode(',', $last));
    $sql = $this->read($args, false, $this->page->request['term']);
    return ['data'=>$this->db->read($sql, MYSQLI_NUM)];
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

  private function get_fields($collection=null, $key=null, $exclusions=[])
  {
    if (is_null($collection)) $collection = $this->main_collection;
    if (!empty($this->fields[$collection]))
      return array_exclude($this->fields[$collection], $exclusions);
    $table = $this->get_table($collection);
    $sql = "select * from `$table` where collection = ";
    $names = $this->db->read_one("$sql '$collection-fields'", MYSQLI_NUM);
    if (sizeof($names) < 4)
      $names = [];
    else
      $names = array_exclude(array_slice($names, 3), [null]);
    $this->fields[$collection] = $names;
    if (empty($names) || is_null($key)) return $names;
    $data = [];
    if (!is_null($key)) $data = $this->db->read_column("$sql '$collection' and id = '$key' ");
    $i = 0;
    $result = [];
    foreach($names as $name) {
      if (!is_null($data[$i])) $result[] = $name;
      ++$i;
    }
    return $result;
  }

  private function get_column_name($field_name, $collection=null)
  {
    if ($field_name == 'create_time')
      return $field_name;
    $fields = $this->get_fields($collection);
    $index = array_search($field_name, $fields);
    if ($index !== false) return "v$index";
    if ($field_name == 'id') return $field_name;
    return null;
  }

  function fields($collection, $key)
  {
    $exclusions = array_slice(func_get_args(), 2);
    $data = $this->get_fields($collection, $key, $exclusions);
    return ['data'=>$data, 'count'=>sizeof($data)];
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
