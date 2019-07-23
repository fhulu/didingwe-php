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
      return q::process($process, $merged, $args);

    $user_message = $merged['message'];
    $merged['message'] = json_encode($args);
    $id = $db->insert_array('q', $merged, ['create_time'=>'/now()']);
    q::wake();
    return true;
  }


  private static function get_msg_key()
  {
    return ftok('q.conf', 'R');
  }

  private static function get_msg_q()
  {
    return msg_get_queue(q::get_msg_key(), 0666 | IPC_CREAT);
  }


  static function signal_handler($signal)
  {
  }

  static function wake()
  {
    $msg_id = q::get_msg_q();
    try {
      if (!msg_send($msg_id,1, "wake", true, false, $error_code))
        log::warn("Error sending to message queue with error code $error_code");
    }
    catch (Exception $e) {
      log::error("Exception sending message queue: ". $e->getMessage());
    }
  }

  static function start()
  {
    log::init("q", log::DEBUG);

    if (msg_queue_exists(q::get_msg_key())) {
      log::warn("Q already running - will attempt wakeup");
      q::wake();
      return;
    }
    $msg_id = q::get_msg_q();

    pcntl_signal(SIGALRM, "q::signal_handler", true);
    global $db;
    $sql = "
      select SQL_CALC_FOUND_ROWS id, process, message, success_regex, success_process, success_message,
        retry_interval - timestampdiff(second, ifnull(post_time,create_time), now()) retry_interval,
        (isnull(post_time) or now() >= post_time + interval retry_interval second) is_due
      from q
      where status in ('pend', 'fail')
        and (max_attempts = 0 or attempts < max_attempts)
        and (max_period = 0 or now() < create_time + interval max_period second)
      order by priority, create_time";

    do {
      $file = fopen("q.conf", 'r');
      $config = json_decode(stripslashes(fgets($file)), true);
      fclose($file);
      $batch_size = $config['batch_size'];
      $delay = (int)$config['delay'];
      do {
        $start_time = time();
        $rows = $db->read($sql, MYSQLI_ASSOC, $batch_size);
        foreach($rows as $row) {
          $row['q'] = true;
          if ($row['is_due'])
            q::process($row['process'], $row, json_decode($row['message'], true));
          $retry_interval = $row['retry_interval'] - (time() - $start_time);
          if ($delay > $retry_interval) $delay = $retry_interval;
        }
        msg_receive ($msg_id, 0, $type, 32, $msg, true, MSG_IPC_NOWAIT);
      } while ( $db->row_count() > sizeof($rows) && $msg != 'stop');

      if ($msg == 'stop') break;
      if ($delay > 0) {
        pcntl_alarm($delay);
        $sleep_time = time();
        log::debug("SLEEP for $delay seconds");
        msg_receive ($msg_id, 0, $type, 32, $msg);
        $elapsed  = time() - $sleep_time;
        log::debug("WOKEN after $elapsed seconds");
      }
    } while ($msg != 'stop');
    log::debug("STOPPED");
    q::kill();
  }

  static function stop()
  {
    msg_send(q::get_msg_q(), 1, 'stop');
  }

  static function kill()
  {
     msg_remove_queue(q::get_msg_q());
  }

  private static function update_db($options, $status, $time)
  {
    if (!$options['q']) return;
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
      $options['response'] = $result;
      if (preg_match('/'. $options['success_regex'] . '/', $result))
        return q::process_success($options);
    }
    catch (Exception $ex) {
      log::debug("EXCEPTION: ". $ex->getMessage());
    }
    q::process_failure($options, $result);
  }

  private static function process_success($options)
  {
    q::update_db($options, 'done', 'response_time', 'response');
    $next_process = $options['success_process'];
    $response = $options['response'];
    if ($next_process == '') return $response;
    return call_user_func_array("q::$next_process", array($response));
  }


  private static function process_failure($options)
  {
    q::update_db($options, 'fail', 'response_time', 'response');
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
    require_once('../didi/curl.php');
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

  static function rest_post($options)
  {
    $user = $options['username'];
    $password = $options['password'];
    require_once('../didi/restclient.php');
    $api = new RestClient(['username'=>$user, 'password'=>$password]);

    $url = $options['url'];
    unset($options['url']);
    $result =  $api->post($url, json_encode($options), ['Content-Type' => 'application/json']);
    return $api->raw_response;
  }

  static function decode_rest($response)
  {
    $matches = [];
    if (!preg_match('/^HTTP.*[\r\n][\r\n]({.*})$/s', $response, $matches)) {
      log::warn("Unable to decode REST response $response");
      return null;
    }
    $decoded = json_decode($matches[1], true);
    log::debug_json("DECODED REST", $decoded);
    return $decoded;
  }
}
