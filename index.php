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
