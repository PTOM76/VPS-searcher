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
}
if (isset($_GET['post_' . getenv('PASS')])) {
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

        // 共通関数を使用して動画HTMLを生成
        $video_contents_html .= generateVideoHtml($id, $data, $lang, $currentUser);
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
<?php renderFooter($lang, $useLang, false); ?>
</div>
</body>
</html>
