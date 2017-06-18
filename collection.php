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
      if (isset($foreign_name)) {
        $name = $foreign_name;
      }
      else {
        $name = $foreign_name = $foreign_collection;
        $foreign_collection = $local_name;
      }
      $attr['local_name'] = $local_name;
      $attr['foreign_name'] = $foreign_name;
      $attr['table'] = $this->get_table($foreign_collection);
      $attr['collection'] = $foreign_collection;
      if (!in_array($foreign_name, $this->foreigners))
        $this->foreigners[] = $foreign_collection;
      if (!$aliased) $alias = $name;
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
    if ($aggregated) $this->aggregated = true;

    return $attr;
  }

  function init_attributes($args)
  {
    $this->attributes = [];
    $this->aggregated = false;
    $index = 1;
    foreach($args as $arg) {
      $attr = $this->init_attr($arg);
      if (in_array($attr['alias'], $this->sort_columns) || in_array("$index", $this->sort_columns)) {
        if (!$attr['sort_order']) $attr['sort_order'] = $this->page->request['sort_order'];
      }
      ++$index;
      $this->attributes[] = $attr ;
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

  function create_filter_joins()
  {
    $sql = "";
    foreach($this->filters as &$filter) {
      $name = $filter['name'];
      $alias = "filter_$name";
      $value = $filter['value'];
      $table = $filter['table'];
      $local_name = $filter['local_name'];
      if (is_null($local_name)) {
        $sql .= " join `$this->main_table` `$alias` "
            . " on  `$alias`.collection = `$this->main_collection`.collection and `$this->main_collection`.identifier = `$alias`.identifier "
            . " and `$alias`.attribute = '$name' and `$alias`.value $value ";

      }
      else {
        $collection = $filter['collection'];
        $table = $filter['table'];
        $sql .= " join `$table` `$alias` on `$alias`.collection = '$collection'"
         . " and `$this->main_collection`.attribute = '$local_name' and `$alias`.identifier = `$this->main_collection`.value";
      }
    }
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
    $main = "`$this->main_collection`";
    $sql = "select $main.identifier, ";
    $selected = [];
    foreach($this->attributes as $attr) {
      if (!$attr['foreign_name']) continue;
      $collection = $attr['collection'];
      if (in_array($collection, $selected)) continue;
      $name = $attr['name'];
      $foreign_attrs .= "when `$collection`.attribute is not null then '$name' \n";
      $foreign_vals  .= "when `$collection`.value is not null then `$collection`.value\n";
    }
    if ($foreign_attrs)
      $sql .= "case $foreign_attrs else $main.attribute end `attribute`\n, case $foreign_vals else $main.value end `value`\n ";
    else
      $sql .= "$main.attribute `attribute`, $main.value `value`";
    if ($sort_fields)
      $sql .= "," . implode(",", $sort_fields);
    return $sql;
  }

  function get_attribute_filter($collection, $foreigners=[])
  {
    $names = [];
    $set_names = function($collection, $foreign) use (&$names) {
      foreach($this->attributes as $attr) {
        if ($attr['aggregated'] || $attr['collection'] != $collection) continue;
        $names[] = $foreign? $attr['local_name']: $attr['name'];
      }
    };
    $set_names($collection, false);
    foreach($foreigners as $foreigner) {
      $set_names($foreigner, true);
    }
    $names = implode("','", $names);
    return "`$collection`.collection = '$collection' and `$collection`.attribute in ('$names')";
  }


  function create_outer_select()
  {
    foreach ($this->attributes as $attr) {
      if ($attr['aggregated']) continue;
      $name = $attr['name'];
      $alias = $attr['alias'];
      $names[] = "max(case when attribute='$name' then value end) `$alias`";
    }
    $names = implode(",\n", $names);
    list($sort_fields, $sort_joins, $order_sql) = $this->create_sort_joins();
    $sql =  "select $names from ("
      . $this->create_inner_select($sort_fields)
      . " from `$this->main_table` `$this->main_collection` "
      . $this->create_filter_joins()
      . $this->join_foreigners();

    $sql .= "$sort_joins where ". $this->get_attribute_filter($this->main_collection, $this->foreigners);

    if ($this->identifier_filter)
      $sql .= " and `$this->main_collection`.identifier = '$this->identifier_filter'";

    return $sql . " $order_sql) tmp group by identifier $order_sql";
  }

  function aggregate($sql)
  {
    foreach ($this->attributes as $attr) {
      $alias = $attr['alias'];
      if ($attr['aggregated'])
        $names[] = substr($attr['name'], 1) . " `$alias`";
      else
        $names[] = "`$alias`";
    }
    $names = implode(",", $names);
    return "select $names from ($sql) tmp";
  }

  function get_joins($main_collection, $filters, &$where="", $conjuctor="and", $index=0, $new_group=false)
  {
    $joins = "";
    // create joins for each filter
    foreach($filters as $filter) {
      ++$index;
      if ($filter == "") continue;
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
        " join `$table` m$index on m$index.collection = m.collection
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
      $joins .= " join `$table` m$index on m$index.collection = m.collection
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
        $joins .= " join `$table` m0$index on m0$index.collection = '$collection'
                  and m0$index.version <= m.version and m0$index.identifier = m$index.value";

        $where .= " and m0$index.attribute = '$name' and m0$index.value $operator $value";
      }

    }
    return $joins;
  }

  function expand_star($arg)
  {
    $matches = [];
    if (!is_string($arg) || !preg_match('/^(?:(\w+)\.)?\*/', $arg, $matches))
      continue;
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
  }

  function add_custom_filters(&$sql, $args)
  {
    $index = -1;
    $request = $this->page->request;
    $filters = [];
    foreach($args as $arg) {
      list($name,$alias) = $this->get_name_alias($arg);
      if (in_array($alias, $this->hidden_columns, true)) continue;
      ++$index;
      $term = $request["f$index"];
      if (!isset($term)) continue;
      $filters[] = "$alias like '%$term%'";
    }
    if (!empty($filters))
      $sql = "select * from ($sql) tmp_custom_filter where " . join(" and ", $filters);
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

  function set_grouping(&$sql, $grouping)
  {
    if (!empty($grouping))
      $sql .= " group by " . implode(",", $grouping);
  }

  function wrap_query(&$sql, $args, $term, $use_custom_filters)
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
      array_splice($aliases, $combined_pos, 0, 'combined');
    }
    $sql = "select " . join(',', $outer) . " from ($sql) tmp";
    if ($term != '') {
      $aliases = array_map(function($v) use($term) { return "`$v` like '%$term%'"; }, $aliases);
      $sql = "select * from ($sql) tmp_search where " . join(' or ', $aliases);
    }
    else if ($use_custom_filters) {
      $this->add_custom_filters($sql, $aliases);
    }
    return true;
  }


  function read($args, $use_custom_filters=false, $term="")
  {
    $args = page::parse_args($args);
    $this->expand_args($args);
    $this->extract_header($args);
    $this->init_attributes($args);
    $this->init_filters();
    $sql = $this->create_outer_select();
    if ($this->aggregated)
      $sql = $this->aggregate($sql);
    // if (!empty($this->combined_columns))
    //   $this->expand_star($collection, $this->combined_columns, $args);
    // $this->expand_star($collection, $args);
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
      $updated = $this->page->sql_exec("update `$table` m $joins set m.$attribute = $value $where $condition");
      if ($updated) continue;
      list($identifier) = find_assoc_element($filters, 'identifier');
      if (!$identifier || $identifier[0] == '/') continue;
      $this->page->sql_exec("insert into `$table`(collection,identifier,attribute,value)
        select '$collection', '$identifier', '$name', $value from dual where not exists (
          select 1 from `$table` m $where $condition)");
    }
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
