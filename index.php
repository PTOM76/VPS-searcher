<?php
require_once "./secret.ini.php"

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);

if (isset($_GET['q'])) {
  $analytics = "./data/analytics/" . date("Y-m-d") . ".txt";
  $data = '';
  if (file_exists($analytics))
    $data = file_get_contents($analytics);
  file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
}

set_time_limit(600);
date_default_timezone_set('UTC');
//define("API_KEY", getenv("API_KEY"));
define("MAX_VIEW", 50);

if (isset($_GET[getenv('PASS')])) {
  addPlaylist("PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5", "vps", false, true);
  addPlaylist("PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM", "material", false, true);
}
if (isset($_GET['replace_' . getenv('PASS')])) {

    if (isset($_GET['vps_nexttoken'])) 
        $next = addPlaylist("PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5", "vps", $_GET['vps_nexttoken'], false);
    else 
        $next = addPlaylist("PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5", "vps", false, false);
    if (isset($_GET['material_nexttoken'])) 
        $next2 = addPlaylist("PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM", "material", $_GET['material_nexttoken'], false);
    else 
        $next2 = addPlaylist("PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM", "material", false, false);
  echo 'vps_nexttoken: ' . $next . ' , ';
  echo 'material_nexttoken: ' . $next2;
  exit;
}

if (isset($_GET["update2_" . getenv('PASS')])) {
        $playlists = json_decode(file_get_contents("data/playlists.json"), true);
        foreach($playlists as $id => $data) {
            addPlaylist($id, $data['type'], false, false, true);
        }
}

if (file_exists("time.txt")) {
    $time = (int) file_get_contents("time.txt");
    if ($time + 86400 < time() || isset($_GET['update_' . getenv('PASS')])) {
        file_put_contents("time.txt", time());
        $playlists = json_decode(file_get_contents("data/playlists.json"), true);

        foreach($playlists as $id => $data) {
            addPlaylist($id, $data['type']);
        }
    }
} else {
    file_put_contents("time.txt", time());
    $playlists = json_decode(file_get_contents("data/playlists.json"), true);

    foreach($playlists as $id => $data) {
        addPlaylist($id, $data['type']);
    }
}
if (!isset($useLang))
    $useLang = "ja";

require_once "lang.ini.php";

$lang = $_lang[$useLang];

global $notice;


function kan2num($str) {
    $str = str_replace("一", "1", $str);
    $str = str_replace("二", "2", $str);
    $str = str_replace("三", "3", $str);
    $str = str_replace("四", "4", $str);
    $str = str_replace("五", "5", $str);
    $str = str_replace("六", "6", $str);
    $str = str_replace("七", "7", $str);
    $str = str_replace("八", "8", $str);
    $str = str_replace("九", "9", $str);
    $str = str_replace("！", "!", $str);
    $str = str_replace("？", "?", $str);
  return $str;
}

