<?php
require_once("log.php");

function at($array, $index, $default=null) {
  return $array[$index]??$default;
}

function GET($item) { return at($_GET, $item); }
function POST($item) { return at($_POST, $item); }
function REQUEST($item) { return at($_REQUEST, $item); }
function SESSION($item) { return at($_SESSION, $item); }
function null_at($array, $index) { return is_null(at($array,$index)); }
function valid_at($array, $index) { return !is_null(at($array,$index)); }

function set_valid(&$dest, $source, $index = null)
{
  if (is_null($source)) return $dest;
  if (is_null($index)) return $dest = $source;
  $val = $source[$index];
  if (!is_null($val)) $dest[$index] = $val;
}

function last($array)
{
  if (is_null($array)) return null;
  $length = is_array($array)?sizeof($array): strlen($array);
  return at($array, $length-1);
}


function null_merge($array1, $array2) {
  if (is_null($array2)) return $array1;
  if (!is_array($array2)) return $array2;
  if (head($array2) == '_reset') return tail($array2);
  return is_array($array1)? array_merge($array1, $array2): $array2;
}

function merge_to(&$array1, $array2)
{
  return $array1 = null_merge($array1, $array2);
}

function remove_nulls(&$array)
{
  if (!is_array($array)) return $array;
  foreach($array as $key=>$value) {
    if (is_null($value)) unset ($array[$key]);
  }
  return $array;
}

function replace_vars($str, $values, $callback=null, $value_if_unset=null) {

  $matches = [];
  // first check for $.variable replacement for variable as is, including arrays
  if (preg_match('/^\$\.(\w+)$/', $str, $matches)) { 
    $var = $matches[1];
    $value = at($values, $var, $str);
    
    // if callback is supplied call it and only return substitute value if callback result is false 
    return !$callback || $callback($value, $var) !== false? $value: $str;
  }

  if (!preg_match_all('/\$(?:(\w+)\b|\{(\w+)\})(\.\.\.)?/m', $str, $matches, PREG_SET_ORDER)) return  $str;

  foreach($matches as $match) {
    $key = at($match, 1);
    if (is_null($key)) $key = at($match, 2);
    if (is_null($key)) continue;
    $value = at($values, $key);
    if (is_null($value)) {
      if ($value_if_unset === null) continue;
      $value = $value_if_unset;
    }
    if (!$callback || $callback($value, $key) !== false) {
      if (isset($match[3]) && is_array($value)) $value = implode($value);
      if (is_string($value))
        $value = str_replace('$', '{%}',$value);
      $str = preg_replace('/\$(?:'.$key.'(\b)|\{'.$key.'\})(\.\.\.)?/',"$value$1", $str);
      if (is_string($value))
        $str = str_replace('{%}', '$',$str);
    }
  }
  return $str;
}

function replace_vars_except($str, $values, $exceptions)
{
  return replace_vars($str, $values, function($v, $key) use ($exceptions) {
    return !in_array($key, $exceptions, true);
  });
}

function is_assoc($array)
{
  if (is_null($array) || !is_array($array) || sizeof($array) == 0) return false;
  return !(bool)count(array_filter(array_keys($array), 'is_int'));
}

function normalize_array(&$array) {
  $prev_key = -1;
  $compacted = [];
  foreach($array as $key=>$value) {
    if (!is_numeric($key) || $key <= $prev_key) {
      $compacted = [];
      break;
    }
    $prev_key = $key;
    $compacted[] = $value;
  }
  if (sizeof($compacted) != 0) $array = $compacted;
}

function compress_array($array)
{
  $compressed = array();
  foreach($array as $key=>$value) {
    if ($value != '') return $array;
    $compressed[] = $key;
  }
  return $compressed;
}

function caught_error($errNo, $errStr, $errFile, $errLine) {
  $msg = "$errStr in $errFile on line $errLine";
  if ($errNo == E_NOTICE) return;
  throw new ErrorException($msg, $errNo);
//  echo $msg;
}

function caught_fatal(){

    $error = error_get_last();

    if($error)  { // && ($error['type'] & E_FATAL)){
      caught_error($error['type'], $error['message'], $error['file'], $error['line']);
    }

}
set_error_handler('caught_error');

register_shutdown_function('caught_fatal');


