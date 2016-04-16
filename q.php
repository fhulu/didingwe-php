<?php

require_once('db.php');
class q
{
  static function put($process, $args)
  {
    if ($process == 'put')
      throw new Exception("Cannot call q::put() recursively");

    global $db;
    $options = $db->read_one(
      "select name process, q, priority, retry_interval, max_attempts, max_period, success_regex, success_process, success_message
        from process where name = '$process'");
    if (is_null($options))
      throw new Exception("Queue process $process not defined on the database");

    $merged = merge_options($options, $args);
    $args = array_diff_assoc($args, $options);
    if (!$merged['q'])
      return q::process($name, $merged, $args);

    $user_message = $merged['message'];
    $merged['message'] = json_encode($args);
    $db->insert_array('q', $merged);
    return null;
  }

  static function process($name, $options, $args)
  {
    try {
      $result = call_user_func_array("q::$name", array($args));
      log::debug("RESULT: $result");
    }
    catch (Exception $ex) {
    }
    return $result;
  }

  static function post_http($options)
  {
    log::debug_json("HTTP POST", $options);
    $url = $options['url'];
    if (!isset($url)) {
      $url = $options['protocol']
            . "://" . $options['host']
            . ":" . $options['port']
            . "/" . $options['path'];
    }
    require_once('../common/curl.php');
    $curl = new curl();
    $post = $options['post'];
    return isset($options['post'])? $curl->post($url, $post): $curl->read($url);
  }

  static function send_sms($options)
  {
    $options['message'] = urlencode($options['message']);
    replace_fields($options, $options, true);
    return q::post_http($options);
  }

  static function send_email($options)
  {
    $headers = $options['headers'];
    $from = $options['from'];
    $to = $options['to'];
    $message = $options['message'];
    $subject = $options ['subject'];
    log::debug("SENDMAIL from $from to $to SUBJECT $subject");
    $headers['From'] = $from;
    $headers['Subject'] = $subject;
    $headers['To'] = $to;

    set_include_path("./common/pear");
    require_once "Mail.php";
    require_once("Mail/mime.php");
    $mime = new Mail_mime("\n");
    $mime->setHTMLBody($message);
    $message = $mime->get();
    $headers = $mime->headers($headers);
    $smtp = Mail::factory('smtp',  $options['smtp']);
    $result = $smtp->send($to, $headers, $message);
    restore_include_path();
    return $result;
  }

  static function rest_post($options)
  {
    $user = $options['username'];
    $password = $options['password'];
    require_once('../common/restclient.php');
    $api = new RestClient(['username'=>$user, 'password'=>$password]);

    $url = $options['url'];
    unset($options['url']);
    unset($options['username']);
    unset($options['password']);
    return $api->post($url, json_encode($options), ['Content-Type' => 'application/json']);
  }

}
