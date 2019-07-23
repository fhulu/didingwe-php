<?php
class sms {
  var $page;

  function __construct($page) {
    $this->page = $page;
  }
  function send($options) {

    $this->page->merge_fields($options);
    $this->page->replace_fields( $options['message']);
    $options['message'] = urlencode($options['message']);
    $this->page->merge_context('sms.send', $options);
    replace_fields($options, $options, true);
    $options['msisdn'] = urlencode($options['cellphone']);
    replace_fields($options, $options, true);
    $url = $options['url'];
    if (!isset($url)) {
      $url = $options['protocol']
            . "://" . $options['host']
            . ":" . $options['port']
            . "/" . $options['path'];
    }
    require_once('../didi/curl.php');
    $curl = new curl();
    return $curl->read($url);
  }
}
