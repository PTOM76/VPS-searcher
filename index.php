<?php
if (isset($_GET['q'])) {
  $analytics = "./data/analytics/" . date("Y-m-d") . ".txt";
  $data = file_get_contents($analytics);
  file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
}

set_time_limit(600);
date_default_timezone_set('UTC');
define("API_KEY", getenv("API_KEY"));
define("MAX_VIEW", 50);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);

if (isset($_GET[getenv('PASS')])) {
  addPlaylist("PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5", "vps", false, true);
  addPlaylist("PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM", "material", false, true);
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
$_lang['ja'] = [
    'year' => '年',
    'month' => 'ヶ月',
    'week' => '週',
    'day' => '日',
    'hour' => '時',
    'minu' => '分',
    'sec' => '秒',
    'ago' => '前',
    'justnow' => '直前',
    'title' => 'ボ対専用検索ツール',
    'search' => '検索',
    'and_search' => 'AND検索',
    'or_search' => 'OR検索',
    'title_checkbox' => 'タイトル',
    'overview_checkbox' => '概要欄',
    'author_checkbox' => '投稿者',
    'tag_checkbox' => 'タグ',
    'all_radio' => 'すべて',
    'vps_radio' => 'ボイパ対決',
    'material_radio' => '素材',
    'next' => '次へ',
    'prev' => '前へ',
    'sended_pl' => 'プレイリストを送信しました。',
    'added_pl' => 'プレイリストを追加しました。',
    'sended_vd' => '動画を送信しました。',
    'added_vd' => '動画を追加しました。',
    'reported_video' => '動画を報告しました。',
    'send_pl' => 'データ送信',
    'view' => '回視聴',
    'report' => '報告',
    'download' => 'ダウンロード',
    'copy' => 'コピー',
];

$_lang['en'] = [
    'year' => 'year',
    'month' => 'month',
    'week' => 'week',
    'day' => 'day',
    'hour' => 'hour',
    'minu' => 'minute',
    'sec' => 'second',
    'ago' => 'ago',
    'justnow' => 'just now',
    'title' => 'VPS Search Tool',
    'search' => 'Search',
    'and_search' => 'AND',
    'or_search' => 'OR',
    'title_checkbox' => 'Title',
    'overview_checkbox' => 'Description',
    'author_checkbox' => 'Author',
    'tag_checkbox' => 'Tag',
    'all_radio' => 'All',
    'vps_radio' => 'VPS',
    'material_radio' => 'Material',
    'next' => 'Next',
    'prev' => 'Prev',
    'sended_pl' => 'Sended playlist',
    'added_pl' => 'Added playlist',
    'sended_vd' => 'Sended video',
    'added_vd' => 'Added video',
    'reported_video' => 'Reported video',
    'send_pl' => 'Data Sending',
    'view' => 'views',
    'report' => 'Report',
    'download' => 'Download',
    'copy' => 'Copy',
];

$_lang['zh'] = [
'year' => '年',
'month' => '个月',
'week' => '周',
'day' => '天',
'hour' => '小时',
'minu' => '分钟',
'sec' => '秒',
'ago' => '前',
'justnow' => '刚刚',
'title' => '口技対決专用搜索工具',
'search' => '搜索',
'and_search' => 'AND搜索',
'or_search' => 'OR搜索',
'title_checkbox' => '标题',
'overview_checkbox' => '概述',
'author_checkbox' => '投稿者',
'tag_checkbox' => '标签',
'all_radio' => '全部',
'vps_radio' => '语音对决',
'material_radio' => '素材',
'next' => '下一页',
'prev' => '上一页',
'sended_pl' => '已发送播放列表。',
'added_pl' => '已添加到播放列表。',
'sended_vd' => '已发送播视频。',
'added_vd' => '已添加到播视频。',
'reported_video' => '已举报视频。',
'send_pl' => '数据传输',
'view' => '次观看',
'report' => '举报',
'download' => '下载',
'copy' => '复制',
];

$_lang['ko'] = [
'year' => '년',
'month' => '개월',
'week' => '주',
'day' => '일',
'hour' => '시간',
'minu' => '분',
'sec' => '초',
'ago' => '전',
'justnow' => '방금',
'title' => '보이파대결 전용 검색 도구',
'search' => '검색',
'and_search' => 'AND 검색',
'or_search' => 'OR 검색',
'title_checkbox' => '제목',
'overview_checkbox' => '개요',
'author_checkbox' => '게시자',
'tag_checkbox' => '태그',
'all_radio' => '모두',
'vps_radio' => '보이스피싱 대결',
'material_radio' => '소재',
'next' => '다음',
'prev' => '이전',
'sended_pl' => '재생 목록을 전송했습니다.',
'added_pl' => '재생 목록에 추가했습니다.',
'sended_vd' => '동영상 전송했습니다.',
'added_vd' => '동영상 추가했습니다.',
'reported_video' => '동영상을 신고했습니다.',
'send_pl' => '데이터 전송',
'view' => '회 시청',
'report' => '신고',
'download' => '다운로드',
'copy' => '복사',
];

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
                $v = $diff->$k . '' . $v . ($diff->$k > 1 ? ($useLang == "en" ? 's ' : '') : '');
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

    function addPlaylist($playlist_id, $type, $nextPageToken = false, $only = false, $nextWithOnly = false) {
        static $c = 0;
        ++$c;
        $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&key=" . API_KEY . "&order=date&playlistId=" . $playlist_id;
        $video_api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics&key=" . API_KEY;

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

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, $video_api_url . "&id=" . $videoId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            $video_output = json_decode(curl_exec($ch));
            curl_close($ch);

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
            ];
        }

        $index = ((array) $index);
        array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        
        file_put_contents("data/index.json", json_encode($index, JSON_UNESCAPED_UNICODE));

        if (($only == false || $nextWithOnly == true) && isset($output->nextPageToken)) {
            addPlaylist($playlist_id, $type, $output->nextPageToken);
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
      
        if ($_POST['do'] == "post") {

          // 再生リスト
          if ($url_type == "playlist") {
            $playlist_id = preg_replace('/.*?&list\=(.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/pl_" . $playlist_id . ".txt", "ID: {$playlist_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_pl'];
          }

          // ニコニコ動画
          if ($url_type == "nicovideo") {
            $video_id = preg_replace('/.*?(sm.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/nc_" . $video_id . ".txt", "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_vd'];
            
          }

          // YouTube動画
          if ($url_type == "youtube") {
            $video_id = preg_replace('/.*?watch\?v\=(.*?)/u', '$1', $url);
            if (!file_exists("queue/")) mkdir("queue");
            file_put_contents("queue/yt_" . $video_id . ".txt", "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);

            $notice .= $lang['sended_vd'];
            
          }
          
        }
        if ($_POST['do'] == "post_" . getenv('PASS')) {
          
          // 再生リスト
          if ($url_type == "playlist") {
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
          if ($url_type == "nicovideo") {
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
          if ($url_type == "youtube") {
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
        
        if ($_POST['do'] == "report") {
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
    </script>
    <style>
        body {
            margin:0;
            font-family: verdana, arial, helvetica, Sans-Serif;
        }
        div#contents {
            margin-left:8px;
            margin-right:8px;
        }
        h3 a {
            color:black;
            text-decoration:none;
            font-weight:normal;
        }
        a.plain {
            color:black;
            text-decoration:none;
            font-weight:normal;
        }
        div#navi {
            width:100%;
            height:35px;
            background-color:black;
            box-sizing:border-box;
            margin:0;
            position:fixed;
            top:0;
            left:0;
            opacity:0.85;
            
        }
        div#navi li+ li {
            border-left:1px solid #555;
        }
        div#navi ul {
            display: flex;
            margin-left:-40px;
        }
        div#navi li {
            list-style:none;
            margin-top:-10px;
        }
        div#navi a {
            display:block;
            text-decoration:none;
            color:white;
            margin-right:10px;
            margin-left:10px;
        }
        div#navi a:hover {
            color:#77aaff;
        }

