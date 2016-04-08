<?php

require_once('session.php');

class document_exception extends Exception {};
class document
{
  static function upload($options)
  {
    list($control, $allowed_types, $user_id, $partner_id, $path, $ignore_date) =
      to_array($options,'control', 'types', 'user_id', 'partner_id', 'path', 'ignore_date');
    $file_name = addslashes($_FILES[$control]["name"]);
    $type = document::extension($file_name);
    log::debug("about to upload $file_name from $control of type $type");

    if (!is_array($allowed_types)) $allowed_types = explode(',', $allowed_types);
    $type = strtoupper($type);
    array_walk($allowed_types, function(&$val) { $val = strtoupper($val); });
    if (!in_array($type, $allowed_types))
      return "File $file_name is not allowed. Please upload file of type ". implode(' or ', $allowed_types);

    if ($file_name == '')
      return  "Error uploading document of type $type. File may be too large";

    global $db;


    $sql = "INSERT INTO document(partner_id,user_id,filename,type,status)
            values($partner_id,$user_id,'$file_name','$type','pend')";

    $id = $db->insert($sql);
    $file_name = str_replace("/[\' \s]'/", '-', $_FILES[$control]["name"]);
    if ($path=='') $path = '.';
    if (!$ignore_date) $path .= '/' . date('Ymd');
    if (!file_exists($path)) mkdir($path, 755, true);
    $path = "$path/$id-$file_name";
    log::debug("Uploading file $path");
    $temp_file = $_FILES[$control]['tmp_name'];

    if (!is_uploaded_file($temp_file))
      return "Error uploading document. File may be too large";

    if (!move_uploaded_file($_FILES[$control]["tmp_name"], $path))
      return "Error uploading document of type $type. File may be too large";

    $db->exec("update document set status = 'done', path='$path' where id = '$id'");
    return array($id,$file_name);
  }

  static function optional_upload($control, $type, $partner_id)
  {
    if ($_FILES[$control]["name"] != '')
      document::upload ($control, $type, $partner_id);
  }

  static function view($id)
  {
    global $db;
    list($file_name, $desc) = $db->read_one("select path, description from document d, document_type dt
      where d.type = dt.code and d.id = $id");
    if (!file_exists($file_name)) {
      echo "Document file not found. Please report to System Administrator";
      return;
    }
    $size = filesize($file_name);
    $ext = document::extension($file_name);
    header("Content-Disposition: attachment; filename=\"$desc.$ext\"");
    header("Content-length: ". $size);
    // --- get mime type end --
    header("Content-type: ". document::mimetype($file_name));
    $file = fopen($file_name, 'rb');
    $data = fread($file, $size);
    fclose($file);
    echo $data;
  }

  static function extension($file)
  {
    $pos = strrpos($file, '.');
    return $pos===FALSE? '': substr($file, $pos+1);
  }

  static function mimetype($value) {

    $ct['htm'] = 'text/html';
    $ct['html'] = 'text/html';
    $ct['txt'] = 'text/plain';
    $ct['asc'] = 'text/plain';
    $ct['bmp'] = 'image/bmp';
    $ct['gif'] = 'image/gif';
    $ct['jpeg'] = 'image/jpeg';
    $ct['jpg'] = 'image/jpeg';
    $ct['jpe'] = 'image/jpeg';
    $ct['png'] = 'image/png';
    $ct['ico'] = 'image/vnd.microsoft.icon';
    $ct['mpeg'] = 'video/mpeg';
    $ct['mpg'] = 'video/mpeg';
    $ct['mpe'] = 'video/mpeg';
    $ct['qt'] = 'video/quicktime';
    $ct['mov'] = 'video/quicktime';
    $ct['avi'] = 'video/x-msvideo';
    $ct['wmv'] = 'video/x-ms-wmv';
    $ct['mp2'] = 'audio/mpeg';
    $ct['mp3'] = 'audio/mpeg';
    $ct['rm'] = 'audio/x-pn-realaudio';
    $ct['ram'] = 'audio/x-pn-realaudio';
    $ct['rpm'] = 'audio/x-pn-realaudio-plugin';
    $ct['ra'] = 'audio/x-realaudio';
    $ct['wav'] = 'audio/x-wav';
    $ct['css'] = 'text/css';
    $ct['zip'] = 'application/zip';
    $ct['pdf'] = 'application/pdf';
    $ct['doc'] = 'application/msword';
    $ct['docx'] = 'application/msword';
    $ct['bin'] = 'application/octet-stream';
    $ct['exe'] = 'application/octet-stream';
    $ct['class']= 'application/octet-stream';
    $ct['dll'] = 'application/octet-stream';
    $ct['xls'] = 'application/vnd.ms-excel';
    $ct['xlsx'] = 'application/vnd.ms-excel';
    $ct['ppt'] = 'application/vnd.ms-powerpoint';
    $ct['wbxml']= 'application/vnd.wap.wbxml';
    $ct['wmlc'] = 'application/vnd.wap.wmlc';
    $ct['wmlsc']= 'application/vnd.wap.wmlscriptc';
    $ct['dvi'] = 'application/x-dvi';
    $ct['spl'] = 'application/x-futuresplash';
    $ct['gtar'] = 'application/x-gtar';
    $ct['gzip'] = 'application/x-gzip';
    $ct['js'] = 'application/x-javascript';
    $ct['swf'] = 'application/x-shockwave-flash';
    $ct['tar'] = 'application/x-tar';
    $ct['xhtml']= 'application/xhtml+xml';
    $ct['au'] = 'audio/basic';
    $ct['snd'] = 'audio/basic';
    $ct['midi'] = 'audio/midi';
    $ct['mid'] = 'audio/midi';
    $ct['m3u'] = 'audio/x-mpegurl';
    $ct['tiff'] = 'image/tiff';
    $ct['tif'] = 'image/tiff';
    $ct['rtf'] = 'text/rtf';
    $ct['wml'] = 'text/vnd.wap.wml';
    $ct['wmls'] = 'text/vnd.wap.wmlscript';
    $ct['xsl'] = 'text/xml';
    $ct['xml'] = 'text/xml';

    $extension = document::extension($value);

    if (!$type = $ct[strtolower($extension)]) $type = 'text/html';

    return $type;
  }

  static function uploaded_check()
  {
    global $db, $session;
    $result = array();
    $statuses = $db->read_column("select status from document where session_id = '$session->id' and status != 'rese'");
    if (sizeof($statuses) == 0) {
      $result['status'] = 'busy';
    }
    else if ($db->exists("select id from document where session_id = '$session->id' and status = 'busy'")) {
      $result['status'] = 'busy';
    }
    else {
      $row = $db->read_one("select filename, status from document
              where session_id = '$session->id' and status in ('inva','perm')");
      if ($row == null) {
        $result['status'] = 'done';
      }
      else {
        $db->exec("update document set status='reset' where session_id = '$session->id'");
        $result['status'] = $row[0];
        $result['file'] = $row[1];
      }
    }
    echo json_encode($result);
  }

}
