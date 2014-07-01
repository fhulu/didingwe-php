<?php

class period
{
  static function get_recent_sql($field, $period, $todate=null)
  {
    switch($period) {
    case 'today': 
      $condition = array("$field >= curdate()", "$field < curdate() + interval 1 day"); break;
    case 'yesterday':
      $condition = array("$field >= curdate() - interval 1 day ", "$field < curdate()"); break;
    case 'this_week':
      $condition = array("$field >=curdate() - interval dayofweek(curdate()) day", "$field < curdate() + interval 1 week - interval dayofweek(curdate()) day"); break;
    case 'last_week':
      $condition = array("$field >= curdate() - interval 1 week - interval dayofweek(curdate()) day", "$field < curdate() - interval dayofweek(curdate()) day"); break;
    case 'this_month':
      $bom = date('Y-m-01');
      $condition = array("$field >= '$bom'", "$field < '$bom' + interval 1 month"); break;
    case 'last_month':
      $bom = date('Y-m-01');
      $condition = array("$field >= '$bom' - interval 1 month", "$field < '$bom'"); break;
    default: 
      return period::get_recent_sql($field, 'today');
    }
    if (!is_null($todate)) 
      return $condition[0];
    return $condition[0] . ' and ' . $condition[1];
  }
  
  function get_sql($period, $control_prefix, $recent_period, $sql_field)
  {
    switch($period) {
    case 'recent': return period::get_recent_sql($sql_field, $recent_period);
    case "date":
      $start_date = "'" . $_REQUEST[$control_prefix .'date'] . "'";
      return "$sql_field >= $start_date and $sql_field <= $start_date";
    case "month":
      $start_date = "'" . $_REQUEST[$control_prefix .'year'] . '-' . $_REQUEST[$control_prefix .'month'] . "-01'";
      return "$sql_field >= $start_date and $sql_field <= $start_date + interval 1 month - interval 1 day";
    case "own":
      $start_date = "'" . $_REQUEST[$control_prefix .'start_date'] . "'";
      $end_date = "'" . $_REQUEST[$control_prefix .'end_date'] . "'";
      return "$sql_field >= $start_date and $sql_field <= $end_date";
    } 
    return period::get_recent_sql($sql_field, 'today');
  }
  
  function is_holiday($date='curdate()')
  {
    global $db;
    return $db->dup()->exists("select date from mukonin_tracing.holidays where date = $date");
  }
}

?>