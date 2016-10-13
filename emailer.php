<?php

class emailer
{
  var $page;
  var $mime;
  function __construct($page)
  {
    $this->page = $page;
    set_include_path("./common/pear");
    require_once "Mail.php";
    require_once("Mail/mime.php");
    $this->mime = new Mail_mime("\n");
    restore_include_path();
}

  function send($options)
  {
    $this->page->merge_context('emailer.send', $options);
    $headers = $options['headers'];
    $from = $options['from'];
    $to = $options['to'];
    $message = $options['message'];
    $subject = $options ['subject'];
    log::debug_json("SENDMAIL from $from to $to SUBJECT $subject", $options);
    $headers['From'] = $from;
    $headers['Subject'] = $subject;
    $headers['To'] = $to;

    $mime = $this->mime;
    set_include_path("./common/pear");
    $mime->setHTMLBody($message);
    $message = $mime->get();
    $headers = $mime->headers($headers);
    $smtp = Mail::factory('smtp',  $options['smtp']);
    $result = $smtp->send($to, $headers, $message);
    restore_include_path();
    return $result;
  }
}
