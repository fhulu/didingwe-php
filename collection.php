<?php

class collection
{

  var $page;
  function __construct($page)
  {
    $this->page = $page;
  }

  function get_selection($args, &$where)
  {
    $identifier = "";
    $primary = [];
    $sub_fields = [];
    foreach($args as &$arg) {
      if (is_string($arg))
        $name = $alias = $arg;
      else if (is_assoc($arg)) {
        list($alias, $name) = assoc_element($arg);
        if (is_assoc($name)) $name = $alias;
      }


      if ($name == 'identifier')
        $identifier = "m.$name $alias";
      else if (empty($primary) && strpos($name, '.') === false)
        $primary = [$name, $alias];
      else
        $sub_fields[] = [$name,$alias];
    }
    $sub_queries = $this->get_subqueries($sub_fields);
    $selection = [$identifier];
    if (!empty($primary)) {
      $where .= " and m.attribute = '$primary[0]'";
      $selection[] = "m.value $primary[1]";
    }
    $selection[] = $sub_queries;
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
      if (!isset($foreign_name)) {
        $queries[] =
          "(select value from collection where collection = m.collection
             and version <= m.version and identifier=m.identifier and attribute = '$name'
             order by version desc limit 1) $alias";
        continue;
      }
      if ($alias == $name) $alias = $foreign_key;
      $queries[] =
        "(select value from collection where collection = '$foreign_key' and version <= m.version and attribute = '$foreign_name'
            and identifier = (
              select value from collection where collection = m.collection and version <= m.version
                and identifier=m.identifier and attribute = '$foreign_key' order by version desc limit 1)
          order by version desc limit 1) $alias";
    }
    return implode(",", $queries);
  }


  function get_joins($filters, &$where)
  {
    $index = 0;
    $joins = "";
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

  function read($method, $args)
  {
    $args = page::parse_args($args);
    if (empty($args))
      $args = [$this->page->path[sizeof($this->page->path)-2], [], "identifier", "name"];
    page::verify_args($args, "collection.$method", 3);
    list($collection, $filters) = array_splice($args, 0, 2);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $selection = $this->get_selection($args, $where);
    $joins = $this->get_joins($filters, $where  );

    return $this->page->{"sql_$method"}("select $selection from collection m $joins $where");
  }

  function values()
  {
    return $this->read("values", func_get_args());
  }

  function data()
  {
    return $this->read("data", func_get_args());
  }

}
