<?php

class collection
{
  var $page;
  var $db;
  var $tables;
  function __construct($page)
  {
    $this->page = $page;
    $this->db = $page->db;
    $this->read_tables();
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
  function update_sort_order(&$sorting, &$name, &$alias)
  {
    $matches = [];
    if (!preg_match('/(\w+) (asc|desc)$/', $alias, $matches)) return;
    if ($name == $alias)
      $name = $alias = $matches[1];
    else
      $alias = $matches[1];
    $sorting[] = "$alias $matches[2]";
  }

  function get_name_alias($arg)
  {
    if (is_string($arg))
      $name = $alias = $arg;
    else if (is_assoc($arg)) {
      list($alias, $name) = assoc_element($arg);
      if (is_assoc($name)) $name = $alias;
    }
    return [$name,$alias];
  }

  function get_selection($table, $args, &$where, &$sorting, &$has_primary_filter)
  {
    $identifier = "";
    $primary = [];
    $sub_fields = [];
    $index = 0;
    $identifier_pos = 0;
    foreach($args as &$arg) {
      list($name,$alias) = $this->get_name_alias($arg);
      if ($alias[0] == '/') continue;
      $this->update_sort_order($sorting, $name, $alias);

      if ($name == 'identifier') {
        $identifier = "m.$name $alias";
        $identifier_pos = $index;
      }
      else if (empty($primary) && strpos($name, '.') === false)
        $primary = [$name, $alias];
      else
        $sub_fields[] = [$name,$alias];
      ++$index;
    }
    $sub_queries = $this->get_subqueries($table, $sub_fields);
    $selection = [];
    $has_primary_filter = !empty($primary);
    if ($has_primary_filter) {
      $where .= " and m.attribute  = '$primary[0]'";
      $selection[] = "m.value $primary[1]";
    }
    $selection[] = $sub_queries;
    if ($identifier)
      array_splice($selection, $identifier_pos, 0, [$identifier]);
    return implode(",", array_filter($selection));
  }

  function get_subqueries($table, $fields)
  {
    global $config;
    $queries = [];
    foreach($fields as $name_alias) {
      list($name,$alias) = $name_alias;
      $name = addslashes($name);
      $alias = addslashes($alias);
      if ($alias[0] == '/') continue;
      list($foreign_key, $foreign_name) = explode('.', $name);
      $value = "value";
      if (!isset($foreign_name))
        $query = "select group_concat($value) from $table where collection = m.collection
             and version <= m.version and identifier=m.identifier and attribute = '$name'";

      else {
        if ($alias == $name) $alias = $foreign_name;
        $name = $foreign_name;
        $sub_table = $this->get_table($foreign_key);
        $query =
          "select group_concat($value) from $sub_table where collection = '$foreign_key' and version <= m.version and attribute = '$foreign_name'
            and identifier in (
              select value from $table where collection = m.collection and version <= m.version
                and identifier=m.identifier and attribute = '$foreign_key' order by version desc)";
      }
      $queries[] = "($query order by version desc limit 1) $alias";
    }
    return implode(",", $queries);
  }


  function get_joins($table, $filters, &$where="", $has_primary_filter=false)
  {
    $index = 0;
    $joins = "";
    if (empty($filters)) return "";
    if (!is_array($filters) || is_assoc($filters)) $filters = [$filters];

    // use first filter in 'where' when don't have primary filter
    if (!$has_primary_filter) {
      $first_filter = array_shift($filters);
      list($name,$value) = $this->page->get_sql_pair($first_filter);
      $where .= " and m.attribute = '$name' and m.value = $value";
    }
    // create joins for each filter
    foreach($filters as $filter) {
      ++$index;
      list($name,$value) = $this->page->get_sql_pair($filter);
      if ($name == 'identifier') {
        $where .= " and m.$name = $value";
        continue;
      }

      $operator = "";
      if (ctype_alnum($value[0]) || $value[0] == "'")
        $operator = " = ";
      $joins .= " join $table m$index on m$index.collection = m.collection
          and m$index.version <= m.version and m$index.identifier=m.identifier
          and m$index.attribute = '$name' and m$index.value $operator $value";
    }
    return $joins;
  }

  function expand_star($collection, &$args)
  {
    $expansion = null;
    $index = -1;
    foreach($args as $arg) {
      ++$index;
      if ($arg != '*') continue;
      $table = $this->get_table($collection);
      $expansion = $this->db->read_column("select distinct attribute from $table where collection = '$collection' and version = 0 ");
      break;
    }
    if ($expansion) array_splice($args, $index, 1, $expansion);
  }

  function extract_header(&$args)
  {
    if (is_numeric($args[0])) {
      if (is_numeric($args[1]))
        list($offset, $size) = array_splice($args, 0, 2);
      else
        $size = array_shift($args);
    }
    list($collection, $filters) = array_splice($args, 0, 2);
    list($collection) = assoc_element($collection);
    if ($filters == '')
      $filters = [];
    else if (is_string($filters))
      $filters = ['identifier'=>$filters];
    return [$collection, $filters, $offset, $size];
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
    if (!is_null($offset))
      $sql .= " limit $offset, $size";
    else if (!is_null($size))
      $sql .= " limit $size";
  }

  function set_sorting(&$sql, $sorting, $group=true)
  {
    if (empty($sorting)) return;
    if ($group)
      $sql = "select * from ($sql) tmp order by ".implode(',', $sorting);
    else
      $sql .= " order by ".implode(',', $sorting);
  }

  function wrap_query(&$sql, $args)
  {
    $outer = [];
    $wrapped = false;
    foreach($args as $arg) {
      list($alias,$name) = $this->page->get_sql_pair($arg);
      if ($name[0] == "'") {
        list($name, $alias) = explode('.', $alias);
        if (!$alias) $alias = $name;
        $outer[] = $alias;
        continue;
      }
      $outer[] = "$name $alias";
      $wrapped = true;
    }
    if ($wrapped)
      $sql = "select " . join(',', $outer) . " from ($sql) tmp";
  }
  function read($args)
  {
    $args = page::parse_args($args);
    $size = $this->expand_args($args);
    list($collection, $filters, $offset, $size) = $this->extract_header($args);
    $this->expand_star($collection, $args);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $sorting = [];
    $table = $this->get_table($collection);
    $selection = $this->get_selection($table, $args, $where, $sorting, $has_primary_filter);
    $joins = $this->get_joins($table, $filters, $where, $has_primary_filter);
    $sql = "select $selection from $table m $joins $where $search";
    $this->set_limits($sql, $offset, $size);
    $this->wrap_query($sql, $args);
    $this->set_sorting($sql, $sorting);
    return $this->page->translate_sql($sql);
  }

  function values()
  {
    $a = func_get_args();
    if (!is_numeric($a[0])) array_splice($a, 0, 0, 1);
    $sql = $this->read($a);
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
    $sql = $this->read(func_get_args());
    if ($this->page->foreach)
      return $this->db->read($sql, MYSQLI_ASSOC);
    return ['data'=>$this->db->read($sql, MYSQLI_NUM)];
  }

  function update()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.update", 3);
    list($collection, $filters) = $this->extract_header($args);
    $this->page->parse_delta($args);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $joins = $this->get_joins($filters, $where);
    $table = $this->get_table($collection);
    foreach($args as $arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      if ($name == 'identifier') {
        $attribute = $name;
        $condition = "";
      }
      else {
        $attribute = 'value';
        $condition = " and m.attribute = '$name'";
      }
      $attribute = $name== 'identifier'? $name: 'value';
      $this->page->sql_exec("update $table m $joins set m.$attribute = $value $where $condition");
    }
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 3);
    list($collection, $identifier) = $this->extract_header($args);
    $table = $this->get_table($collection);
    $sql = "insert into $table(version,collection,identifier,attribute,value) values";
    $identifier_func = "last_insert_id()";
    list($alias,$identifier) = assoc_element($identifier);
    if (!is_null($value)) $identifier = $value;
    foreach($args as &$arg) {
      list($name,$value) = $this->page->get_sql_pair($arg);
      $name = addslashes($name);
      if ($identifier[0] == '/') {
        $identifier_func = substr($identifier,1);
        $identifier = "";
      }
      $this->page->sql_exec($sql . "(0,'$collection', '$identifier','$name',$value)");
      if ($identifier) continue;
      list($identifier,$last_id) = $this->db->read_one("select $identifier_func, last_insert_id()");;
      $this->db->exec("update $table set identifier='$identifier' where id = $last_id");
    }
    return ["new_${collection}_id"=>$identifier];
  }

  function search()
  {
    $args = page::parse_args(func_get_args());
    log::debug_json("collection.search", $args);
    page::verify_args($args, "collection.search", 1);
    $term = array_shift($args);
    $size = $this->expand_args($args);
    list($collection, $filters, $offset, $size) = $this->extract_header($args);
    $this->expand_star($collection, $args);
    $fields = [];
    $sorting = [];
    $identifier = false;
    foreach($args as &$arg) {
      list($name,$alias) = $this->get_name_alias($arg);
      $this->update_sort_order($sorting, $name, $alias);

      if ($name == 'identifier') {
        $fields[] = "$name $alias";
        $identifier = true;
        $where .= " and (m.identifier like '%$term%'";
      }
      else
        $fields[] = "max(case when attribute='$name' then value end) $alias";
    }
    $joins = $this->get_joins($filters);
    $where = " where collection = '$collection' and version <= 0 ";
    if ($term != "") {
      if ($identifier)
        $where .= " and (m.identifier like '%$term%' or m.value like '%$term%')";
      else
        $where .= " and m.value like '%$term%'";
    }
    $sql = "select ". implode(",", $fields). " from (
     select m.identifier,m.attribute,m.value FROM collection m $joins $where) tmp
     group by identifier";

    $this->set_sorting($sql, $sorting, false);
    $this->set_limits($sql, $offset, $size);
    return ['data'=>$this->db->read($sql, MYSQLI_NUM)];
  }

  function exists($collection, $identifier)
  {
    $table = $this->get_table($collection);
    return $this->db->exists("select 1 from $table where collection='$collection' and identifier='$identifier'");
  }
}
