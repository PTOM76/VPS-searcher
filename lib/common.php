<?php
// 共通関数とユーティリティ

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
            $v = $diff->$k . '' . $v . ($diff->$k > 1 ? ($useLang === "en" ? 's ' : '') : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . $lang['ago'] : $lang['justnow'];
}

// HTMLヘッダーの出力
function renderHtmlHead($title, $useLang = "ja", $isMatrix = false) {
    global $currentUser, $lang;

    $pathPrefix = $isMatrix ? '../' : '';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <link rel="icon" type="image/png" href="/favicon.png" />
        <script src="<?php echo $pathPrefix; ?>darkmode.js"></script>
        <script src="<?php echo $pathPrefix; ?>main.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo $pathPrefix; ?>main.css" />
        <script>
            // ログイン状態と翻訳データをJavaScriptに渡す
            window.isLoggedIn = <?php echo $currentUser ? 'true' : 'false'; ?>;
            window.translations = {
                login_required: '<?php echo addslashes($lang['login_required']); ?>',
                add_to_favorites: '<?php echo addslashes($lang['add_to_favorites']); ?>',
                remove_from_favorites: '<?php echo addslashes($lang['remove_from_favorites']); ?>',
                added_to_favorites: '<?php echo addslashes($lang['added_to_favorites']); ?>',
                removed_from_favorites: '<?php echo addslashes($lang['removed_from_favorites']); ?>',
                error_occurred: '<?php echo addslashes($lang['error_occurred']); ?>'
            };
        </script>
    </head>
    <body>
    <?php
}