function merge_options() {
  $merge = function($options1, $options2) use(&$merge) {
    if (is_null($options2)) return $options1;
    if (is_string($options2) && strpos($options2, '...')===0) {
      $options2 = preg_split('/ *, */', substr($options2,3));
      if (!is_array($options1)) $options1 = [$options1];
    }
    if (!is_array($options1) || $options1 == $options2) return $options2;
    if (!is_array($options2)) return $options2;
    if (!is_assoc($options1) && !is_assoc($options2)) {
      if (head($options2) == '_reset') return tail($options2);
      return array_merge($options1, $options2);
    }
    $result = $options1;
    foreach($options2 as $key=>$value ) {
      if (array_key_exists($key, $result))
        $result[$key] = $merge($result[$key], $value);
      else
        $result[$key] = $value;
    }
    return $result;
  };

  $args = func_get_args();

  $result = array_shift($args);
  while(sizeof($args) > 0) {
    $next = array_shift($args);
    $result = $merge($result, $next);
  }
  return $result;
}

function choose_value(&$array)
{
  $args = func_get_args();
  array_shift($args);
  foreach ($args as $arg) {
    if ($array[$arg] != '') return $array[$arg];
  }
  return null;
}

function walk_recursive(&$array, $callback, $options = [], $level = 0) {

  // merge supplied options with default options with supplied options taking precedence
  $default_options = [
    'leaves_first' => true,       // indicates whether to walk to leaf nodes before applying callback
    'leaves_only'=> false,        // indicates whether to only apply callback on leaf nodes or all nodes
    'exclude' => [],              // ignore all keys specified here
    'done' => null,               // call back to be called at the end of walking a level
    'max_level' => PHP_INT_MAX    // maximum number of levels to process
  ];
  $options = array_merge($default_options, $options);

  // use options as variables
  extract($options);

  // return immediately if we are already at the last level
  if ($level >= $max_level)  return;

  // let's walk
  foreach($array as $key=>&$value) {

    // exclude keys in supplied exclude list
    if (in_array($key, $exclude, true)) continue;

    // do callback right away if at leaf level 
    if (!is_array($value)) {
      $callback($value, $key, $array, $level);      
      continue;
    }

    // walk down the tree first before callback
    if ($leaves_first) {
      if ($level < $max_level) walk_recursive($value, $callback, $options, $level+1);
      if (!$leaves_only) $callback($value, $key, $array, $level); 
    }

    // run callback first and if result is false don't continue traversing
    else if ($leaves_only || $callback($value, $key, $array, $level) !== false) {
      if ($level < $max_level) walk_recursive($value, $callback, $options, $level+1);
    }
  }
  
  // if supplied, call done callback at the end of each level
  if ($done) $done($array);
}

function walk_recursive_down(&$array, $callback, $done_callback = null) {
  walk_recursive($array, $callback, ['leaves_first' => false, 'done'=> $done_callback]);
}

function walk_leaves(&$array, $callback) {
  walk_recursive($array, $callback, ['leaves_only' => true]);
}

function assoc_element($element) {
  if (!is_array($element)) return [$element, null];
  if (empty($element)) return [null, null];
  foreach($element as $key=>$value) {};
  return [$key, $value];
}

function replace_fields(&$options, $context, $callback=null) {
  if (!is_array($options)) {
    $options = replace_vars($options, $context, $callback);
    return $options;
  }
  $replaced = false;
  walk_leaves($options, function(&$value) use(&$context, $callback, &$replaced) {
    $old = $value;
    $value = replace_vars($value, $context, $callback);
    $replaced = ($value !== $old);
  });
  return $replaced? replace_fields($options, $context, $callback): $options;
}

function replace_indices($str, $values)
{
  if (is_null($values)) $values = $_REQUEST;
  $i = 1;
  foreach($values as $value) {
    $str = str_replace('$'.$i, $value, $str);
    ++$i;
  }
  return $str;
}

function replace_field_indices(&$options, $values)
{
  array_walk_recursive($options, function(&$value) use(&$values) {
    $value = replace_indices($value, $values);
  });
}

function replace_keys($array, $key1, $key2)
{
    $keys = array_keys($array,null, true);
    $index = array_search($key1, $keys);

    if ($index !== false) {
        $keys[$index] = $key2;
        $array = array_combine($keys, $array);
    }

    return $array;
}

function find_assoc_element($array, $key)
{
  $index = 0;
  foreach($array as $element) {
    list($k, $value) = assoc_element($element);
    if ($k == $key) return array($value, $index);
    ++$index;
  }
  return null;
}

