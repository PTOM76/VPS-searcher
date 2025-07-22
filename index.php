<?php
require_once "secret.ini.php";
require_once "config.ini.php";
require_once "lang.ini.php";
require_once './lib/auth.php';
require_once './lib/common.php';

if (isset($_GET['q'])) {
  $analytics = "./data/analytics/" . date("Y-m-d") . ".txt";
  $data = '';
  if (file_exists($analytics))
    $data = file_get_contents($analytics);
  file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
}

if (isset($_GET[getSecretValue('PASS')])) {
  addPlaylist("PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5", "vps", false, true);
  addPlaylist("PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM", "material", false, true);
}

if (isset($_GET['replace_' . getSecretValue('PASS')])) {

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

if (isset($_GET["update2_" . getSecretValue('PASS')])) {
        $playlists = json_decode(file_get_contents("data/playlists.json"), true);
        foreach($playlists as $id => $data) {
            addPlaylist($id, $data['type'], false, false, true);
        }
}

if (file_exists("time.txt")) {
    $time = (int) file_get_contents("time.txt");
    if ($time + 86400 < time() || isset($_GET['update_' . getSecretValue('PASS')])) {
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
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang];
Auth::setLanguage($lang);

// ユーザー情報を取得
$currentUser = Auth::getCurrentUser();

global $notice;

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

// HTMLヘッダーを出力
renderHtmlHead($lang['title'], $useLang, false);

// ナビゲーションバーを出力
renderNavigation($lang, $useLang, $currentUser);

// モバイルメニューを出力
renderMobileMenu($lang, $useLang, $currentUser);
?>
    <div id="contents">
    
    <?php echo empty($notice) ? "" : '<h3>' . $notice . '</h3>'; ?>
<?php
if (isset($_GET['post'])) {
    include 'page/post.php';
} else if (isset($_GET['post_' . getenv('PASS')])) {
    include 'page/post_admin.php';
} else if (isset($_GET['report'])) {
    include 'page/report.php';
} else if (isset($_GET['info'])) {
    include 'page/info.php';
} else if (isset($_GET['do'])) {
    switch ($_GET['do']) {
        case 'login':
            include 'action/login.php';
            break;
        case 'logout':
            include 'action/logout.php';
            break;
        case 'register':
            include 'action/register.php';
            break;
        case 'account':
            include 'action/account.php';
            break;
        case 'favorites':
            include 'action/favorites.php';
            break;
        default:
            header('Location: ./');
            exit;
    }
} else {
    // 動画検索・表示のメイン処理
    processVideoSearch($lang, $currentUser, false);
}
?>
<br />
<?php renderFooter($lang, $useLang, false); ?>
</div>
</body>
</html>