// ナビゲーションバーの出力
function renderNavigation($lang, $useLang, $currentUser, $isMatrix = false) {
    $pathPrefix = $isMatrix ? '../' : '';
    $matrixPath = $isMatrix ? './' : './matrix/';
    $matrixImage = $isMatrix ? 'vertical' : 'matrix';
    ?>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="pc"><a href="<?php echo $pathPrefix; ?>?info"><?php echo $lang['info']; ?></a></li>
            <li class="pc"><a href="<?php echo $pathPrefix; ?>?post"><?php echo $lang['send_pl']; ?></a></li>
            <li class="dropdown pc">
                <a href="javascript:void(0)" class="dropbtn">Language</a>
                <div class="dropdown-content">
                    <a href="./<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/japanese.png" /> 日本語</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'en.php' : ($isMatrix ? '../en.php' : 'en.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/english.png" /> English</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'zh.php' : ($isMatrix ? '../zh.php' : 'zh.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/chinese.png" /> 中国语</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'ko.php' : ($isMatrix ? '../ko.php' : 'ko.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/korean.png" /> 한국인</a>
                </div>
            </li>

            <li><a href="<?php echo $matrixPath . ($useLang === "ja" ? "" : $useLang . ".php"); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/<?php echo $matrixImage; ?>.png" /></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="<?php echo $pathPrefix; ?>image/darkmode.png" /></a></li>

            <!-- 右寄せ要素用のスペーサー -->
            <li class="nav-spacer noborder"></li>
            
            <?php if ($currentUser): ?>
                <li class="pc noborder nav-right"><a href="<?php echo $pathPrefix; ?>account.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo htmlspecialchars($currentUser['username']); ?></a></li>
                <li class="pc nav-right"><a href="<?php echo $pathPrefix; ?>login.php?logout"><?php echo $lang['logout']; ?></a></li>
            <?php else: ?>
                <li class="pc nav-right"><a href="<?php echo $pathPrefix; ?>login.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo $lang['login']; ?></a></li>
            <?php endif; ?>

            <li class="sp noborder nav-right" id="menu"><a href="javascript:openSpMenu()"><img src="<?php echo $pathPrefix; ?>image/menu.png" /></a></li>
        </ul>
    </div>
    <?php
}

// モバイルメニューの出力
function renderMobileMenu($lang, $useLang, $currentUser, $isMatrix = false) {
    $pathPrefix = $isMatrix ? '../' : '';
    ?>
    <div id="menu_sp" data-isopen="false">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="none"><br /></li>
            <li><a href="<?php echo $pathPrefix; ?>?info"><?php echo $lang['info']; ?></a></li>
            <li><a href="<?php echo $pathPrefix; ?>?post"><?php echo $lang['send_pl']; ?></a></li>
            <?php if ($currentUser): ?>
                <li><a href="<?php echo $pathPrefix; ?>favorites.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo $lang['favorites']; ?></a></li>
            <?php endif; ?>
            <li class="none"><br /></li>
            <li><a href="./matrix/<?php echo $useLang === "ja" ? "" : $useLang . ".php"; ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><?= $lang['matrixview'] ?></a></li>
            <li class="none"><br /></li>
            <?php if ($currentUser) { ?>
                <li><span style="color: var(--text-color);"><?php echo htmlspecialchars($currentUser['username']); ?></span></li>
                <li><a href="<?php echo $pathPrefix; ?>login.php?logout"><?php echo $lang['logout']; ?></a></li>
                <li class="none"><br /></li>
            <?php } else { ?>
                <li><a href="<?php echo $pathPrefix; ?>login.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo $lang['login']; ?></a></li>
                <li class="none"><br /></li>
            <?php } ?>
            <li><a href="./<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">日本語</a></li>
            <li><a href="./<?php echo $useLang === 'ja' ? 'en.php' : ($isMatrix ? '../en.php' : 'en.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">English</a></li>
            <li><a href="./<?php echo $useLang === 'ja' ? 'zh.php' : ($isMatrix ? '../zh.php' : 'zh.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">中国语</a></li>
            <li><a href="./<?php echo $useLang === 'ja' ? 'ko.php' : ($isMatrix ? '../ko.php' : 'ko.php'); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>">한국인</a></li>            
        </ul>
    </div>
    <br /><br />
    <?php
}

// お気に入りボタンの生成
function generateFavoriteButton($videoId, $title, $thumbnailUrl, $currentUser) {
    global $lang;
    
    if (!$currentUser) {
        return '';
    }
    
    require_once __DIR__ . '/../lib/auth.php';
    $is_favorite = Auth::isFavorite($currentUser['id'], $videoId);
    $favorite_class = $is_favorite ? 'favorited' : '';
    $favorite_text = $is_favorite ? '★' : '☆';
    $favorite_title = $is_favorite ? $lang['remove_from_favorites'] : $lang['add_to_favorites'];
    
    // タイトルのエスケープ処理（改行、引用符、特殊文字を処理）
    $escaped_title = str_replace(["\n", "\r", "\t", "'", '"', "\\"], ["\\n", "\\r", "\\t", "\\'", '\\"', "\\\\"], $title);
    
    return "<span class=\"favorite-btn {$favorite_class}\" onclick=\"toggleFavorite('{$videoId}', '{$escaped_title}', '{$thumbnailUrl}')\" title=\"{$favorite_title}\">{$favorite_text}</span>";
}

// 動画表示用のHTMLを生成
function generateVideoHtml($id, $data, $lang, $currentUser) {
    $description = mb_substr($data['description'], 0, 100, "UTF-8");
    if (mb_strlen($data['description']) > 100) {
        $description .= "...";
    }

    // htmlエスケープ
    $title = htmlspecialchars($data['title'], ENT_QUOTES);
    $channelTitle = htmlspecialchars($data['channelTitle'], ENT_QUOTES);
    $description = htmlspecialchars($description, ENT_QUOTES);

    $ago = "";
    if (isset($data['publishedAt']))
        $ago = time_elapsed_string($data['publishedAt']);

    $view_str = number_format($data['view']);
    
    // お気に入りボタンのHTML
    $thumbnail_url = isset($data['is_nicovideo']) && $data['is_nicovideo'] === true 
        ? "./cache/thumb/{$id}.jpg" 
        : "https://i.ytimg.com/vi/{$id}/hqdefault.jpg";
    
    $favorite_button_html = generateFavoriteButton($id, $title, $thumbnail_url, $currentUser);
    
    if (isset($data['is_nicovideo']) && $data['is_nicovideo'] === true) {
        // ニコニコ動画
        return <<<EOD
        <div style="clear:both;">
            <div id="content_{$id}" style="float:left;margin-right:8px;">
                <a href="javascript:onClickThumbNC('{$id}');"><img id="{$id}" loading="lazy" data-src="./cache/thumb/{$id}.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </div>
            <div>
                <span style="font-size:18px;"><a target="_blank" class="plain" href="https://www.nicovideo.jp/watch/{$id}">{$title}</a> {$favorite_button_html}</span><br />
                <span style="font-size:11px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:15px;"><a class="plain" href="https://www.nicovideo.jp/user/{$data['channelId']}">{$channelTitle}</a></span>
                <br />
                <span style="font-size:11px;">{$description}</span>
                <br />
                <span style="font-size:12px;"><a href="./?report&id={$id}&is_nicovideo=true">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://www.nicovideo.jp/watch/{$id}');">URL{$lang['copy']}</a></span>
            </div>
        </div>
        EOD;
    } else {
        // YouTube動画
        return <<<EOD
        <div style="clear:both;">
            <div id="content_{$id}" style="float:left;margin-right:8px;">
                <a href="javascript:onClickThumb('{$id}');"><img id="{$id}" loading="lazy" data-src="https://i.ytimg.com/vi/{$id}/hqdefault.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </div>
            <div>
                <span style="font-size:18px;"><a target="_blank" class="plain" href="https://youtu.be/{$id}">{$title}</a> {$favorite_button_html}</span><br />
                <span style="font-size:11px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:15px;"><a class="plain" href="https://www.youtube.com/channel/{$data['channelId']}">{$channelTitle}</a></span>
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

/**
 * サイトフッターを生成
 */
function renderFooter($lang, $useLang, $isMatrix = false) {
    $pathPrefix = $isMatrix ? './' : './';
    $matrixPrefix = $isMatrix ? './' : './matrix/';
    
    $repositoryUrl = SITE_REPOSITORY_URL;
    $author = SITE_AUTHOR;
    $copyrightYears = SITE_COPYRIGHT_YEARS;
    
    echo <<<EOD
<span style="font-size:12px;">{$lang['languages']}: 
<a href="{$pathPrefix}" >日本語</a>, 
<a href="{$pathPrefix}en.php" >English</a>, 
<a href="{$pathPrefix}zh.php" >中国语</a>, 
<a href="{$pathPrefix}ko.php" >한국인</a><br /><br />
{$lang['data_note']}<br />
{$lang['source']}: <a href="{$repositoryUrl}">{$lang['git_repository']}</a><br />
Copyright {$copyrightYears} © {$author}.</span>
EOD;
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