function make_param(array $params) : string {
    $param = "";
    foreach ($params as $key => $value) {
        if ($value === null || empty($value)) continue;
        $param .= $key . "=" . $value . "&";
    }
    if (!empty($param)) {
        $param = "?" . substr($param, 0, -1);
    }
    return $param;
}

    function time_elapsed_string($datetime, $full = false) {
        global $lang, $useLang;

        $now = new DateTime;
        $ago = new DateTime;
        $ago->setTimestamp($datetime);
        $diff = $now->diff($ago);
    
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
      
        $string = array(
            'y' => $lang['year'],
            'm' => $lang['month'],
            'w' => $lang['week'],
            'd' => $lang['day'],
            'h' => $lang['hour'],
            'i' => $lang['minu'],
            's' => $lang['sec'],
        );

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                // $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                $v = $diff->$k . '' . $v . ($diff->$k > 1 ? ($useLang === "en" ? 's ' : '') : '');
            } else {
                unset($string[$k]);
            }
        }
    
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . $lang['ago'] : $lang['justnow']; // ago, just now
    }

    function addNicovideo($video_id, $type) {
      $api_url = "https://ext.nicovideo.jp/api/getthumbinfo/" . $video_id;
      $xml = file_get_contents($api_url);
      $array = json_decode(json_encode(simplexml_load_string($xml)), true);

      $index = [];
      if (file_exists("data/index.json"))
        $index = json_decode(file_get_contents("data/index.json"), true);
      
      
      $thumb = $array['thumb'];

      $index[$video_id] = [
          'is_nicovideo' => true,
          'title' => $thumb['title'],
          'description' => $thumb['description'],
          'channelId' => $thumb['user_id'],
          'channelTitle' => $thumb['user_nickname'],
          'publishedAt' => strtotime($thumb['first_retrieve']),
          'view' => $thumb['view_counter'],
          'tags' => array_values($thumb['tags']),
          'type' => $type,
      ];

      file_put_contents("cache/thumb/" . $video_id . ".jpg", file_get_contents($thumb['thumbnail_url']));

      $index = ((array) $index);
      array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        
      file_put_contents("data/index.json", json_encode($index, JSON_UNESCAPED_UNICODE));
      return;
    }

    if (!isset($blacklist))
        $blacklist = json_decode(file_get_contents("blacklist.json"), true);

    // index.json から blacklist の動画を削除
    /*
    $index = json_decode(file_get_contents("data/index.json"), true);
    foreach ($index as $id => $data) {
        if (in_array($id, $blacklist)) {
            unset($index[$id]);
        }
    }
    file_put_contents("data/index.json", json_encode($index, JSON_UNESCAPED_UNICODE));
    */

    /* 削除済みや非公開の動画のデータはerrorにステータスを入れる */
    // $index = json_decode(file_get_contents("data/index.json"), true);
    // foreach ($index as $id => $data) {
    //     // YouTube APIを使ってステータスを取得
    //     if (!isset($data['error'])) {
    //         $ch = curl_init();
    //         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    //         curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/youtube/v3/videos?part=status&key=" . API_KEY . "&id=" . $id);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    //         $output = json_decode(curl_exec($ch));
    //         curl_close($ch);

    //         if (isset($output->items[0]->status->privacyStatus)) {
    //             $index[$id]['status'] = $output->items[0]->status->privacyStatus;
    //             $index[$id]['error'] = $index[$id]['status'];
    //         } else if (!isset($output->items[0]->snippet->publishedAt)) {
    //             $index[$id]['error'] = "deleted";
    //         }
    //     }
    // }

    function addPlaylist($playlist_id, $type, $nextPageToken = false, $only = false, $nextWithOnly = false) {
        global $blacklist;

        if (!isset($blacklist)) {
            $blacklist = json_decode(file_get_contents("blacklist.json"), true);
        }

        static $c = 0;
        ++$c;
        $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&key=" . API_KEY . "&order=date&playlistId=" . $playlist_id;
        $video_api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics,status&key=" . API_KEY;

        if ($nextPageToken !== false) {
            $api_url .= "&pageToken=" . $nextPageToken;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        $index = [];
        file_put_contents("data/" . $c . "-" . $playlist_id . ".json", json_encode($output, JSON_UNESCAPED_UNICODE));

        if (file_exists("data/index.json")) {
            $index = json_decode(file_get_contents("data/index.json"), true);
        }

        foreach ($output->items as $item) {
            $snippet = $item->snippet;

            $videoId = $snippet->resourceId->videoId;
            if ($only) {
                if (isset($index[$videoId])) continue;
            }
            
            if (in_array($videoId, $blacklist)) continue;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, $video_api_url . "&id=" . $videoId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            $video_output = json_decode(curl_exec($ch));
            curl_close($ch);

            // 削除、非公開の動画の場合はスキップ (publishedAtがない場合)
            if (!isset($video_output->items[0]->snippet->publishedAt)) {
                if (isset($index[$videoId])) {
                    if (isset($video_output->items[0]->status->privacyStatus)) {
                        $index[$videoId]['status'] = $video_output->items[0]->status->privacyStatus;
                        $index[$videoId]['error'] = $index[$videoId]['status'];
                    } else {                    
                        $index[$videoId]['error'] = "deleted";
                    }
                }
                continue;
            }

            $index[$videoId] = [
                'title' => $snippet->title,
                'description' => $snippet->description,
                'channelId' => $snippet->videoOwnerChannelId,
                'channelTitle' => $snippet->videoOwnerChannelTitle,
                'publishedAt' => strtotime($video_output->items[0]->snippet->publishedAt),
                'view' => $video_output->items[0]->statistics->viewCount,
                'like' => $video_output->items[0]->statistics->likeCount,
                'tags' => (array) $video_output->items[0]->snippet->tags,
                'type' => $type,
                'status' => $video_output->items[0]->status->privacyStatus,
            ];
        }

        $index = ((array) $index);
        array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        
        file_put_contents("data/index.json", json_encode($index, JSON_UNESCAPED_UNICODE));

        if ((!$only || $nextWithOnly) && isset($output->nextPageToken)) {
            addPlaylist($playlist_id, $type, $output->nextPageToken);
        } else if (isset($output->nextPageToken)) {
            return $output->nextPageToken;
        }
        
    }
    if (isset($_POST['do'])) {
        $url = $_POST['url'];

        $url_type = "none";
        if (false !== strpos($url, 'list=') || str_starts_with($url, 'PL')) {
          $url_type = "playlist";
        } else if (false !== strpos($url, 'watch/sm') || str_starts_with($url, 'sm')) {
          $url_type = "nicovideo";
        } else {
          $url_type = "youtube";
        }
      
        if ($_POST['do'] === "post") {

          // 再生リスト
          if ($url_type === "playlist") {
            $playlist_id = preg_replace('/.*?&list\=(.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/pl_" . $playlist_id . ".txt", "ID: {$playlist_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_pl'];
          }

          // ニコニコ動画
          if ($url_type === "nicovideo") {
            $video_id = preg_replace('/.*?(sm.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/nc_" . $video_id . ".txt", "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_vd'];
            
          }

          // YouTube動画
          if ($url_type === "youtube") {
            $video_id = preg_replace('/.*?watch\?v\=(.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/yt_" . $video_id . ".txt", "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_vd'];
            
          }
          
        }
        if ($_POST['do'] === "post_" . getenv('PASS')) {
          
          // 再生リスト
          if ($url_type === "playlist") {
            $playlist_id = preg_replace('/.*?&list\=(.*?)/u', '$1', $url);

            $array = [];
            if (file_exists("data/playlists.json"))
                $array = json_decode(file_get_contents("data/playlists.json"), true);
            $array[$playlist_id] = [
                "type" => $_POST['t']
            ];
            file_put_contents("data/playlists.json", json_encode($array));

            addPlaylist($playlist_id, $_POST['t']);
            $notice .= $lang['added_pl'];
          }

          // ニコニコ動画
          if ($url_type === "nicovideo") {
            $video_id = preg_replace('/.*?(sm.*?)/u', '$1', $url);

            $array = [];
            if (file_exists("data/nc_videos.json"))
                $array = json_decode(file_get_contents("data/nc_videos.json"), true);
            $array[$video_id] = [
                "type" => $_POST['t']
            ];
            file_put_contents("data/nc_videos.json", json_encode($array));

            addNicovideo($video_id, $_POST['t']);
            $notice .= $lang['added_vd'];
          }

          // YouTube動画
          if ($url_type === "youtube") {
            $video_id = preg_replace('/.*?watch\?v\=(.*?)/u', '$1', $url);

            $array = [];
            if (file_exists("data/yt_videos.json"))
                $array = json_decode(file_get_contents("data/yt_videos.json"), true);
            $array[$video_id] = [
                "type" => $_POST['t']
            ];
            file_put_contents("data/yt_videos.json", json_encode($array));

            //addPlaylist($playlist_id, $_POST['t']);
            $notice .= $lang['added_vd'];
          }
          
        }
        
        if ($_POST['do'] === "report") {
            if (!file_exists("report/")) mkdir("report");
            $id = $_POST['id'];
            file_put_contents("report/" . $_POST['id'] . "-" . time() . ".txt", "ID: {$id}\nURL: https://youtu.be/{$id}\nType: " . $_POST['t'] . "\nReason: " . (isset($_POST['reason']) ? $_POST['reason'] : 'none'));
            $notice .= $lang['reported_video'];
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title><?php echo $lang['title']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="/favicon.png" />
    <script>
        function onClickThumb($id) {
            document.getElementById("content_" + $id).innerHTML = '<iframe width="320" height="180" src="https://www.youtube.com/embed/' + $id + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
        }
      
        function onClickThumbNC($id) {
            var $script = document.createElement('script');
            $script.setAttribute("type", "application/javascript");
            $script.setAttribute("src", "https://embed.nicovideo.jp/watch/" + $id + "/script?w=320&h=180");
            document.getElementById("content_" + $id).innerHTML = '';
            document.getElementById("content_" + $id).appendChild($script);
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Dropdown
            var dropdown = document.getElementsByClassName("dropdown");
            var i;

            for (i = 0; i < dropdown.length; i++) {
                dropdown[i].addEventListener("mouseover", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "block";
                });
                dropdown[i].addEventListener("mouseout", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "none";
                });
            }

            // Lazy Load
            const lazyImages = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.onload = () => img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        });

        function openSpMenu() {
            var menu = document.getElementById("menu_sp");
            if (menu.getAttribute("data-isopen") === "false" || menu.getAttribute("data-isopen") === null) {
                menu.setAttribute("data-isopen", "true");
            } else {
                menu.setAttribute("data-isopen", "false");
            }
        }

        document.addEventListener("click", function(e) {
            if (document.getElementById("menu_sp").getAttribute("data-isopen") === "true") {
                if (!document.getElementById("menu_sp").contains(e.target) && !document.getElementById("menu").contains(e.target)) {
                    document.getElementById("menu_sp").setAttribute("data-isopen", "false");
                }
            }
        });
    </script>
    <script src="darkmode.js"></script>
    <link rel="stylesheet" type="text/css" href="main.css" />