input[type='submit'] {
    height:24px;
    background:#EBEBEB;
    text-align:center;
    border:1px solid #AFAFAF;
    color:#444444;
    font-size:15px;
    border-radius:3px;
    -webkit-border-radius:3px;
    -moz-border-radius:3px;
    transition: all 0.5s ease;
    margin-top:-1px;
}
input[type='submit']:hover{
    background:#EBFBFF;
    color:#444444;
    margin-left:0px;
    margin-top:-1px;
    border:1px solid #AFCFFF;
}
input[type='text'] {
    height:20px;
    border:1px solid #AFAFAF;
    color:#444444;
    font-size:15px;
    border-radius:3px;
    -webkit-border-radius:3px;
    -moz-border-radius:3px;
    transition: all 0.5s ease;
}
a {
    text-decoration:none;
    color:#0066ff;
}
a:hover {
    text-decoration:none;
    color:#0044dd;
}
    </style>
</head>
<body>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang == "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li><a href="?post"><?php echo $lang['send_pl']; ?></a></li>
            <li><a href="<?php echo $useLang == "ja" ? "./en.php" : "./"; ?>"><?php echo $useLang == "ja" ? "English" : "日本語"; ?></a></li>
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
        <input type="text" name ="q" value="<?php echo isset($_GET['q']) ? $_GET['q'] : '' ?>" />
        <input type="submit" value="<?php echo $lang['search']; ?>" />
        
        <input type="radio" name="method" value="and"<?php echo isset($_GET['method']) && $_GET['method'] == "and" || !isset($_GET['method']) ? " checked" : ""; ?>><?php echo $lang['and_search']; ?></input>
        <input type="radio" name="method" value="or"<?php echo isset($_GET['method']) && $_GET['method'] == "or" ? " checked" : ""; ?>><?php echo $lang['or_search']; ?></input>
        <br />
        <input type="checkbox" id="q_title" name="title" value="1" <?php echo isset($_GET['title']) && $_GET['title'] == "1" || !isset($_GET['q']) ? "checked " : ""; ?>/>
        <label for="q_title"><?php echo $lang['title_checkbox']; ?></label>
        <input type="checkbox" id="q_tag" name="tag" value="1" <?php echo isset($_GET['tag']) && $_GET['tag'] == "1" ? "checked " : ""; ?>/>
        <label for="q_tag"><?php echo $lang['tag_checkbox']; ?></label>
        <input type="checkbox" id="q_expl" name="expl" value="1" <?php echo isset($_GET['expl']) && $_GET['expl'] == "1" ? "checked " : ""; ?>/>
        <label for="q_expl"><?php echo $lang['overview_checkbox']; ?></label>
        <input type="checkbox" id="q_author" name="author" value="1" <?php echo isset($_GET['author']) && $_GET['author'] == "1" ? "checked " : ""; ?>/>
        <label for="q_author"><?php echo $lang['author_checkbox']; ?></label>
  
        <input type="radio" name="t" value="all"<?php echo isset($_GET['t']) && $_GET['t'] == "all" || !isset($_GET['t']) ? " checked" : ""; ?>><?php echo $lang['all_radio']; ?></input>
        <input type="radio" name="t" value="vps"<?php echo isset($_GET['t']) && $_GET['t'] == "vps" ? " checked" : ""; ?>><?php echo $lang['vps_radio']; ?></input>
        <input type="radio" name="t" value="material"<?php echo isset($_GET['t']) && $_GET['t'] == "material" ? " checked" : ""; ?>><?php echo $lang['material_radio']; ?></input>
    </form>
    <br />
    <?php

    $video_contents_html = '';
      
    $index = [];
    $c = $view_c = 0;
    if (file_exists("data/index.json")) {
        $index = json_decode(file_get_contents("data/index.json"), true);
    }

    $page = 1;
    if (isset($_GET['page']))
        $page = (int) $_GET['page'];

    $words = [];
    if (isset($_GET['q']))
        $words = explode(" ", $_GET['q']);
    foreach ($index as $id => $data) {
        $continue = false;
        foreach ($words as $word) {
            if (!isset($_GET['method']) || $_GET['method'] == "and")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] == "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] == "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] == "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word))) 
                    && (!isset($_GET['tag']) || !$_GET['tag'] == "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    )
                {
                    $continue = true;
                }
            if (isset($_GET['method']) && $_GET['method'] == "or")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] == "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] == "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] == "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word)))
                    && (!isset($_GET['tag']) || !$_GET['tag'] == "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    ) 
                {
                    if ($continue == false) $continue = 0;
                    ++$continue;
                }
        }

        if (isset($_GET['t']) && $_GET['t'] !== "all") {
            if ($_GET['t'] != $data['type']) continue;
        }
        if ($continue === true || count($words) > 0 && $continue >= count($words)) continue;
        
        ++$c;
        if ($c < $page - 1) continue;

        ++$view_c;
        if ($view_c >= MAX_VIEW) break;
        $description = mb_substr($data['description'], 0, 100, "UTF-8");
        if (mb_strlen($data['description']) > 100) {
            $description .= "...";
        }

        $ago = "";
        if (isset($data['publishedAt']))
            $ago = time_elapsed_string($data['publishedAt']);

        $view_str = number_format($data['view']);
        if (isset($data['is_nicovideo']) && $data['is_nicovideo'] == true) {
          // ニコニコ
        $video_contents_html .= <<<EOD
        <div style="clear:both;">
            <div id="content_{$id}" style="float:left;margin-right:8px;">
                <a href="javascript:onClickThumbNC('{$id}');"><img id="{$id}" src="./cache/thumb/{$id}.jpg" style="width:320px;height:180px;object-fit:cover;" /></a>
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
                <a href="javascript:onClickThumb('{$id}');"><img id="{$id}" src="https://i.ytimg.com/vi/{$id}/hqdefault.jpg" style="width:320px;height:180px;object-fit:cover;" /></a>
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
    if ($page !== 1)
        $page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . '&t=' . $t . '&page=' . ($page - 50) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    $move_n = round(9 / 2);
    $page_count = 0;

    if (file_exists("data/index.json"))
        $page_count = count(json_decode(file_get_contents("data/index.json"), true));

    $num_op_tag = '';
    for ($c = 1;$c <= 9; ++$c) {	
        $n = round(($page + MAX_VIEW) / MAX_VIEW);
        $disp_num = $c;
        if ($n > $move_n) $disp_num = $c + $n - $move_n;
        $f = $disp_num * MAX_VIEW - MAX_VIEW + 1;
        if ($f > $page_count) break;
        $num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . '&t=' . $t . '&page=' . ($f) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';
        if ($f == $page && $view_c < MAX_VIEW) break;
    }
    $page_switch_html .= $num_op_tag;
    if ($view_c >= MAX_VIEW) {
      $page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . '&t=' . $t . '&page=' . ($page + 50) . '"><strong>' . $lang['next'] . '</strong></a>';
    } else {
      $page_switch_html .= '<strong>...</strong>';
    }
    $page_switch_html .= '</div>';

    echo $page_switch_html . "\n<hr />" . $video_contents_html . "\n<br />\n<div style=\"clear:both;\"><hr /></div>\n" . $page_switch_html;
}
?>
<br />
<span style="font-size:12px;">Languages: <a href="./" >日本語</a>, <a href="./en.php" >English</a>, <a href="./zh.php" >中国语</a>, <a href="./ko.php" >한국인</a><br /><br />※当サイトのデータは再生リストから取得したものです。<br />ソース: <a href="https://github.com/PTOM76/VPS-searcher">Gitリポジトリ</a><br />Copyright 2023 © Pitan.</span>
</div>
</body>
</html>
<?php