function expand_function($func) {
  $matches = [];
  if (!preg_match('/^(\w[\w.]+)(?:\((.*)\))?$/s', trim($func), $matches))
    throw new Exception("Invalid function specification --$func--");
  $name = $matches[1];
  $args = at($matches, 2);
  if (is_null($args)) return [$name,[]];
  if (!preg_match_all('/\w*(\(.*\)|[^,]+)/sm', trim($args), $matches))
    throw new Exception("Invalid function parameter specification --$func--");

  return [$name, $matches[0]];
}

function array_find(&$array, $callback)
{
  foreach ($array as $key=>&$value) {
    if ($callback($value, $key)) return $key;
  }
  return false;
}

function array_compact(&$array)
{
  if (!is_assoc($array))
    $array = array_values($array);
}

function load_yaml($file, $must_exist=false)
{
  if (!file_exists($file)) {
    if (!$must_exist) return null;
    throw new Exception ("File $file does not exist");
  }

  log::debug("YAML LOAD $file");
  $data = yaml_parse_file($file);
  if (is_null($data))
    throw new Exception ("Unable to parse file $file");
  return $data;
}

function array_remove_value($array, $value)
{
  $key = array_search($value, $value);
  if ($key === false) return $array;
  unset($array[$key]);
  return array_values($array);
}

function to_array($obj)
{
  $keys = array_slice(func_get_args(),1);
  $array = array();
  foreach($keys as $key) {
    $array[] = $obj[$key];
  }
  return $array;
}

function is_function($str)
{
  return preg_match('/(\w+::)?\w+\(.*\)$/', $str);
}

function echo_scripts($scripts, $template) {
  if (!$scripts) return;
  foreach($scripts as $script) {
    echo str_replace('$script', $script, $template);
  }
}

function implode_quoted($array, $separator=",", $quote="'")
{
  return $quote . implode($separator, $array) . $quote;
}

function on_null($val, $default)
{
  return is_null($val)? $default: $val;
}

function seems_utf8($str)
{
    $length = strlen($str);
    for ($i=0; $i < $length; $i++) {
        $c = ord($str[$i]);
        if ($c < 0x80) $n = 0; # 0bbbbbbb
        elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
        elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
        elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
        elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
        elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
        else return false; # Does not match any model
        for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
            if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                return false;
        }
    }
    return true;
}