</head>
<body>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="pc"><a href="?info"><?php echo $lang['info']; ?></a></li>
            <li class="pc"><a href="?post"><?php echo $lang['send_pl']; ?></a></li>
            <li class="dropdown pc">
                <a href="javascript:void(0)" class="dropbtn">Language</a>
                <div class="dropdown-content">
                    <a href="./<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="./image/japanese.png" /> 日本語</a>
                    <a href="./en.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="./image/english.png" /> English</a>
                    <a href="./zh.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="./image/chinese.png" /> 中国语</a>
                    <a href="./ko.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="./image/korean.png" /> 한국인</a>
                </div>
            </li>

            <li><a href="./matrix/<?php echo $useLang === "ja" ? "" : $useLang . ".php"; ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="image/matrix.png" /></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="image/darkmode.png" /></a></li>

            <li class="sp noborder" id="menu"><a href="javascript:openSpMenu()"><img src="image/menu.png" /></a></li>
        </ul>
    </div>
    <div id="menu_sp" data-isopen="false">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="none"><br /></li>
            <li><a href="?info"><?php echo $lang['info']; ?></a></li>
            <li><a href="?post"><?php echo $lang['send_pl']; ?></a></li>
            <li class="none"><br /></li>
            <li><a href="./matrix/<?php echo $useLang === "ja" ? "" : $useLang . ".php"; ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><?= $lang['matrixview'] ?></a></li>
            <li class="none"><br /></li>
            <li><a href="./<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">日本語</a></li>
            <li><a href="./en.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">English</a></li>
            <li><a href="./zh.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">中国语</a></li>
            <li><a href="./ko.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">한국인</a></li>            
        </ul>
    </div>
    <br /><br />
    <div id="contents">
    
    <?php echo empty($notice) ? "" : '<h3>' . $notice . '</h3>'; ?>
