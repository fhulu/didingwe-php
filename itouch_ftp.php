<?php

require_once("ftp.php");

class itouch_ftp extends ftp
{
  var $msisdns;
  var $fhandle;
  var $fpath;
  var $dest_dir;
  
  
  function start_file($name_prefix)
  {
    $this->fpath = tempnam("/tmp", $name_prefix);
    $this->fhandle = ex(fopen($this->fpath, "w"));
    ex(fputs($this->fhandle, "[BOF]\n"));
  }
  
  function submit()
  {
    ex(fputs($this->fhandle, "\n[EOF]\n"));
    fclose($this->fhandle);
    $this->put($this->fpath, $this->dest_dir);
    unlink($this->fpath);
  }
  
  function write_tag($tag_name, $value)
  {
    log::debug("$tag_name: $value\n");
    ex(fputs($this->fhandle, "$tag_name: $value\n"));
  }  

  function write_msisdn($msisdn)
  {
    $this->write_tag("Cellphone", $msisdn);
  }  
  
  function write_ref($msisdn)
  {
    $this->write_tag("Reference", $msisdn);
  }  

  function write_mms($msisdn)
  {
    $this->write_tag("MMS", $msisdn);
  } 
 
  function write_msg($message)
  {
    log::debug($message);
    ex(fputs($this->fhandle, $message));
  }
 /* 
  function mms_reply($msisdn, $photo_idx, $photo_count)
  {
    $this->start_file("mms-reply-");
    $this->write_msisdn($msisdn);
    $this->write_tag('Source', '38611');
    ++$photo_idx;
    $available  = $photo_count - $photo_idx;
    $photos = $available==1?"photo":"photos";
    if ($photo_count == 1) 
      $this->write_msg("Photo MMS to follow shortly. If photo does not display correctly call 087 944 0827.");
    else
      $this->write_msg("Photo MMS to follow shortly. If does not display correctly call 087 944 0827. $available more $photos available, reply or sms PHOTO to 38611.");

    $this->submit();
  }
  
  function mms_reply_notfound($msisdn)
  {
    $this->start_file("mms-reply-");
    $this->write_msisdn($msisdn);
    $this->write_tag('Source', '38611');
    $this->write_msg("Sorry, we don't have photo records for this cell number. Call 087 944 0827 for enquiries or reply with ID number to 38611.\n");
    $this->submit();
  }

  function send_mms($msisdn, $photo_idx, $photo_count, $notice_no, $path)  
  {
    $this->put($path, "/MMS");

    $this->start_file("mms-photo-");
    $this->write_msisdn($msisdn);
    $photo_idx++;
    $this->write_ref("Photo $photo_idx of $photo_count. Notice No.: $notice_no");
    $this->write_tag('Source', '38611');
    $this->write_mms(basename($path));
    $this->submit();
  }

*/
}
?>  


