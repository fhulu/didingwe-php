<?php

class curl_exception extends Exception {};

class curl
{
  var $session;
  function __construct()
  {
    $this->session = curl_init();
  }

  function __destruct()
  {
    curl_close($this->session);
  }

  function read($url, $limit=0)
  {
    log::debug("CURL Read $url");
    curl_setopt($this->session, CURLOPT_URL, $url);
    if ($limit > 0)
	     $range = "0-$limit";
	  else
      $range = "0-";
  	curl_setopt ($this->session, CURLOPT_HTTPHEADER, array("Range: $range"));
    curl_setopt($this->session, CURLOPT_RETURNTRANSFER, 1);

    return curl_exec($this->session);
  }

  function download($url, $dest_path=null)
  {
    curl_setopt($this->session, CURLOPT_URL, $url);
    log::debug("CURL Download $url");
    if (is_null($dest_path)) $dest_path = "/tmp/" . basename($url);
    $file = fopen($dest_path, "w");
    curl_setopt($this->session, CURLOPT_FILE, $file);
    if (!curl_exec($this->session)) {
      fclose($file);
      return null;
    }
    fclose($file);
    return $dest_path;
  }

  function save($url, $dest_path=null)
  {
    return $this->download($url, $dest_path);
  }

  function show($url)
  {
    echo $this->read($url);
  }

  function get_last_mime()
  {
    return curl_getinfo($this->session, CURLINFO_CONTENT_TYPE);
  }

  function post($url, $fields)
  {
    curl_setopt($this->session,CURLOPT_URL, $url);
    curl_setopt($this->session,CURLOPT_POST, count($fields));

    $values = [];
    foreach($fields as $key=>$value) {
      $values[] = "$key = " . urlencode($value);
    }
    curl_setopt($this->session,CURLOPT_POSTFIELDS, implode('&', $values));
    return curl_exec($ch);
  }
};
