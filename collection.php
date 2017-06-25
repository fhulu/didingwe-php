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

      $filter['value'] = $value;
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
      if (!isset($foreign_name)) {
        $foreign_name = $foreign_collection;
        $foreign_collection = $local_name;
      }
      $name = "$foreign_collection.$foreign_name";
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

  function create_sort_joins()
  {
    $joins_sql = "";
    $order_sql = [];
    foreach($this->attributes  as $attr) {
      if (!$attr['sort_order'] || $attr['foreign_name'] || $attr['aggregated']) continue;
      $sorter = "`sorter_" . $attr['alias']. "`";
      $name = $attr['name'];
      $joins_sql .= " left join `$this->main_table` $sorter"
          . " on $sorter.collection = '$this->main_collection' and `$this->main_collection`.identifier = $sorter.identifier "
          . " and $sorter.attribute = '$name'";
      $order_sql[] = " $sorter " . $attr['sort_order'];
      $fields[] = "$sorter.value $sorter";
    }
    if (sizeof($order_sql))
      $order_sql = "order by ". implode(",", $order_sql);
    else
      $order_sql = "";
    return [$fields, $joins_sql, $order_sql];
  }

  function create_filter_joins($filters, $suffix="")
  {
    $sql = "";
    foreach($filters as &$filter) {
      $name = $filter['name'];
      $alias = "filter_$name".$suffix;
      $value = $filter['value'];
      $table = $filter['table'];
      $collection = $filter['collection'];
      $local_name = $filter['local_name'];
      if (is_null($local_name)) {
        $sql .= " join `$table` `$alias` on `$alias`.collection = '$collection'"
        . " and `$collection`.identifier = `$alias`.identifier";
        if ($name == 'identifier')
          $sql .= " and `$alias`.identifier $value";
        else
          $sql .= " and `$alias`.attribute = '$name' and `$alias`.value $value ";
      }
      else {
        $foreign_name = $filter['foreign_name'];
        $sql .= " join `$this->main_table` `main_$alias` on `main_$alias`.collection = `$this->main_collection`.collection"
          . " and `main_$alias`.identifier = `$this->main_collection`.identifier and `main_$alias`.attribute = '$local_name'"
          . " join `$table` `$alias` on `$alias`.collection = '$collection' and `$alias`.identifier = `main_$alias`.value"
          . " and `$alias`.attribute = '$foreign_name' and `$alias`.value $value ";
      }
    }

    return $sql;
  }

  function create_search_join($term)
  {
    if ($term == "") return "";

    $sql = "";
    $searched = [];
    foreach($this->attributes  as $attr) {
      $collection = $attr['collection'];
      if (in_array($collection, $searched)) continue;
      $searched[] = $collection;
      $table = $attr['table'];
      $alias = "`search_$collection`";
      $sql .= " join `$table` $alias "
          . " on `$collection`.identifier = $alias.identifier "
          . " and $alias.value like '%$term%'"
          . " and " .  $this->get_attribute_filter($collection, [], $alias);

    }
    log::debug("joins $sql");
    return $sql;
  }

  function join_foreigners()
  {
    $sql = "";
    $joined = [];
    $main = $this->main_collection;
    foreach($this->attributes as $attr) {
      $foreign = $attr['foreign_name'];
      if (!$foreign) continue;
      $collection = $attr['collection'];
      if (in_array($collection, $joined)) continue;
      $table = $attr['table'];
      $local = $attr['local_name'];
      $sql .= " left join `$table` `$collection` "
            . " on  `$collection`.identifier = `$main`.value and `$main`.attribute = '$local' "
            . " and " .  $this->get_attribute_filter($collection);
      $joined[] = $collection;
    }
    return $sql;
  }

  function create_inner_select($sort_fields)
  {
    $main = "$this->main_collection";
    $sql = "select `$main`.identifier, `$main`.attribute `$main.attribute`, `$main`.value `$main.value`";
    $selected = [];
    foreach($this->attributes as $attr) {
      if (!$attr['foreign_name']) continue;
      $collection = $attr['collection'];
      if (in_array($collection, $selected)) continue;
      $selected[] = $collection;
      $sql .= ", `$collection`.attribute `$collection.attribute`, `$collection`.value `$collection.value`";
    }
    if ($sort_fields)
      $sql .= "," . implode(",", $sort_fields);
    return $sql;
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



  function create_outer_select($use_custom_filters, $term)
  {
    $prev_parent = null;
    $values = [];
    $siblings = [];
    $count = sizeof($this->attributes);
    foreach ($this->attributes as $attr) {
      ++$counted;
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
      if ($name == 'identifier')
        $value = "identifier $alias";
      else
        $value = "max(case when `$collection.attribute`='$name' then `$collection.value` end) $alias";

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
    }
    if (sizeof($siblings))
      $values[] = "concat_ws(' ',". implode(',', $siblings) . ") `$parent`";

    $values = implode(",\n", $values);
    list($sort_fields, $sort_joins, $order_sql) = $this->create_sort_joins();
    $sql =  "select $values from ("
      . $this->create_inner_select($sort_fields)
      . " from `$this->main_table` `$this->main_collection` "
      . $this->create_filter_joins($this->filters)
      . $this->join_custom_filters($use_custom_filters)
      . $this->join_foreigners()
      . $this->create_search_join($term)
      . "$sort_joins where "
      . $this->get_attribute_filter($this->main_collection, $this->foreigners);

    if ($this->identifier_filter)
      $sql .= " and `$this->main_collection`.identifier = '$this->identifier_filter'";

    return $sql . " $order_sql) tmp group by identifier $order_sql";
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
    if (is_string($filters)) {
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
  }


  function expand_args(&$args)
  {
    $size = sizeof($args);
    switch($size) {
      case 0: $args = [$this->page->path[sizeof($this->page->path)-2], [], "identifier", "name asc"]; break;
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
    $this->init_attributes($args);
    $this->init_filters();
    $sql = $this->create_outer_select($use_custom_filters, $term);
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
    return [$name=>$this->db->read_column($sql)];
  }

  function data()
  {
    $sql = $this->read(func_get_args(), true);
    if ($this->page->foreach)
      return $this->db->read($sql, MYSQLI_ASSOC);
    return ['data'=>$this->db->read($sql, MYSQLI_NUM), 'count'=>$this->db->row_count()];
  }

  function update()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.update", 3);
    $this->extract_header($args);
    $this->page->parse_delta($args);
    $this->init_filters();
    $joins = $this->create_filter_joins($this->filters);
    $table = "`$this->main_table`";
    $collection = $this->main_collection;
    $where = " where `$collection`.collection = '$collection' and `$collection`.version = 0 ";
    if ($this->identifier_filter)
      $where .= " and `$collection`.identifier = '$this->identifier_filter'";
    foreach($args as $arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      if ($name == 'identifier') {
        $attribute = $name;
        $condition = "";
      }
      else {
        $attribute = 'value';
        $condition = " and `$collection`.attribute = '$name'";
      }
      $attribute = $name== 'identifier'? $name: 'value';
      $updated = $this->page->sql_exec("update $table `$collection` $joins set `$collection`.$attribute = $value $where $condition");
      if ($updated) continue;
      if (!$this->identifier_filter) continue;
      $this->page->sql_exec("insert into $table (collection,identifier,attribute,value)
        select '$collection', '$this->identifier_filter', '$name', $value from dual where not exists (
          select 1 from $table `$collection` $where $condition)");
    }
  }

  function encode_array($value)
  {
    if (!is_array($value)) return $value;
    $encoded = json_encode($value);
    if (!$encoded) return $value;
    return "'". addslashes($encoded) . "'";
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 3);
    list($collection, $identifier) = array_splice($args, 0, 2);
    list($collection) = assoc_element($collection);
    $table = $this->get_table($collection);
    $sql = "insert into `$table`(version,collection,identifier,attribute,value) values";
    $identifier_func = "last_insert_id()";
    foreach($args as &$arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $name = addslashes($name);
      if ($identifier[0] == '/') {
        $identifier_func = substr($identifier,1);
        $identifier = "";
      }
      $value = $this->encode_array($value);
      $this->page->sql_exec($sql . "(0,'$collection', '$identifier','$name',$value)");
      if ($identifier) continue;
      list($identifier,$last_id) = $this->db->read_one("select $identifier_func, last_insert_id()");;
      $this->db->exec("update `$table` set identifier='$identifier' where id = $last_id");
    }
    return ["new_${collection}_id"=>$identifier];
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
      $args[1] = ['identifier'=>$args[1]];
      $args[] = 'identifier';
    }
    else {
      $filters = [];
      for ($i=1; $i < $size; $i+=2) {
        $filters[] = [$args[$i]=>$args[$i+1]];
      }
      $args = [$args[0], $filters, 'identifier' ];
    }
    $sql = $this->read($args);
    return $this->db->exists($sql);
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

  private function get_fields($collection, $key=null, $exclusions=null)
  {
    $table = $this->get_table($collection);
    $sql = "select distinct attribute from `$table` where collection = '$collection'";
    if (!is_null($key)) $sql .= " and identifier = '$key'";
    if (!is_null($exclusions)) $sql .= " and attribute not in ('" . implode("','", $exclusions) ."')";
    return $this->db->read_column($sql);
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
