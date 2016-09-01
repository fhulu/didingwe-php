<?php

class document_exception extends Exception {};
class document
{
  var $page;
  var $db;
  function __construct($page)
  {
    $this->page = $page;
    $this->db = $page->db;
  }

  function upload($control, $path, $id)
  {
    $page = $this->page;
    $control = $page->translate_context($control);
    $path = $page->translate_context($path);
    $id = $page->translate_context($id);
    $file_name = addslashes($_FILES[$control]["name"]);
    $temp_name = $_FILES[$control]['tmp_name'];
    if (!file_exists($path)) mkdir($path, 755, true);
    $path = "$path/$id-$file_name";
    if ($file_name == '' || !is_uploaded_file($temp_name) || !move_uploaded_file($temp_name, $path))
      return $page->error("Error uploading document $path. File may be too large");

    return ['path'=>$path, "mime"=>document::extension($path)];
  }

  static function view($path, $name, $media)
  {
    if (!file_exists($path))
      return $page->error("Document file not found. Please report to System Administrator");

    $size = filesize($path);
    $ext = document::extension($path);
    header("Content-Disposition: attachment; filename=\"$name.$ext\"");
    header("Content-length: $size");
    header("Content-type: $media");
    $file = fopen($path, 'rb');
    $data = fread($file, $size);
    fclose($file);
    echo $data;
  }

  static function extension($file)
  {
    $pos = strrpos($file, '.');
    return $pos===FALSE? '': substr($file, $pos+1);
  }
}
