<?php

require_once("db_record.php");

class db_file_exception extends db_record_exception {};
class db_file extends db_record
{
  var $path;
  function __construct($db=null, $id=0)
  {
    parent::__construct($db, "mukonin_tracing.file", array("id"),
      array("name","compressed","mime","size","width","height","author",'subdir'));
 
    $this->keys['id'] = $id;
  }

  function release_data()
  {
  }


  function save($path=null, $compress=false, $subdir=null)
  {
    if (is_null($path)) $path = $this->path;

    $this->values['name'] = basename($path);
    $this->values['size'] = filesize($path);

    log::debug("Uploading file $path");
    $this->values[subdir] = $subdir;
    if(!is_null($subdir)) $subdir .= '/';
    $this->path = $subdir.$this->values['name'];
    copy($path, $this->path);
    log::debug("Copying file $path to $this->path");
    $this->keys[id] = $id = $this->insert();
    return $id;
  }

  function save_image($path, $compress=false, $subdir=null)
  {
    list($this->values['width'], $this->values['height']) = getimagesize($path);
    return $this->save($path, $compress, $subdir);
  }

  function upload($control_name, $compress=false, $subdir=null)
  {
    $temp_name = $_FILES[$control_name]['tmp_name'];
    if (!is_uploaded_file($temp_name))
      throw new db_file_exception("File $this->name too large for upload");

    $dest = '/tmp/'. $_FILES[$control_name]['name'];
    log::debug("Uploading $temp_name to $dest");
    move_uploaded_file($temp_name, $dest);

    $this->values['mime'] = $_FILES[$control_name]['type'];
    
    $id = $this->save($dest, $compress, $subdir);
    unlink($dest);
  }

  function save_to_dir($dest_dir="/tmp", $uncompress=false)
  {
    if (!$this->load_values("name,size,compressed,subdir")) return "";
    $values = &$this->values;
    $path = $values[subdir] . '/' . $values[name];
    return $path;
  }

  function show()
  {
    $this->load_values("name,size,mime,subdir");
    header("Content-length: ". $this->values['size']);
    header("Content-type: ". $this->values['mime']);
    $subdir = $this->values['subdir'];
    if ($subdir != '') {
      $file = fopen($subdir.'/'.$this->values[name], 'rb');
      $data = fread($file, $this->values[size]*2);
      fclose($file);
    }
    echo $data;
  }

  function download()
  {
    $this->load_values("name");

    $name = '"'.$this->values['name'].'"';
    header("Content-Disposition: attachment; filename=$name");
    $this->show();
  }

  static function show_by_id($id)
  {
    $file = new db_file(null, $id);
    $file->show();
  }

}

