<?php

use Atrox\Async;

final class Curl {

  private $multi;
  private $tasks = array(); // handle id => [url, callback]

  function __construct() {
    $this->multi = curl_multi_init();
  }

  function __destruct() {
    curl_multi_close($this->multi);
  }

  function get($url) {
    return function ($cb) use ($url) {
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_ENCODING, '');
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 8);
      curl_setopt($curl, CURLOPT_TIMEOUT, 40);
      $code = curl_multi_add_handle($this->multi, $curl);
      if ($code === 0) {
        $this->tasks[(int) $curl] = [$url, $cb];
        while (curl_multi_exec($this->multi, $running) == CURLM_CALL_MULTI_PERFORM) {}
      } else {
        curl_multi_remove_handle($this->multi, $curl);
        $cb(null, new \Exception(curl_multi_strerror($code))); // maybe should throw directly
      }
    };
  }

  private function step($timeout = 0.01) {
    curl_multi_select($this->multi, $timeout);
    while(curl_multi_exec($this->multi, $running) == CURLM_CALL_MULTI_PERFORM) {}

    while ($info = curl_multi_info_read($this->multi)) {
      if ($info['msg'] == CURLMSG_DONE) {
        $code = $info['result'];
        $curl = $info['handle'];
        list($url, $cb) = $this->tasks[(int) $curl];

        if ($code === CURLE_OK) {
          $content = curl_multi_getcontent($curl);
          unset($this->tasks[(int) $curl]);
          curl_multi_remove_handle($this->multi, $curl);
          $cb($content, null);
        } else {
          unset($this->tasks[(int) $curl]);
          curl_multi_remove_handle($this->multi, $curl);
          $cb(null, new \Exception(curl_strerror($code)));
        }

      } else {
        var_dump("This should never happen, but who em I to judge!");
        var_dump($info);
      }
    }
  }

  function loop() {
    while (true)
      $this->step();
  }
}


function followersSync() {
  $curl = new Curl();
  $profile = $curl->getSync(profileOf('@kaja47'));

  $ids = [];
  $cursor = '-1';
  do {
    $fs = $curl->getSync(followersOf($profile->id, $cursor));
    $ids = array_merge($ids, $fs->ids);
    $cursor = $fs->next_cursor_str;
  } while ($cursor != "0");

  yield [$profile, $ids];
}


function followersCallbacks() {
  $curl = new Curl();

  $q = $curl->get(profileOf('@kaja47'));
  $q(function ($profile, $err) use ($curl) {
    $recur = function ($ids, $cursor) use ($profile, &$recur, $curl) {
      $q = $curl->get(followersOf($profile->id, $cursor));
      $q(function ($fs, $err) use ($ids, $profile, $curl) {
        $ids = array_merge($ids, $fs->ids);
        $cursor = $fs->next_cursor_str;
        if ($cursor != "0")
          $recur($ids, $cursor);
        else
          doSomethingWithResult([$profile, $ids]);
      });
      $recur([], '-1');
    };
  });
}

function followersYield() {
  $curl = new Curl();
  $profile = (yield $curl->get(profileOf('@kaja47')));

  $ids = [];
  $cursor = '-1';
  do {
    $fs = (yield $curl->get(followersOf($profile->id, $cursor)));
    $ids = array_merge($ids, $fs->ids);
    $cursor = $fs->next_cursor_str;
  } while ($cursor != "0");

  doSomethingWithResult([$profile, $ids]);
}



function crawlCallbacks() {
  $curl = new Curl();
  $q = $curl->get("http://api.4chan.org/boards.json");
  $q(function ($json, $err) use ($curl) {
    foreach (json_decode($json)->boards as $board) {
      $letter = $board->board;
      $q = $curl->get("http://api.4chan.org/$letter/threads.json");
      $q(function ($json, $err) use ($letter, $curl) {
        foreach (json_decode($json) as $page) {
          foreach ($page->threads as $thread) {
            $id = $thread->no;
            $q = $curl->get("http://api.4chan.org/$letter/res/$id.json");
            $q(function ($json, $err) use ($letter, $id) {
              echo "/$letter/$id\n";
              foreach (json_decode($json)->posts as $post) {
              if(preg_match('~skype~i', $post->com))
                echo "https://boards.4chan.org/$letter/res/$id\n";
              }
            });
          }
        }
      });
    }
  });
  $curl->loop();
}
//*/


function runCallbacks($f) {
  $gen = ($f instanceof \Closure) ? $f() : $f;

  $recur = function ($success, $exception) use ($gen, &$recur) {
    $x = ($exception !== null) ?  $gen->throw($exception) : $gen->send($success);
    if ($gen->valid()) $x($recur);
  };

  try {
    $x = $gen->current();
    $x($recur);
  } catch (Exception $e) {
    $recur(null, $e);
  }
}


$curl = new Curl();
runCallbacks(function () use ($curl) {
  $json = (yield $curl->get("http://api.4chan.org/boards.json"));
  foreach (json_decode($json)->boards as $board) {
    $letter = $board->board;
    $json = (yield $curl->get("http://api.4chan.org/$letter/threads.json"));
    foreach (json_decode($json) as $page) {
      foreach ($page->threads as $thread) {
        $id = $thread->no;
        $json = (yield $curl->get("http://api.4chan.org/$letter/res/$id.json"));
        echo "/$letter/$id\n";
        foreach (json_decode($json)->posts as $post) {
          if(preg_match('~skype~i', $post->com))
            echo "https://boards.4chan.org/$letter/res/$id\n";
        }
      }
    }
  }
});
$curl->loop();
//*/

// par yield