/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * @param string $string Text that might have accent characters
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents($string) {
    if ( !preg_match('/[\x80-\xff]/', $string) )
        return $string;

    $string = str_replace('®', '(c)', $string);
    $string = str_replace('∙', '.', $string);
    $string = str_replace('´', '.', $string);
    $string = str_replace('±', '+/-', $string);
    $string = str_replace('⁃', '-', $string);

    if (seems_utf8($string)) {
        $chars = array(
        // Decompositions for Latin-1 Supplement
        chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
        chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
        chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
        chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
        chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
        chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
        chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
        chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
        chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
        chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
        chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
        chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
        chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
        chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
        chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
        chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
        chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
        chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
        chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
        chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
        chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
        chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
        chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
        chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
        chr(195).chr(183) => '.', chr(195).chr(185) => 'u',
        chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
        chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
        chr(195).chr(191) => 'y',
        // Decompositions for Latin Extended-A
        chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
        chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
        chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
        chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
        chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
        chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
        chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
        chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
        chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
        chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
        chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
        chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
        chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
        chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
        chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
        chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
        chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
        chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
        chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
        chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
        chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
        chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
        chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
        chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
        chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
        chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
        chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
        chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
        chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
        chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
        chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
        chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
        chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
        chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
        chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
        chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
        chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
        chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
        chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
        chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
        chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
        chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
        chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
        chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
        chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
        chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
        chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
        chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
        chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
        chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
        chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
        chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
        chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
        chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
        chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
        chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
        chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
        chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
        chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
        chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
        chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
        chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
        chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
        chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
        // Euro Sign
        chr(226).chr(130).chr(172) => 'E',
        // GBP (Pound) Sign
        chr(194).chr(163) => '');

        $string = strtr($string, $chars);
    } else {
        // Assume ISO-8859-1 if not UTF-8
        $chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
            .chr(159).chr(162).chr(165).chr(180).chr(181).chr(183).chr(192).chr(193).chr(194)
            .chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
            .chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
            .chr(211).chr(212).chr(213).chr(214).chr(215).chr(216).chr(217).chr(218)
            .chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
            .chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
            .chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
            .chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
            .chr(252).chr(253).chr(255);

        $chars['out'] = "EfSZszYcY'u.AAAAAACEEEEIIIINOOOOOOxUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

        $string = strtr($string, $chars['in'], $chars['out']);
        $double_chars['in'] = array(chr(140), chr(156), chr(169), chr(177), chr(188), chr(189),
            chr(190), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
        $double_chars['out'] = array('OE', 'oe', '(c)', '+-', '1/4', '1/2', '3/4', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
        $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }

    return $string;
}

function translate_special_chars($str)
{
  setlocale(LC_ALL, 'en_GB');
  return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', remove_accents($str));
}


function swap(&$x,&$y) {
  $tmp=$x;
  $x=$y;
  $y=$tmp;
}

// get array_keys for associative arrays otherwise get array values
function array_keys_first($array)
{
  if (is_assoc($array)) return array_keys($array);
  foreach($array as &$value) {
    list($value) = assoc_element($value);
  }
  return $array;
}


function array_exclude($array, $exclusions)
{
  if (empty($exclusions) || empty($array)) return $array;
  $result = [];
  foreach($array as $v) {
    if (!in_array($v, $exclusions)) $result[] = $v;
  }
  return $result;
}

function json_encode_array($value)
{
  if (!is_array($value)) return $value;
  $encoded = json_encode($value);
  if (!$encoded) return $value;
  return "'". addslashes($encoded) . "'";
}


function get_mime_type($filename) {
    $idx = explode( '.', $filename );
    $count_explode = count($idx);
    $idx = strtolower($idx[$count_explode-1]);

    $mimet = array(
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/msword',
        'xlsx' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.ms-powerpoint',


        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    );

    return isset( $mimet[$idx] )?  $mimet[$idx]: 'application/octet-stream';
 }

function flatten_array(array $a) {
    $r = [];
    array_walk_recursive($a, function($v, $k) use (&$r) {
      if (!is_array($v)) $r[] = $v;
    });
    return $r;
}

function decode_if_json($x) {
  if (!is_string($x) || !preg_match('/^({.*}|\[.*\])$/', $x)) return $x;
  $result = json_decode($x);
  return json_last_error() == JSON_ERROR_NONE? (array)$result: $x;
}

function decode_json_array(&$data) {
  array_walk($data, function(&$x) {
    $x = decode_if_json($x);
  });
}

function assoc_to_array($assoc, ...$names) {
  if (!$names)
    return array_values($assoc);
  $result = [];
  foreach($names as $name) {
    $result[] = at($assoc, $name);
  }
  return $result;
}

function email_to_array($str) {
  $matches = [];
  if (!preg_match('/([\w.\s-]+)<(\w+@\w+(?:\.\w+)+)>|([\w+.-]*@[\w-]+(?:\.[\w-]+)+)/', $str, $matches)) 
    return [];
  return sizeof($matches) == 3? [$matches[2], $matches[1] ]: [$matches[3] ] ;
}

function preg_match_each($pattern, $str, $callback) {
  $matches = [];
  if (!preg_match_all($pattern, $str, $matches, PREG_SET_ORDER)) return $str;
  $index = 0;
  foreach($matches as $match) {
    if ($callback($match, $index++) === false) break;
  }
}

// extends explode(delim, str) to be use like [x,y,z] = explode_safe(delim, str, 3, default) 
// for backward compatibility with php ver < 8
// so if exploding str result in an array whose less than the minimum quantity, the resultant array 
// is padded with a default value
function explode_safe(string $delim, string $str, int $min_quantity=2, string $default=null) {
  assert('$min_quantity >= 0');
  $array = explode($delim, $str);
  return array_pad($array, $min_quantity, $default);
}

// return first element of an array
function car($array) {
  return $array[0]??null;
}

// return first element of an array
function head($array) {
  return $array[0]??null;
}

// return last element of an array
function tail($array) {
  return array_slice($array, 1);
}
