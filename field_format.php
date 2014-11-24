<?php

define ('FORMAT_IGNORE', 0);
define ('FORMAT_UNIT', 1);
define ('FORMAT_COMPOSITE', 2);
define ('FORMAT_RECORD', 3);
define ('FORMAT_DELIMITED', 4);

class field_format
{
  var $type;
  var $use_previous;
  function __construct($type=FORMAT_IGNORE) 
  { 
    $this->type = $type; 
    $this->use_previous = false;
  }
}

class format_unit extends field_format
{
  // format: name[.repeat_count]
  function __construct() { parent::__construct(FORMAT_UNIT); }
};

class format_composite extends field_format
{
  // format: name.composite.part_count.combiner[.repeat_count]
  var $num_fields;  // number of input field values to be combined to make up a composite value
  var $combiner;    // string to combine the values, if empty, values are concatenated
  function __construct($type=FORMAT_COMPOSITE) { parent::__construct($type); }
};


class format_record extends field_format
{
  // format: name.record[.repeat_count]
  var $fields;   
  var $positions;
  function __construct($type=FORMAT_RECORD) { parent::__construct($type); }
};

class format_delimited extends format_record
{
  // format: name.delimited[.repeat_count]
  var $delimiter;    
  function __construct($type=FORMAT_DELIMITED) { parent::__construct($type); }
};

class format_position
{
  var $name;
  var $repeat_count;
};

?>
