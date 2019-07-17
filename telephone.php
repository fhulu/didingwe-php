<?php

require_once('../didi/db_record.php');



class telephone extends db_record
{
  static $masks = array(
    'Cell' => '07[1234689]|08[1-4]',
    'Telkom' => '0[1-6]\d{8,}',
    'Premium' => '08[567]'
  );

 static $msisdn_masks = array(
    'Vodacom' => '082|07[269]|071[1-6]|075[56]',
    'MTN' => '0[78]3|0778|071[0789]|078',
    'CellC' => '0[78]4',
    'Telkom' => '081'
  );


  function __construct($db, $values=null)
  {
    $values['date_updated'] = $values['date'];
    parent::__construct($db, "mukonin_tracing.telephone",
      array("number","id_number"), array("type","source_id","date_washed","date_updated", 'source_id'), $values);
  }

  static function get_type($number, $type)
  {
    if (telephone::valid_msisdn($number)) return 'c';
    return $type[0]=='c'?'h':$type[0];
  }

  static function match($number, $masks=null, $matches=null)
  {
    if (is_null($masks))
      $mask = array_merge(&telephone::$masks);
    else
      $mask = array_merge($masks);

    $mask = implode("|", $mask);
    $mask = "/\b($mask)[0-9]+/";
    return preg_match_all($mask, $number, $matches);
  }

  static function match_msisdn($number, $matches)
  {
    return telephone::match($number, telephone::$msisdn_masks, $matches);
  }


 static function valid($number, $masks=null)
 {
    if (!preg_match('/^[0-9]{5}/', $number)) return false;

    if (is_null($masks)) $masks = telephone::$masks;

    foreach($masks as $mask) {
      if (preg_match("/^($mask)/", $number)) return true;
    }
    return false;
  }

  static function set_number($values, $type)
  {
    $number = telephone::localise($values['number']);
    if ($number == "") return false;
    if (!telephone::valid($number)) return false;

    $values['type'] = telephone::get_type($number, $type);
    $matches = array();
    preg_match('/\d+/', $number, $matches);
    $values['number'] = $matches[0];
    return true;
  }

  static function valid_msisdn($number)
  {
    return telephone::valid($number, telephone::$msisdn_masks);
  }

  static function localise($x)
  {
    if ($x == "") return "";

    $x  = str_replace('+27', '0', $x);
    if ($x[0] != '0' && $x[0] != '+') $x = '0' . $x;
    return trim($x);
  }


  static function can_sms($x)
  {
    if (strlen($x) < 10) return false;
    $masks = telephone::$msisdn_masks;
    return preg_match("/^(". $masks['CellC'] . ")/", $x)
     || preg_match("/^(". $masks['MTN'] . ")/", $x)
     || preg_match("/^(". $masks['Vodacom'] . ")/", $x)
     || preg_match("/^(". $masks['Telkom'] . ")/", $x);
  }

  static function can_mms($x)
  {
    $masks = telephone::$msisdn_masks;
    return preg_match("/^(". $masks['MTN'] . ")/", $x)
     || preg_match("/^(". $masks['Vodacom'] . ")/", $x);
  }

  static function commit($db, $values, $type)
  {
    if(!telephone::set_number($values, $type)) return;
    $tel = new telephone($db, $values);
    $tel->save();
    $number = $tel->keys['number'];
    $date_updated = $values[date];
    $date_washed = $values[date_washed];
    $id_number = $values[id_number];
    $source_id = $values[source_id];
    $db->exec("update mukonin_tracing.telephone set date_washed='$date_washed', source_id = $source_id
      where id_number = '$id_number' and number = '$number' and date_washed < '$date_washed'");
    $db->exec("update mukonin_tracing.telephone set date_updated='$date_updated', source_id = $source_id
      where id_number = '$id_number' and number = '$number' and date_updated < '$date_updated'");
    $db->exec("update mukonin_tracing.telephone set source_id = $source_id
      where id_number = '$id_number' and number = '$number' and source_id = 0");
  }



  static function commit_all($db, $values)
  {
    $date_washed = $values[batch_date];
    $id_number = $values[id_number];
    $source_id = $values[source_id];

    foreach( array("home_tel", "work_tel", "cell") as  $type) {
      if (!isset($values[$type])) continue;

      if (isset($values[$type][0])) {
        foreach($values[$type] as $record) {
          $record[source_id] = $source_id;
          $record[date_washed] = $date_washed;
          $record[id_number] = $id_number;
          telephone::commit($db, $record, $type);
        }
      }
      else {
        $record = $values[$type];
        $record[source_id] = $source_id;
        $record[date_washed] = $date_washed;
        $record[id_number] = $id_number;
        telephone::commit($db, $record, $type);
      }
    }
  }

  static function get_numbers($db, $id_number)
  {
    return $db->read("select number from mukonin_tracing.telephone where id_number=$id_number");
  }

  static function get_cell_numbers($db, $id_number, $surname, $long_name)
  {
    $surname = addslashes(trim(strtoupper($surname)));
    $long_name = addslashes(trim(strtoupper($long_name)));
    return $db->read_column("select number from mukonin_tracing.telephone t, mukonin_tracing.person p
      where p.id_number='$id_number' and (p.surname sounds like '$surname' or '$long_name' rlike p.surname or '$surname'='') and p.id_number = t.id_number
       and type='c' and (t.date_washed > (curdate() - interval 12 month) or t.date_updated > (curdate() - interval 12 month))
       and t.date_updated not like '000%'");
//       and t.date_updated = (select max(date_updated) from mukonin_tracing.telephone where number = t.number and type='c')");
  }

  static function has_cell_numbers($db, $id_number)
  {
    return $db->exists("select number from mukonin_tracing.telephone where id_number='$id_number' and type='c'");
  }

  static function has_sms_numbers($db, $id_number, $surname, $long_name)
  {
    $numbers = telephone::get_cell_numbers($db, $id_number, $surname, $long_name);
    foreach($numbers as $number)
      if (telephone::can_sms($number)) return true;
    return false;
  }

  static function is_duplicate($db, $number)
  {
    if (!telephone::valid($msisdn)) return false;
    list($users) = $db->read("select count(*) from mukonin_tracing.telephone where number = '$number'");
    var_dump($users);
    return $users > 1;
  }
}
