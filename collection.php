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

  function extract_grouping(&$sorting, &$grouping, &$name, &$alias)
  {
    $matches = [];
    if (!preg_match('/^(\w[\w\.]*)(\s+group\s*)?(\s+asc|desc\s*)?$/', $alias, $matches)) return;
    if ($name == $alias)
      $name = $alias = $matches[1];
    else
      $alias = $matches[1];
    if (sizeof($matches) < 2) return;
    $field =  array_slice(explode('.',$alias),-1)[0];
    if ($matches[2]) $grouping[] = $field;
    if ($matches[3]) $sorting[] = "$field $matches[3]";
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

  function get_selection($table, $args, &$where, &$sorting, &$grouping)
  {
    foreach($args as &$arg) {
      list($name,$alias) = $this->get_name_alias($arg);
      if ($alias[0] == '/') continue;
      $this->extract_grouping($sorting, $grouping, $name, $alias);
      if ($name == 'identifier')
        $selection[] = "m.$name $alias";
      else
        $selection[] = $this->get_subquery($table, $name, $alias, $term);
      ++$index;
    }
    return implode(",", array_filter($selection));
  }

  function get_subquery($table, $name, $alias, $term)
  {
    if ($name[0] == '/') return;
    $name = addslashes($name);
    $alias = addslashes($alias);
    list($local_name, $foreign_key, $foreign_name) = explode('.', $name);
    $value = "value";
    if (!isset($foreign_key)) {
      $query = "select group_concat($value) from $table where collection = m.collection
           and version <= m.version and identifier=m.identifier and attribute = '$name'";
    }
    else {
      if (!isset($foreign_name)) {
        $foreign_name = $foreign_key;
        $foreign_key = $local_name;
      }
      if ($alias == $name) $alias = $foreign_name;
      $name = $local_name;
      $sub_table = $this->get_table($foreign_key);
      $query =
        "select group_concat($value) from $sub_table where collection = '$foreign_key' and version <= m.version and attribute = '$foreign_name'
          and identifier in (
            select value from $table where collection = m.collection and version <= m.version
              and identifier=m.identifier and attribute = '$local_name' order by version desc)";
    }
    return "($query order by version desc limit 1) $alias";
  }

  function get_joins($table, $filters, &$where="", $conjuctor="and", $index=0, $new_group=false)
  {
    $joins = "";
    // create joins for each filter
    foreach($filters as $filter) {
      ++$index;
      if (sizeof($filter) > 1) {
        $where .= " $conjuctor (";
        $joins .= $this->get_joins($table, $filter, $where, "or", $index, true);
        $where .= ")";
        continue;
      }
      list($name,$value) = $this->page->get_sql_pair($filter);
      if ($name == 'identifier') {
        $where .= " and m.$name = $value";
        continue;
      }

      $operator = "";
      if ($value[0] == "'" )
        $operator = " = ";

      $joins .= " join $table m$index on m$index.collection = m.collection
                and m$index.version <= m.version and m$index.identifier=m.identifier";
      if (!$new_group)
        $where .= " $conjuctor ";
      else $new_group = false;
      $where .= "m$index.attribute = '$name' and m$index.value $operator $value";
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

  function add_custom_filters(&$filters, $args)
  {
    $index = -1;
    $request = $this->page->request;

    foreach($args as $arg) {
      ++$index;
      $term = $request["f$index"];
      if (!isset($term)) continue;
      list($name,$alias) = $this->get_name_alias($arg);
      $filters[] = [$alias=>"/like '%$term%'"];
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
    if ($filters == '')
      $filters = [];
    else if (is_string($filters))
      $filters = [['identifier'=>$filters]];
    else if (is_assoc($filters))
      $filters = [$filters];
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

  function set_sorting(&$sql, $sorting, $group=true, $args=[])
  {
    if (empty($sorting)) {
      $request = $this->page->request;
      $index = $request['sort'];
      if (!isset($index) || !is_numeric($index)) return;
      list($field) = assoc_element($args[$index]);
      if (!$field) return;
      $sorting = $field . " " . $request['sort_order'];
    }
    else {
      $sorting = implode(',', $sorting);
    }
    if ($group)
      $sql = "select * from ($sql) tmp_sorting";
    $sql .= " order by $sorting";
  }

  function set_grouping(&$sql, $grouping)
  {
    if (!empty($grouping))
      $sql .= " group by " . implode(",", $grouping);
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
        $outer[] = explode(' ',$alias)[0];
        continue;
      }
      $outer[] = "$name $alias";
      $wrapped = true;
    }
    if ($wrapped)
      $sql = "select " . join(',', $outer) . " from ($sql) tmp";
    return $wrapped;
  }

  function read($args, $use_custom_filters=false, $term="")
  {
    $args = page::parse_args($args);
    $size = $this->expand_args($args);
    list($collection, $filters, $offset, $size) = $this->extract_header($args);
    $this->expand_star($collection, $args);
    if ($use_custom_filters)
      $this->add_custom_filters($filters, $args);
    $where = " where m.collection = '$collection' and m.version = 0 ";
    $sorting = [];
    $table = $this->get_table($collection);
    $selection = $this->get_selection($table, $args, $where, $sorting, $grouping, $term);
    $joins = $this->get_joins($table, $filters, $where);
    $sql = "select $selection from $table m $joins $where";
    if ($term != '')
      $sql .= " and (value like '%$term%' or identifier like '%$term%') ";
    $sql .= "group by m.identifier";
    $this->set_limits($sql, $offset, $size);
    $wrapped = $this->wrap_query($sql, $args);
    $this->set_grouping($sql, $grouping);
    $this->set_sorting($sql, $sorting, !$wrapped, $args);
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
    $sql = $this->read(func_get_args(), true);
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
    $table = $this->get_table($collection);
    $joins = $this->get_joins($table, $filters, $where);
    $identifier = $filters['identifier'];
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
      $updated = $this->page->sql_exec("update $table m $joins set m.$attribute = $value $where $condition");
      if (!$updated && $identifier) {
        $this->page->sql_exec("insert into $table(collection,identifier,attribute,value)
          select '$collection', '$identifier', '$name', $value from dual where not exists (
            select 1 from $table m $where $condition)");
      }
    }
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 3);
    list($collection, $identifier) = array_splice($args, 0, 2);
    $table = $this->get_table($collection);
    $sql = "insert into $table(version,collection,identifier,attribute,value) values";
    $identifier_func = "last_insert_id()";
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
      $args[] = 'identifier';
    }
    else {
      $filters = [];
      for ($i=1; $i < $size; $i+=2) {
        $filters[] = [$args[$i]=>$args[$i+1]];
      }
      $args = [$args[0], $filters, 'identifier' ];
      log::debug_json("args", $args);
    }
    $sql = $this->read($args);
    return $this->db->exists($sql);
  }

  function scroll()
  {
    $request = $this->page->request;
    $page_num = $request['page_num'];
    $page_size = $request['page_size'];
    $args = array_merge([$page_num*$page_size, $page_size], func_get_args());
    return call_user_func_array([$this, "data"], $args);
  }
}
