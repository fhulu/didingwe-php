<?php

class radio
{

  static function add($name, $value, $script=null)
  {
    $checked =  $_REQUEST[$name] == $value? " checked":"";
    $script = is_null($script)? '': "onclick=$script";
    echo "<input type='radio' name='$name' value='$value'$checked $script />\n";
  }
}
?>
