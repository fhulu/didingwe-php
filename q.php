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
    $id = $db->insert_array('q', $merged);
    q::wake($id);
    return null;
  }

  private static function get_msg_q()
  {
    return msg_get_queue(ftok('q.conf', 'R'), 0666 | IPC_CREAT);
  }

  static function wake($id=1)
  {
    $msg_id = q::get_msg_q();

    if (!msg_send($msg_id, $id, "wake", true, true, $error_code))
      log::error("Error sending to message queue with error code $error_code");
  }

  static function run()
  {
    log::init("q", log::DEBUG);

    $msg_id = q::get_msg_q();

    global $db;
    $sql = "
      select SQL_CALC_FOUND_ROWS id, process, message, success_regex, success_process, success_message,
        timestampdiff(second, ifnull(post_time, create_time), now())
      from q
      where status in ('pend', 'fail') and attempts < max_attempts
        and now() < create_time + interval max_period second
        and (isnull(post_time) or now() >= post_time + interval retry_interval second)
      order by priority, create_time";

    do {
      $file = fopen("q.conf", 'r');
      $config = json_decode(stripslashes(fgets($file)), true);
      fclose($file);
      $batch_size = $config['batch_size'];
      do {
        $rows = $db->read($sql, MYSQLI_ASSOC, $batch_size);
        foreach($rows as $row) {
          q::process($row['process'], $row, json_decode($row['message'], true));
        }
        msg_receive ($msg_id, 0, $type, 32, $msg, true, MSG_IPC_NOWAIT);
      } while ( $db->row_count() > sizeof($rows) && $msg != 'stop');

      if ($msg != 'stop')
        msg_receive ($msg_id, 0, $type, 32, $msg);
    } while ($msg != 'stop');
    log::debug("STOPPED");
  }

  static function stop()
  {
    msg_send(q::get_msg_q(), 1, 'stop');
  }

  private static function update_db($options, $status, $time)
  {
    global $db;
    $args = func_get_args();
    array_splice($args, 0, 3, ['q', $options, 'id', ['status'=>"$status"], ["$time"=>'/now()']]);
    call_user_func_array(array($db, 'update_array'), $args);
  }

  private static function process($name, $options, $args)
  {
    try {
      q::update_db($options, 'busy', 'post_time', ['attempts'=>'/attempts+1']);
      $result = call_user_func_array("q::$name", array($args));
      log::debug("RESULT: $result");
      if (preg_match('/'.$options['success_regex'] . '/', $result))
        return q::process_success($opttions, $result);
    }
    catch (Exception $ex) {
      log::debug_json("EXCEPTION:", $ex);
    }
    q::process_failure($options, $result);
  }

  private static function process_success($options, $result)
  {
    q::update_db($options, 'done', 'response', 'response');
  }


  private static function process_failure($options, $result)
  {
    q::update_db($options, 'fail', 'response', 'response');
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
