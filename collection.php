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
    if (!preg_match('/^(\w[\w\.]*)(\s*\+\d+)?(\s+group\s*)?(\s+asc\s*|\s+desc\s*)?$/', $alias, $matches)) return;
    if ($name == $alias)
      $name = $alias = $matches[1];
    else
      $alias = $matches[1];
    if (sizeof($matches) < 2) return;
    $field =  array_slice(explode('.',$alias),-1)[0];
    if ($matches[3]) $grouping[] = $field;
    if ($matches[4]) $sorting[] = "$field $matches[2] $matches[4]";
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
    $selection = [];
    foreach($args as &$arg) {
      if (is_array($arg) && !is_assoc($arg)) {
        $selection[] = $this->get_selection($table, $arg, $where, $sorting, $grouping);
        continue;
      }
      list($name,$alias) = $this->get_name_alias($arg);
      if ($alias[0] == '/') continue;
      $this->extract_grouping($sorting, $grouping, $name, $alias);
      if ($name == 'identifier')
        $selection[] = "m.$name `$alias`";
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
              and identifier=m.identifier and attribute = '$local_name' order by version desc, id desc)";
    }
    return "($query order by version desc, id desc limit 1) `$alias`";
  }

  function get_joins($main_collection, $filters, &$where="", $conjuctor="and", $index=0, $new_group=false)
  {
    $joins = "";
    // create joins for each filter
    foreach($filters as $filter) {
      ++$index;
      if (is_array($filter) && sizeof($filter) > 1) {
        $where .= " $conjuctor (";
        $joins .= $this->get_joins($main_collection, $filter, $where, "or", $index, true);
        $where .= ")";
        continue;
      }
      if ($filter == '_access') {
        continue;

        // todo: fix access control
        $member_table = $this->get_table("user_group_member");
        $user_groups =  implode_quoted($this->auth->get_groups());
        $joins .=
        " join $table m$index on m$index.collection = m.collection
            and m$index.version <= m.version and m$index.identifier=m.identifier
            and m$index.attribute = 'owner'
          join $member_table owner on owner.collection = 'user_group_member'
            and owner.version <= m.version and owner.attribute = 'user'";
        $where .= " and (owner.value = m$index.value or m$index.
          join $member_table owner_groups on owner_groups.collection = 'user_group_member'
            and owner_groups.version <= m.version and owner_groups.attribute = 'group' and owner_groups.identifier = owner.identifier
            and owner_groups.value in ($user_groups)";
        continue;
      }
      list($name,$value) = $this->page->get_sql_pair($filter);
      $operator = "";
      if ($value[0] == "'" )
        $operator = " = ";

      if ($name == 'identifier') {
        $where .= " and m.$name $operator $value";
        continue;
      }
      $table = $this->get_table($main_collection);
      $joins .= " join $table m$index on m$index.collection = m.collection
                and m$index.version <= m.version and m$index.identifier = m.identifier";
      list($local_name, $foreign_key, $foreign_name) = explode('.', $name);
      if (!$new_group)
        $where .= " $conjuctor ";
      else $new_group = false;
      $where .= " m$index.attribute = '$local_name' ";
      if (!isset($foreign_key)) {
        $where .= " and m$index.value $operator $value ";
      }
      else {
        if (!isset($foreign_name)) {
          $collection = $local_name;
          $name = $foreign_key;
        }
        else {
          $collection = $foreign_key;
          $name = $foreign_name;
        }
        if (substr($value,0,2) == "'$") $value = "'$" . last(explode('.', $value));
        $table = $this->get_table($collection);
        $joins .= " join $table m0$index on m0$index.collection = '$collection'
                  and m0$index.version <= m.version and m0$index.identifier = m$index.value";

        $where .= " and m0$index.attribute = '$name' and m0$index.value = $value";
      }

    }
    return $joins;
  }

  function expand_star($default_collection, &$args, $aliases=[])
  {
    $expansion = null;
    $star_index = $index = -1;
    $aliases = array_map(function($value) {
      list($name,$alias) = $this->get_name_alias($value);
      return $alias;
    }, $aliases);
    foreach($args as $arg) {
      ++$index;
      $matches = [];
      if (!is_string($arg) || !preg_match('/^(?:(\w+)\.)?\*/', $arg, $matches)) {
        list($name,$alias) = $this->get_name_alias($arg);
        $aliases[] = $alias;
        continue;
      }
      $collection = $default_collection;
      $foreign = false;
      if ($matches[1]) {
        $collection = $matches[1];
        $foreign = true;
      }
      if (empty($this->columns[$collection]))
        $this->columns[$collection] = $this->get_fields($collection);
      $expanded = $this->columns[$collection];
      if ($foreign) {
        $expanded = array_map(function($v) use ($collection) {
          return "$collection.$v";
        }, $expanded);
      }
      $star_index = $index;
    }
    if ($star_index == -1) return;
    $expanded = array_diff($expanded, array_values($aliases));
    array_splice($args, $star_index, 1, $expanded);
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
    if (!is_null($offset) && !is_null($size))
      $sql .= " limit $offset, $size";
    else if (!is_null($size))
      $sql .= " limit $size";
  }

  function set_sorting(&$sql, $sorting, $group=true, $args=[])
  {
    if (empty($sorting)) {
      $request = $this->page->request;
      if (!$this->dynamic_sorting) return;
      foreach($this->sort_columns as $field) {
        $index = $request[$field];
        if (!isset($index)) continue;
        $sorting = "$index " . $request['sort_order'];
      }
      if (empty($sorting)) return;
    }
    else {
      $sorting = implode(',', $sorting);
    }
    if ($group)
      $sql = "select * from ($sql) tmp_sorting";
    $sql .= " order by $sorting";
  }

  function set_grouping(&$sql, $grouping, $term)
  {
    $this->filter_term($sql, $grouping, $term);
    $sql .= " group by " . implode(",", $grouping);
    return true;
  }

  function wrap_query(&$sql, $args, $term)
  {
    $outer = [];
    $wrapped = $this->lastColumn != '' || !empty($this->hidden_columns || !empty($this->combined_columns)) || $term != '';
    $combined = [];
    $combined_pos = -1;
    $index = -1;
    $aliases = [];
    foreach($args as $arg) {
      list($alias,$name) = $this->page->get_sql_pair($arg);
      $this->extract_grouping($grouping,$sorting, $name,$alias);
      if (in_array($alias, $this->hidden_columns, true)) continue;
      ++$index;
      if (in_array($alias, $this->combined_columns, true)) {
        list($name, $alias) = explode('.', $alias);
        if (!$alias) $alias = $name;
        $combined[] = "`$alias`";
        if ($combined_pos==-1) $combined_pos = $index;
        $wrapped = true;
        continue;
      }
      if ($name[0] == "'") {
        list($name, $alias) = explode('.', $alias);
        if (!$alias) $alias = $name;
        $outer[] = "`".explode(' ',$alias)[0] . "`";
        $aliases[] = $alias;
        continue;
      }
      else {
        $outer[] = "$name `$alias`";
        $aliases[] = $alias;
      }
      $wrapped = true;
      if ($alias == $this->lastColumn) break;
    }
    if (!$wrapped) return false;
    if (!empty($combined)) {
      $combined = "concat_ws(' ', " . join(',', $combined) . ") `combined`";
      array_splice($outer, $combined_pos, 0, [$combined]);
      $aliases[] = 'combined';
    }
    $sql = "select " . join(',', $outer) . " from ($sql) tmp";
    $this->filter_term($sql, $aliases, $terms);
    return true;
  }

  function filter_term(&$sql, $args, $term)
  {
    if (!$term) return;
    $args = array_map(function($v) use($term) {
        return "`$v` like '%$term%'"; }, $args);
    $sql = "select * from ($sql) tmp_search where " . join(' or ', $args);
  }

  function read($args, $use_custom_filters=false, $term="")
  {
    $args = page::parse_args($args);
    $size = $this->expand_args($args);
    list($collection, $filters, $offset, $size) = $this->extract_header($args);
    if (!empty($this->combined_columns))
      $this->expand_star($collection, $this->combined_columns, $args);
    $this->expand_star($collection, $args);
    if ($use_custom_filters)
      $this->add_custom_filters($filters, $args);
    $where = " where m.collection = '$collection' and m.version = 0 ";
    $sorting = [];
    $table = $this->get_table($collection);
    $selection = $this->get_selection($table, $args, $where, $sorting, $grouping);
    $joins = $this->get_joins($collection, $filters, $where);
    $sql = "select $selection from $table m $joins $where";
    $sql .= " group by m.identifier";
    if (!empty($grouping))
      $wrapped = $this->set_grouping($sql, $grouping, $term);
    else
      $wrapped = $this->wrap_query($sql, $args, $term);
    if (!$this->no_sorting)
      $this->set_sorting($sql, $sorting, !$wrapped, $args);
    $this->set_limits($sql, $offset, $size);
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
    list($collection, $filters) = $this->extract_header($args);
    $this->page->parse_delta($args);

    $where = " where m.collection = '$collection' and m.version = 0 ";
    $table = $this->get_table($collection);
    $joins = $this->get_joins($collection, $filters, $where);
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
      if ($updated) continue;
      list($identifier) = find_assoc_element($filters, 'identifier');
      if (!$identifier || $identifier[0] == '/') continue;
      $this->page->sql_exec("insert into $table(collection,identifier,attribute,value)
        select '$collection', '$identifier', '$name', $value from dual where not exists (
          select 1 from $table m $where $condition)");
    }
  }

  function insert()
  {
    $args = page::parse_args(func_get_args());
    page::verify_args($args, "collection.insert", 3);
    list($collection, $identifier) = array_splice($args, 0, 2);
    list($collection) = assoc_element($collection);
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

  function get_fields($collection)
  {
    $table = $this->get_table($collection);
    return $this->db->read_column("select distinct attribute from `$table` where collection = '$collection'");
  }


  function hide()
  {
    $this->hidden_columns = array_merge($this->hidden_columns, func_get_args());
  }

  function combine()
  {
    $this->combined_columns = array_merge($this->combined_columns, func_get_args());
  }

  function sort_on()
  {
    $this->sort_columns = array_merge($this->sort_columns, func_get_args());
  }
}