<?php
if (isset($_GET['post'])) {
    ?>
    <p>ボイパ対決、素材の動画情報を当ツールへ追加するための再生リストを送信できます<br />再生リストに入っているものがボイパ対決・素材であるかこちらで審査します。<br />比較動画などに関しては今のところ採用しません。<br /><br />YouTubeの動画・再生リストのURLかニコニコ動画のURL</p>
        <form action="./" method="POST">
            <input type="text" name ="url" />
            <input type="hidden" name="do" value="post" placeholder="Playlist URL" />
            <input type="radio" name="t" value="vps"><?php echo $lang['vps_radio']; ?></input>
            <input type="radio" name="t" value="material"><?php echo $lang['material_radio']; ?></input>
            <input type="submit" />
        </form>
    <?php
} else if (isset($_GET['post_' . getenv('PASS')])) {
    ?>
        <form action="./" method="POST">
            <input type="text" name ="url" />
            <input type="hidden" name="do" value="post_<?php echo getenv('PASS'); ?>" placeholder="Playlist URL" />
            <input type="radio" name="t" value="vps"><?php echo $lang['vps_radio']; ?></input>
            <input type="radio" name="t" value="material"><?php echo $lang['material_radio']; ?></input>
            <input type="submit" />
        </form>
    <?php
} else if (isset($_GET['report'])) {
    $id = $_GET['id'];
    echo <<<EOD
        <iframe width="560" height="315" src="https://www.youtube.com/embed/{$id}" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        <p>報告内容を以下から選んでください。</p>
        <form action="./" method="POST">
        <input type="hidden" name="id" value="{$id}" />
        <input type="hidden" name="do" value="report" />
        <input type="radio" name="t" value="比較などの動画である、または関係のない動画である">比較などの動画である、または関係のない動画である</input><br />
            <input type="radio" name="t" value="動画が削除、非公開である">動画が削除、非公開である</input><br />
            <input type="radio" name="t" value="法律に反する動画である">法律に反する動画である</input><br />
            <input type="radio" name="t" value="種類(ボイパ対決/素材)が間違えている">種類(ボイパ対決/素材)が間違えている</input><br />
            <input type="radio" name="t" value="その他">その他</input><br />
            <textarea name="reason" cols="75" rows="5" placeholder="理由等 (「その他」を選択した場合)"></textarea><br />
            <input type="submit" />
        </form>
    EOD;
} else if (isset($_GET['info'])) {
    ?>

    <div id="badapple">
        <!-- メニューにはめ込む -->
        <img src="image/badapple.gif" style="width: 25%; text-align: center; opacity: 75%; position: absolute; top: 0; left: 50%; transform: translate(-50%, 0%);" />
        <script>
            var img = document.querySelector("#badapple img");
            img.onload = function() {
                setTimeout(function() {
                    document.querySelector("#badapple").remove();
                }, 3100);
            }
        </script>

    </div>

    <h2>Information</h2>

    <p id="next_birth">
        <?php

        $date = new DateTime("2014-05-05");
        $now = new DateTime();
        $interval = $date->diff($now);
        $year = $interval->y;
        $day = $interval->days;
        $next = $year + 1;
        $next_date = new DateTime("2014-05-05");
        $next_date->add(new DateInterval("P" . $next . "Y"));
        $next_interval = $now->diff($next_date);
        $next_day = $next_interval->days;
        echo sprintf($lang['next_birth'], $next, $next_day);
        ?>
    </p>

    <h3>Description</h3>
    <p>
        このサイトはボイパ対決という音MADに特化した検索ツールです。<br />
        The site is a search tool specialized in sound MAD called "Vocal Percussion Showdown".<br />
        本サイトはYouTube APIを使用しています。<br />
        This site uses the YouTube API.<br />
        <br />
        サイト名: HIKAKINボイパ対決シリーズ専用検索ツール / Vocal Percussion Showdown Search Tool<br />
        サイトURL: <a href="https://vps-search.pitan76.net/">https://vps-search.pitan76.net/</a><br />
        開発者: Pitan<br />
        開発言語: PHP, JavaScript (HTML, CSS)<br />
        ソースコード: <a href="https://github.com/PTOM76/VPS-searcher" target="_blank">GitHub</a><br />
    </p>

    <h3>Related Links</h3>
    <ul>
        <li><a href="https://dic.nicovideo.jp/a/hikakin%E3%83%9C%E3%82%A4%E3%83%91%E5%AF%BE%E6%B1%BA%E3%82%B7%E3%83%AA%E3%83%BC%E3%82%BA" target="_blank">HIKAKINボイパ対決シリーズとは - ニコニコ大百科</a></li>
        <li><a href="https://w.atwiki.jp/vpsseries/" target="_blank">HIKAKINボイパ対決シリーズWiki</a></li>
        <li><a href="https://w.atwiki.jp/voicepercussionhkkn/" target="_blank">HIKAKIN Wiki - Vocal Percussion Showdown</a></li>
        <li><a href="./ai/">AIによるタイトルラベリングツール</a></li>
    </ul>

    <h3>Data Count</h3>
    <p>
        <?php
        $vps = $material = 0;

        if (file_exists("cache/info.json") && filemtime("cache/info.json") + 86400 > time()) {
            $info = json_decode(file_get_contents("cache/info.json"), true);
            $vps = $info['vps'];
            $material = $info['material'];
        } else {
            $index = json_decode(file_get_contents("data/index.json"), true);
            foreach ($index as $id => $data) {
                if ($data['type'] === "vps") ++$vps;
                if ($data['type'] === "material") ++$material;
            }
            file_put_contents("cache/info.json", json_encode(['vps' => $vps, 'material' => $material]));
        }

        echo $lang['total'] . ": " . number_format($vps + $material) . "<br />";
        
        echo $lang['vps_radio'] . ": " . number_format($vps) . "<br />";
        echo $lang['material_radio'] . ": " . number_format($material) . "<br />";
        ?>

    </p>

    <hr />

    <?php
} else {

    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $method = isset($_GET['method']) ? $_GET['method'] : 'and';
    $title = isset($_GET['title']) ? $_GET['title'] : '1';
    $expl = isset($_GET['expl']) ? $_GET['expl'] : '0';
    $author = isset($_GET['author']) ? $_GET['author'] : '0';
    $t = isset($_GET['t']) ? $_GET['t'] : 'all';

    $page = 1;
    if (isset($_GET['page']))
        $page = (int) $_GET['page'];

    ?>
    <form method="GET">
        <select name="sort">
            <option value="recent" <?php echo isset($_GET['sort']) && $_GET['sort'] === "recent" ? " selected" : ""; ?>><?php echo $lang['sort_recent']; ?></option>
            <option value="ancient" <?php echo isset($_GET['sort']) && $_GET['sort'] === "ancient" ? " selected" : ""; ?>><?php echo $lang['sort_ancient']; ?></option>
            <option value="most_view" <?php echo isset($_GET['sort']) && $_GET['sort'] === "most_view" ? " selected" : ""; ?>><?php echo $lang['sort_most_view']; ?></option>
            <option value="worst_view" <?php echo isset($_GET['sort']) && $_GET['sort'] === "worst_view" ? " selected" : ""; ?>><?php echo $lang['sort_worst_view']; ?></option>
            <option value="most_like" <?php echo isset($_GET['sort']) && $_GET['sort'] === "most_like" ? " selected" : ""; ?>><?php echo $lang['sort_most_like']; ?></option>
            <option value="word" <?php echo isset($_GET['sort']) && $_GET['sort'] === "word" ? " selected" : ""; ?>><?php echo $lang['sort_word']; ?></option>
            <option value="word_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] === "word_desc" ? " selected" : ""; ?>><?php echo $lang['sort_word_desc']; ?></option>
        </select>

        <input type="text" name ="q" value="<?php echo isset($_GET['q']) ? $_GET['q'] : '' ?>" />
        <input type="submit" value="<?php echo $lang['search']; ?>" />
        
        <input type="radio" name="method" value="and"<?php echo isset($_GET['method']) && $_GET['method'] === "and" || !isset($_GET['method']) ? " checked" : ""; ?>><?php echo $lang['and_search']; ?></input>
        <input type="radio" name="method" value="or"<?php echo isset($_GET['method']) && $_GET['method'] === "or" ? " checked" : ""; ?>><?php echo $lang['or_search']; ?></input>
        <br />
        <input type="checkbox" id="q_title" name="title" value="1" <?php echo isset($_GET['title']) && $_GET['title'] === "1" || !isset($_GET['q']) ? "checked " : ""; ?>/>
        <label for="q_title"><?php echo $lang['title_checkbox']; ?></label>
        <input type="checkbox" id="q_tag" name="tag" value="1" <?php echo isset($_GET['tag']) && $_GET['tag'] === "1" ? "checked " : ""; ?>/>
        <label for="q_tag"><?php echo $lang['tag_checkbox']; ?></label>
        <input type="checkbox" id="q_expl" name="expl" value="1" <?php echo isset($_GET['expl']) && $_GET['expl'] === "1" ? "checked " : ""; ?>/>
        <label for="q_expl"><?php echo $lang['overview_checkbox']; ?></label>
        <input type="checkbox" id="q_author" name="author" value="1" <?php echo isset($_GET['author']) && $_GET['author'] === "1" ? "checked " : ""; ?>/>
        <label for="q_author"><?php echo $lang['author_checkbox']; ?></label>

        |

        <input type="checkbox" id="q_unlisted" name="status" value="unlisted" <?php echo isset($_GET['status']) && $_GET['status'] === "unlisted" ? "checked " : ""; ?>/>
        <label for="q_unlisted"><?php echo $lang['unlisted_checkbox']; ?></label>

        <input type="checkbox" id="q_deleted" name="deleted" value="1" <?php echo isset($_GET['deleted']) && $_GET['deleted'] === "1" ? "checked " : ""; ?>/>
        <label for="q_deleted"><?php echo $lang['deleted_checkbox']; ?></label>
        
        |

        <input type="radio" name="t" value="all"<?php echo isset($_GET['t']) && $_GET['t'] === "all" || !isset($_GET['t']) ? " checked" : ""; ?>><?php echo $lang['all_radio']; ?></input>
        <input type="radio" name="t" value="vps"<?php echo isset($_GET['t']) && $_GET['t'] === "vps" ? " checked" : ""; ?>><?php echo $lang['vps_radio']; ?></input>
        <input type="radio" name="t" value="material"<?php echo isset($_GET['t']) && $_GET['t'] === "material" ? " checked" : ""; ?>><?php echo $lang['material_radio']; ?></input>
        <input type="checkbox" id="ai_class" name="ai_class" value="1" <?php echo isset($_GET['ai_class']) && $_GET['ai_class'] === "1" ? "checked " : ""; ?>/>
        <label for="ai_class"><?php echo $lang['use_ai_class']; ?></label>
    </form>
    <br />
    <?php

    $video_contents_html = '';
      
    $index = [];
    $c = $view_c = 0;

    if (isset($_GET['ai_class'] ) && $_GET['ai_class'] == "1" && file_exists("data/ai_index.json")) {
        $index = json_decode(file_get_contents("data/ai_index.json"), true);
    } elseif (file_exists("data/index.json")) {
        $index = json_decode(file_get_contents("data/index.json"), true);
    }

    $page = 1;
    if (isset($_GET['page']))
        $page = (int) $_GET['page'];

    $words = [];
    if (!empty($_GET['q']))
        $words = explode(" ", $_GET['q']);

    // publishedAtがfalseでタイトルが"Deleted video"の場合は削除された動画として表示しない
    if (!isset($_GET['deleted']) || $_GET['deleted'] === "0") {
        foreach ($index as $id => $data) {
            if ($data['publishedAt'] === false && 
                ($data['title'] === "Deleted video" || $data['title'] === "Private video")
            ) {
                unset($index[$id]);
            }
        }
    }

    // 並び替え
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
    if ($sort != 'recent') {
        $sort_array = [];
        foreach ($index as $id => $data) {
            if (!isset($data['like'])) $index[$id]['like'] = 0;
            $sort_array[$id] = $data['publishedAt'];
        }
        if ($sort === 'ancient') {
            array_multisort($sort_array, SORT_ASC, $index);
        } else if ($sort === 'most_view') {
            array_multisort(array_column($index, 'view'), SORT_DESC, $index);
        } else if ($sort === 'worst_view') {
            array_multisort(array_column($index, 'view'), SORT_ASC, $index);
        } else if ($sort === 'most_like') {
            array_multisort(array_column($index, 'like'), SORT_DESC, $index);
        } else if ($sort === 'word') {
            array_multisort(array_column($index, 'title'), SORT_ASC, $index);
        } else if ($sort === 'word_desc') {
            array_multisort(array_column($index, 'title'), SORT_DESC, $index);
        }
    }

    foreach ($index as $id => $data) {
        $continue = false;
        foreach ($words as $word) {
            if (!isset($_GET['method']) || $_GET['method'] === "and")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] === "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] === "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] === "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word))) 
                    && (!isset($_GET['tag']) || !$_GET['tag'] === "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    )
                {
                    $continue = true;
                }
            if (isset($_GET['method']) && $_GET['method'] === "or")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] === "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] === "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] === "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word)))
                    && (!isset($_GET['tag']) || !$_GET['tag'] === "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    ) 
                {
                    if ($continue === false) $continue = 0;
                    ++$continue;
                }
        }

        if (isset($_GET['t']) && $_GET['t'] !== "all") {
            if ($_GET['t'] != $data['type']) continue;
        }
        if (isset($_GET['status']) && $_GET['status'] !== "public") {
            if (!isset($data['status'])) continue;
            if ($_GET['status'] != $data['status']) continue;
        }

        if (!isset($_GET['deleted']) || $_GET['deleted'] === "0") {
            if (isset($data['error']) && !empty($data['error'])) 
                continue;
        }

        if ($continue === true || count($words) > 0 && $continue >= count($words)) continue;
        
        ++$c;
        if ($c < $page) continue;

        if ($view_c >= MAX_VIEW) break;
        ++$view_c;

        $description = mb_substr($data['description'], 0, 100, "UTF-8");
        if (mb_strlen($data['description']) > 100) {
            $description .= "...";
        }

        // htmlエスケープ
        $data['title'] = htmlspecialchars($data['title'], ENT_QUOTES);
        $data['channelTitle'] = htmlspecialchars($data['channelTitle'], ENT_QUOTES);
        $data['description'] = htmlspecialchars($data['description'], ENT_QUOTES);

        $ago = "";
        if (isset($data['publishedAt']))
            $ago = time_elapsed_string($data['publishedAt']);

        $view_str = number_format($data['view']);
        if (isset($data['is_nicovideo']) && $data['is_nicovideo'] === true) {
          // ニコニコ
        $video_contents_html .= <<<EOD
        <div style="clear:both;">
            <div id="content_{$id}" style="float:left;margin-right:8px;">
                <a href="javascript:onClickThumbNC('{$id}');"><img id="{$id}" loading="lazy" data-src="./cache/thumb/{$id}.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </div>
            <div>
                <span style="font-size:18px;"><a target="_blank" class="plain" href="https://www.nicovideo.jp/watch/{$id}">{$data['title']}</a></span><br />
                <span style="font-size:11px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:15px;"><a class="plain" href="https://www.nicovideo.jp/user/{$data['channelId']}">{$data['channelTitle']}</a></span>
                <br />
                <span style="font-size:11px;">{$description}</span>
                <br />
                <span style="font-size:12px;"><a href="./?report&id={$id}&is_nicovideo=true">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://www.nicovideo.jp/watch/{$id}');">URL{$lang['copy']}</a></span>
            </div>

        </div>
        EOD;
          
        } else {
          // youtube
        $video_contents_html .= <<<EOD
        <div style="clear:both;">
            <div id="content_{$id}" style="float:left;margin-right:8px;">
                <a href="javascript:onClickThumb('{$id}');"><img id="{$id}" loading="lazy" data-src="https://i.ytimg.com/vi/{$id}/hqdefault.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </div>
            <div>
                <span style="font-size:18px;"><a target="_blank" class="plain" href="https://youtu.be/{$id}">{$data['title']}</a></span><br />
                <span style="font-size:11px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:15px;"><a class="plain" href="https://www.youtube.com/channel/{$data['channelId']}">{$data['channelTitle']}</a></span>
                <br />
                <span style="font-size:11px;">{$description}</span>
                <br />
                <span style="font-size:12px;"><a href="./?report&id={$id}">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://youtu.be/{$id}');">URL{$lang['copy']}</a></span>
            </div>

        </div>
        EOD;
        }
    }
  
    $page_switch_html = '';

    $page_switch_html .= '<div style="clear:both;">';
    if ($page !== 1) {
        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort,
        ];
        $param_array['page'] = $page - 50;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        if (isset($_GET['deleted'])) $param_array['deleted'] = $_GET['deleted'];
        if (isset($_GET['ai_class'])) $param_array['ai_class'] = $_GET['ai_class'];
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    }
        //$page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($page - 50) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    $move_n = round(9 / 2);
    $page_count = 0;

    if (file_exists("data/index.json"))
        $page_count = count(json_decode(file_get_contents("data/index.json"), true));

    $num_op_tag = '';
    $c = 1;
    while ($c <= 9) {
        $n = round(($page + MAX_VIEW) / MAX_VIEW);
        $disp_num = $c;
        if ($n > $move_n) $disp_num = $c + $n - $move_n;
        $f = $disp_num * MAX_VIEW - MAX_VIEW + 1;
        if ($f > $page_count) break;

        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort
        ];
        $param_array['page'] = $f;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        if (isset($_GET['deleted'])) $param_array['deleted'] = $_GET['deleted'];
        if (isset($_GET['ai_class'])) $param_array['ai_class'] = $_GET['ai_class'];
        $num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="' . make_param($param_array) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';

        //$num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($f) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';
        if ($f === $page && $view_c < MAX_VIEW) break;
        ++$c;
    }
    $page_switch_html .= $num_op_tag;
    if ($view_c >= MAX_VIEW) {
        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort,
        ];
        $param_array['page'] = $page + 50;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        if (isset($_GET['deleted'])) $param_array['deleted'] = $_GET['deleted'];
        if (isset($_GET['ai_class'])) $param_array['ai_class'] = $_GET['ai_class'];
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['next'] . '</strong></a>';
        //$page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($page + 50) . '"><strong>' . $lang['next'] . '</strong></a>';
    } else {
      $page_switch_html .= '<strong>...</strong>';
    }
    $page_switch_html .= '</div>';

    echo $page_switch_html , "\n<hr />" , $video_contents_html , "\n<br />\n<div style=\"clear:both;\"><hr /></div>\n" , $page_switch_html;
}
?>
<br />
<span style="font-size:12px;">Languages: <a href="./" >日本語</a>, <a href="./en.php" >English</a>, <a href="./zh.php" >中国语</a>, <a href="./ko.php" >한국인</a><br /><br />
※当サイトのデータは再生リストから取得したものです。<br />
ソース: <a href="https://github.com/PTOM76/VPS-searcher">Gitリポジトリ</a><br />
Copyright 2023-2025 © Pitan.</span>
</div>
</body>
</html>
<?php
