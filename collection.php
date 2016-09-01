<?php

class collection
{
  var $page;
  var $db;
  function __construct($page)
  {
    $this->page = $page;
    $this->db = $page->db;
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

  function get_selection($args, &$where, &$sorting)
  {
    $identifier = "";
    $primary = [];
    $sub_fields = [];
    $index = 0;
    $identifier_pos = 0;
    foreach($args as &$arg) {
      if (is_string($arg))
        $name = $alias = $arg;
      else if (is_assoc($arg)) {
        list($alias, $name) = assoc_element($arg);
        if (is_assoc($name)) $name = $alias;
      }

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
    $sub_queries = $this->get_subqueries($sub_fields);
    $selection = [];
    if (!empty($primary)) {
      $where .= " and m.attribute = '$primary[0]'";
      $selection[] = "m.value $primary[1]";
    }
    $selection[] = $sub_queries;
    if ($identifier)
      array_splice($selection, $identifier_pos, 0, [$identifier]);
    return implode(",", array_filter($selection));
  }

  function get_subqueries($fields)
  {
    $queries = [];
    foreach($fields as $name_alias) {
      list($name,$alias) = $name_alias;
      $name = addslashes($name);
      $alias = addslashes($alias);
      list($foreign_key, $foreign_name) = explode('.', $name);
      if (!isset($foreign_name))
        $query = "select value from collection where collection = m.collection
             and version <= m.version and identifier=m.identifier and attribute = '$name'";

      else {
        if ($alias == $name) $alias = $foreign_name;
        $name = $foreign_name;
        $query =
          "select value from collection where collection = '$foreign_key' and version <= m.version and attribute = '$foreign_name'
            and identifier = (
              select value from collection where collection = m.collection and version <= m.version
                and identifier=m.identifier and attribute = '$foreign_key' order by version desc limit 1)";
      }
      $queries[] = "ifnull(($query order by version desc limit 1),'\$$name') $alias";
    }
    return implode(",", $queries);
  }


  function get_joins($filters, &$where)
  {
    $index = 0;
    $joins = "";
    if (empty($filters)) return "";
    if (!is_array($filters) || is_assoc($filters)) $filters = [$filters];
    foreach($filters as $filter) {
      ++$index;
      list($name,$value) = $this->page->get_sql_pair($filter);
      if ($name == 'identifier') {
        $where .= " and m.$name = $value";
        continue;
      }

      $operator = "";
      if (ctype_alnum($value[0]) || $value[0] == "'")
          $operator .= " = ";
      $joins .= " join collection m$index on m$index.collection = m.collection
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
      $expansion = $this->db->read_column("select distinct attribute from collection where collection = '$collection' and version = 0 ");
      break;
    }
    if ($expansion) array_splice($args, $index, 1, $expansion);
  }

  function extract_header(&$args)
  {
    list($collection, $filters) = array_splice($args, 0, 2);
    list($collection) = assoc_element($collection);
    return [$collection, $filters];
  }

  function read($method, $args)
  {
    $args = page::parse_args($args);
    $size = sizeof($args);
    switch($size) {
      case 0: $args = [$this->page->path[sizeof($this->page->path)-2], [], "identifier", "name asc"]; break;
      case 1: $args = [$args[0],[],'*']; break;
      case 2: $args[] = '*';
    }
    list($collection, $filters) = $this->extract_header($args);
    $this->expand_star($collection, $args);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $sorting = [];
    $selection = $this->get_selection($args, $where, $sorting);
    $joins = $this->get_joins($filters, $where  );
    $sql = "select $selection from collection m $joins $where";
    if (!empty($sorting)) $sql = "select * from ($sql) tmp order by ".implode(',', $sorting);
    return $this->page->{"sql_$method"}($sql);
  }

  function values()
  {
    return $this->read("values", func_get_args());
  }

  function data()
  {
    return $this->read("data", func_get_args());
  }

  function update()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.update", 3);
    list($collection, $filters) = $this->extract_header($args);
    $this->page->parse_delta($args);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $joins = $this->get_joins($filters, $where);
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
      $this->page->sql_exec("update collection m $join set m.$attribute = $value $where $condition");
    }
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 3);
    list($collection, $identifier) = $this->extract_header($args);
    $sql = "insert into collection(version,collection,identifier,attribute,value) values";
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
      $this->db->exec("update collection set identifier='$identifier' where id = $last_id");
    }
    return ["new_${collection}_id"=>$identifier];
  }

}
