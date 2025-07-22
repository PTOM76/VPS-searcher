<?php
// 共通関数とユーティリティ
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error_handler.php';

/**
 * アナリティクス処理
 */
function handleAnalytics() {
    if (isset($_GET['q'])) {
        FilePaths::ensureDirectoryExists(FilePaths::ANALYTICS_DIR);
        $analytics = FilePaths::ANALYTICS_DIR . date("Y-m-d") . ".txt";
        $data = '';
        if (file_exists($analytics))
            $data = file_get_contents($analytics);
        file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
    }
}

/**
 * プレイリスト管理API処理
 */
function handlePlaylistAPI() {
    if (isset($_GET[getSecretValue('PASS')])) {
        addPlaylist(PlaylistConfig::VPS_PLAYLIST_ID, "vps", false, true);
        addPlaylist(PlaylistConfig::MATERIAL_PLAYLIST_ID, "material", false, true);
    }

    if (isset($_GET['replace_' . getSecretValue('PASS')])) {
        if (isset($_GET['vps_nexttoken'])) 
            $next = addPlaylist(PlaylistConfig::VPS_PLAYLIST_ID, "vps", $_GET['vps_nexttoken'], false);
        else 
            $next = addPlaylist(PlaylistConfig::VPS_PLAYLIST_ID, "vps", false, false);
        if (isset($_GET['material_nexttoken'])) 
            $next2 = addPlaylist(PlaylistConfig::MATERIAL_PLAYLIST_ID, "material", $_GET['material_nexttoken'], false);
        else 
            $next2 = addPlaylist(PlaylistConfig::MATERIAL_PLAYLIST_ID, "material", false, false);
        echo 'vps_nexttoken: ' . $next . ' , ';
        echo 'material_nexttoken: ' . $next2;
        exit;
    }

    if (isset($_GET["update2_" . getSecretValue('PASS')])) {
        if (file_exists(FilePaths::PLAYLISTS_JSON)) {
            $playlists = json_decode(file_get_contents(FilePaths::PLAYLISTS_JSON), true);
            foreach($playlists as $id => $data) {
                addPlaylist($id, $data['type'], false, false, true);
            }
        }
    }
}

/**
 * ファイル更新処理
 */
function handleFileUpdates() {
    if (file_exists(FilePaths::TIME_TXT)) {
        $time = (int) file_get_contents(FilePaths::TIME_TXT);
        if ($time + AppConstants::UPDATE_INTERVAL < time() || isset($_GET['update_' . getSecretValue('PASS')])) {
            file_put_contents(FilePaths::TIME_TXT, time());
            if (file_exists(FilePaths::PLAYLISTS_JSON)) {
                $playlists = json_decode(file_get_contents(FilePaths::PLAYLISTS_JSON), true);
                foreach($playlists as $id => $data) {
                    addPlaylist($id, $data['type']);
                }
            }
        }
    } else {
        file_put_contents(FilePaths::TIME_TXT, time());
        if (file_exists(FilePaths::PLAYLISTS_JSON)) {
            $playlists = json_decode(file_get_contents(FilePaths::PLAYLISTS_JSON), true);
            foreach($playlists as $id => $data) {
                addPlaylist($id, $data['type']);
            }
        }
    }
}

/**
 * URL種別の判定
 */
function getUrlType($url) {
    if (false !== strpos($url, 'list=') || str_starts_with($url, 'PL')) {
        return "playlist";
    } else if (false !== strpos($url, 'watch/sm') || str_starts_with($url, 'sm')) {
        return "nicovideo";
    } else {
        return "youtube";
    }
}

/**
 * POST処理を統一的に処理
 */
function handlePostRequests($lang) {
    global $notice;
    
    if (!isset($_POST['do'])) {
        return;
    }
    
    $url = $_POST['url'];
    $url_type = getUrlType($url);
    
    switch ($_POST['do']) {
        case 'post':
            handlePublicPost($url, $url_type, $lang);
            break;
        case 'post_' . getenv('PASS'):
            handleAdminPost($url, $url_type, $lang);
            break;
        case 'report':
            handleReport($_POST, $lang);
            break;
    }
}

/**
 * 一般ユーザーの投稿処理
 */
function handlePublicPost($url, $url_type, $lang) {
    global $notice;
    
    switch ($url_type) {
        case "playlist":
            $playlist_id = preg_replace(UrlPatterns::PLAYLIST_ID, '$1', $url);
            FilePaths::ensureDirectoryExists(FilePaths::QUEUE_DIR);
            file_put_contents(FilePaths::QUEUE_DIR . "pl_" . $playlist_id . ".txt", 
                "ID: {$playlist_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);
            $notice .= $lang['sended_pl'];
            break;
            
        case "nicovideo":
            $video_id = preg_replace(UrlPatterns::NICOVIDEO_ID, '$1', $url);
            FilePaths::ensureDirectoryExists(FilePaths::QUEUE_DIR);
            file_put_contents(FilePaths::QUEUE_DIR . "nc_" . $video_id . ".txt", 
                "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);
            $notice .= $lang['sended_vd'];
            break;
            
        case "youtube":
            $video_id = preg_replace(UrlPatterns::YOUTUBE_ID, '$1', $url);
            FilePaths::ensureDirectoryExists(FilePaths::QUEUE_DIR);
            file_put_contents(FilePaths::QUEUE_DIR . "yt_" . $video_id . ".txt", 
                "ID: {$video_id}\nURL: " . $_POST['url'] . "\nType: " . $_POST['t']);
            $notice .= $lang['sended_vd'];
            break;
    }
}

/**
 * 管理者の投稿処理
 */
function handleAdminPost($url, $url_type, $lang) {
    global $notice;
    
    switch ($url_type) {
        case "playlist":
            $playlist_id = preg_replace(UrlPatterns::PLAYLIST_ID, '$1', $url);
            $array = [];
            if (file_exists(FilePaths::PLAYLISTS_JSON))
                $array = json_decode(file_get_contents(FilePaths::PLAYLISTS_JSON), true);
            $array[$playlist_id] = ["type" => $_POST['t']];
            file_put_contents(FilePaths::PLAYLISTS_JSON, json_encode($array));
            addPlaylist($playlist_id, $_POST['t']);
            $notice .= $lang['added_pl'];
            break;
            
        case "nicovideo":
            $video_id = preg_replace(UrlPatterns::NICOVIDEO_ID, '$1', $url);
            $array = [];
            if (file_exists(FilePaths::NC_VIDEOS_JSON))
                $array = json_decode(file_get_contents(FilePaths::NC_VIDEOS_JSON), true);
            $array[$video_id] = ["type" => $_POST['t']];
            file_put_contents(FilePaths::NC_VIDEOS_JSON, json_encode($array));
            addNicovideo($video_id, $_POST['t']);
            $notice .= $lang['added_vd'];
            break;
            
        case "youtube":
            $video_id = preg_replace(UrlPatterns::YOUTUBE_ID, '$1', $url);
            $array = [];
            if (file_exists(FilePaths::YT_VIDEOS_JSON))
                $array = json_decode(file_get_contents(FilePaths::YT_VIDEOS_JSON), true);
            $array[$video_id] = ["type" => $_POST['t']];
            file_put_contents(FilePaths::YT_VIDEOS_JSON, json_encode($array));
            $notice .= $lang['added_vd'];
            break;
    }
}

/**
 * 報告処理
 */
function handleReport($postData, $lang) {
    global $notice;
    
    FilePaths::ensureDirectoryExists(FilePaths::REPORT_DIR);
    $id = $postData['id'];
    file_put_contents(FilePaths::REPORT_DIR . $postData['id'] . "-" . time() . ".txt", 
        "ID: {$id}\nURL: https://youtu.be/{$id}\nType: " . $postData['t'] . 
        "\nReason: " . (isset($postData['reason']) ? $postData['reason'] : 'none'));
    $notice .= $lang['reported_video'];
}

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
    
    // ベースURLを取得
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $useLang; ?>">
    <head>
        <meta charset='utf-8'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <meta name="description" content="ボイパ対決という音MADに特化した検索ツール。YouTube・ニコニコ動画の動画を検索できます。">
        <meta name="keywords" content="ボイパ対決,音MAD,検索,YouTube,ニコニコ動画,VPS">
        <link rel="icon" type="image/png" href="/favicon.png" />
        
        <!-- RSS/Atom フィード -->
        <link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?php echo $baseUrl; ?>/rss.xml">
        <link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?php echo $baseUrl; ?>/feed.atom">
        
        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
        <meta property="og:description" content="ボイパ対決という音MADに特化した検索ツール">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo $baseUrl; ?>">
        <meta property="og:site_name" content="ボ対検索ツール">
        
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
        <meta name="twitter:description" content="ボイパ対決という音MADに特化した検索ツール">
        
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
    $matrixPath = $isMatrix ? '../' : './matrix/';
    $matrixImage = $isMatrix ? 'vertical' : 'matrix';
    ?>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="pc"><a href="<?php echo $pathPrefix; ?>?info"><?php echo $lang['info']; ?></a></li>
            <li class="dropdown pc">
                <a href="javascript:void(0)" class="dropbtn">Language</a>
                <div class="dropdown-content">
                    <a href="./<?= isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/japanese.png" /> 日本語</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'en.php' : ($isMatrix ? '../en.php' : 'en.php'); ?><?= isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/english.png" /> English</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'zh.php' : ($isMatrix ? '../zh.php' : 'zh.php'); ?><?= isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/chinese.png" /> 中国语</a>
                    <a href="./<?php echo $useLang === 'ja' ? 'ko.php' : ($isMatrix ? '../ko.php' : 'ko.php'); ?><?= isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/korean.png" /> 한국인</a>
                </div>
            </li>

            <li><a href="<?php echo $matrixPath . ($useLang === "ja" ? "" : $useLang . ".php"); ?><?= isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="<?php echo $pathPrefix; ?>image/<?php echo $matrixImage; ?>.png" /></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="<?php echo $pathPrefix; ?>image/darkmode.png" /></a></li>

            <!-- 右寄せ要素用のスペーサー -->
            <li class="nav-spacer noborder"></li>
            
            <?php if ($currentUser): ?>
                <li class="pc noborder nav-right"><a href="<?php echo $pathPrefix; ?>?do=account<?php echo $useLang !== "ja" ? "&lang=" . $useLang : ""; ?>"><?php echo htmlspecialchars($currentUser['username']); ?></a></li>
                <li class="pc nav-right"><a href="<?php echo $pathPrefix; ?>?do=logout"><?php echo $lang['logout']; ?></a></li>
            <?php else: ?>
                <li class="pc noborder nav-right"><a href="<?php echo $pathPrefix; ?>?do=login<?php echo $useLang !== "ja" ? "&lang=" . $useLang : ""; ?>"><?php echo $lang['login']; ?></a></li>
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
                <li><a href="<?php echo $pathPrefix; ?>?do=favorites<?php echo $useLang !== "ja" ? "&lang=" . $useLang : ""; ?>"><?php echo $lang['favorites']; ?></a></li>
            <?php endif; ?>
            <li class="none"><br /></li>
            <li><a href="<?= ($isMatrix ? '../' : './matrix/') . ($useLang === "ja" ? "" : $useLang . ".php"); ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><?= $isMatrix ? $lang['listview'] : $lang['matrixview'] ?></a></li>
            <li class="none"><br /></li>
            <?php if ($currentUser) { ?>
                <li><a href="<?php echo $pathPrefix; ?>?do=account<?php echo $useLang !== "ja" ? "&lang=" . $useLang : ""; ?>"><?php echo htmlspecialchars($currentUser['username']); ?></a></li>
                <li><a href="<?php echo $pathPrefix; ?>?do=logout"><?php echo $lang['logout']; ?></a></li>
                <li class="none"><br /></li>
            <?php } else { ?>
                <li><a href="<?php echo $pathPrefix; ?>?do=login<?php echo $useLang !== "ja" ? "&lang=" . $useLang : ""; ?>"><?php echo $lang['login']; ?></a></li>
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
    $description = mb_substr($data['description'], 0, AppConstants::MAX_DESCRIPTION_LENGTH, "UTF-8");
    if (mb_strlen($data['description']) > AppConstants::MAX_DESCRIPTION_LENGTH) {
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
        return generateNicovideoHtml($id, $data, $title, $channelTitle, $description, $view_str, $ago, $favorite_button_html, $lang);
    } else {
        return generateYoutubeHtml($id, $data, $title, $channelTitle, $description, $view_str, $ago, $favorite_button_html, $lang);
    }
}

/**
 * ニコニコ動画のHTML生成
 */
function generateNicovideoHtml($id, $data, $title, $channelTitle, $description, $view_str, $ago, $favorite_button_html, $lang) {
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
}

/**
 * YouTube動画のHTML生成
 */
function generateYoutubeHtml($id, $data, $title, $channelTitle, $description, $view_str, $ago, $favorite_button_html, $lang) {
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
<strong>XML/Feed:</strong> <a href="{$pathPrefix}sitemap.xml">Sitemap</a> | <a href="{$pathPrefix}rss.xml">RSS</a> | <a href="{$pathPrefix}feed.atom">Atom</a><br />
Copyright {$copyrightYears} © {$author}.</span>
EOD;
}

function addNicovideo($video_id, $type) {
    $api_url = "https://ext.nicovideo.jp/api/getthumbinfo/" . $video_id;
    $xml = file_get_contents($api_url);
    $array = json_decode(json_encode(simplexml_load_string($xml)), true);

    $index = [];
    if (file_exists(FilePaths::INDEX_JSON))
        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
      
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

    // サムネイル保存処理
    FilePaths::ensureDirectoryExists(FilePaths::CACHE_THUMB_DIR);
    file_put_contents(FilePaths::CACHE_THUMB_DIR . $video_id . ".jpg", file_get_contents($thumb['thumbnail_url']));

    $index = ((array) $index);
    array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        
    file_put_contents(FilePaths::INDEX_JSON, json_encode($index, JSON_UNESCAPED_UNICODE));
    return;
}

function addPlaylist($playlist_id, $type, $nextPageToken = false, $only = false, $nextWithOnly = false) {
    global $blacklist;

    if (!isset($blacklist)) {
        $blacklist = json_decode(file_get_contents(FilePaths::BLACKLIST_JSON), true);
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
    FilePaths::ensureDirectoryExists(FilePaths::DATA_DIR);
    file_put_contents(FilePaths::DATA_DIR . $c . "-" . $playlist_id . ".json", json_encode($output, JSON_UNESCAPED_UNICODE));

    if (file_exists(FilePaths::INDEX_JSON)) {
        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
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
    
    file_put_contents(FilePaths::INDEX_JSON, json_encode($index, JSON_UNESCAPED_UNICODE));

    if ((!$only || $nextWithOnly) && isset($output->nextPageToken)) {
        addPlaylist($playlist_id, $type, $output->nextPageToken);
    } else if (isset($output->nextPageToken)) {
        return $output->nextPageToken;
    }
}

/**
 * 検索フォームを生成
 */
function renderSearchForm($lang, $queryParams, $isMatrix = false) {
    $q = $queryParams['q'] ?? '';
    $method = $queryParams['method'] ?? 'and';
    $sort = $queryParams['sort'] ?? 'recent';
    ?>
    <form method="GET">
        <select name="sort">
            <option value="recent" <?php echo $sort === "recent" ? " selected" : ""; ?>><?php echo $lang['sort_recent']; ?></option>
            <option value="ancient" <?php echo $sort === "ancient" ? " selected" : ""; ?>><?php echo $lang['sort_ancient']; ?></option>
            <option value="most_view" <?php echo $sort === "most_view" ? " selected" : ""; ?>><?php echo $lang['sort_most_view']; ?></option>
            <option value="worst_view" <?php echo $sort === "worst_view" ? " selected" : ""; ?>><?php echo $lang['sort_worst_view']; ?></option>
            <option value="most_like" <?php echo $sort === "most_like" ? " selected" : ""; ?>><?php echo $lang['sort_most_like']; ?></option>
            <option value="word" <?php echo $sort === "word" ? " selected" : ""; ?>><?php echo $lang['sort_word']; ?></option>
            <option value="word_desc" <?php echo $sort === "word_desc" ? " selected" : ""; ?>><?php echo $lang['sort_word_desc']; ?></option>
            <option value="random" <?php echo $sort === "random" ? " selected" : ""; ?>><?php echo $lang['sort_random']; ?></option>
        </select>

        <input type="text" name ="q" value="<?php echo htmlspecialchars($q); ?>" />
        <input type="submit" value="<?php echo $lang['search']; ?>" />
        
        <input type="radio" name="method" value="and"<?php echo $method === "and" || !isset($queryParams['method']) ? " checked" : ""; ?>><?php echo $lang['and_search']; ?></input>
        <input type="radio" name="method" value="or"<?php echo $method === "or" ? " checked" : ""; ?>><?php echo $lang['or_search']; ?></input>

        <div class="flex-break"></div>

        <input type="checkbox" id="q_title" name="title" value="1" <?php echo (isset($queryParams['title']) && $queryParams['title'] === "1") || !isset($queryParams['q']) ? "checked " : ""; ?>/>
        <label for="q_title"><?php echo $lang['title_checkbox']; ?></label>
        <input type="checkbox" id="q_tag" name="tag" value="1" <?php echo isset($queryParams['tag']) && $queryParams['tag'] === "1" ? "checked " : ""; ?>/>
        <label for="q_tag"><?php echo $lang['tag_checkbox']; ?></label>
        <input type="checkbox" id="q_expl" name="expl" value="1" <?php echo isset($queryParams['expl']) && $queryParams['expl'] === "1" ? "checked " : ""; ?>/>
        <label for="q_expl"><?php echo $lang['overview_checkbox']; ?></label>
        <input type="checkbox" id="q_author" name="author" value="1" <?php echo isset($queryParams['author']) && $queryParams['author'] === "1" ? "checked " : ""; ?>/>
        <label for="q_author"><?php echo $lang['author_checkbox']; ?></label>

        |

        <input type="checkbox" id="q_unlisted" name="status" value="unlisted" <?php echo isset($queryParams['status']) && $queryParams['status'] === "unlisted" ? "checked " : ""; ?>/>
        <label for="q_unlisted"><?php echo $lang['unlisted_checkbox']; ?></label>

        <input type="checkbox" id="q_deleted" name="deleted" value="1" <?php echo isset($queryParams['deleted']) && $queryParams['deleted'] === "1" ? "checked " : ""; ?>/>
        <label for="q_deleted"><?php echo $lang['deleted_checkbox']; ?></label>
        
        |

        <input type="radio" name="t" value="all"<?php echo (isset($queryParams['t']) && $queryParams['t'] === "all") || !isset($queryParams['t']) ? " checked" : ""; ?>><?php echo $lang['all_radio']; ?></input>
        <input type="radio" name="t" value="vps"<?php echo isset($queryParams['t']) && $queryParams['t'] === "vps" ? " checked" : ""; ?>><?php echo $lang['vps_radio']; ?></input>
        <input type="radio" name="t" value="material"<?php echo isset($queryParams['t']) && $queryParams['t'] === "material" ? " checked" : ""; ?>><?php echo $lang['material_radio']; ?></input>
        <input type="checkbox" id="ai_class" name="ai_class" value="1" <?php echo isset($queryParams['ai_class']) && $queryParams['ai_class'] === "1" ? "checked " : ""; ?>/>
        <label for="ai_class"><?php echo $lang['use_ai_class']; ?></label>
    </form>
    <br />
    <?php
}

/**
 * データインデックスを読み込み、並び替えを行う
 */
function loadAndSortIndex($queryParams, $isMatrix = false) {
    $dataPath = $isMatrix ? '../' . FilePaths::DATA_DIR : FilePaths::DATA_DIR;
    
    $index = [];
    if (isset($queryParams['ai_class']) && $queryParams['ai_class'] == "1" && file_exists($dataPath . "ai_index.json")) {
        $index = json_decode(file_get_contents($dataPath . "ai_index.json"), true);
    } elseif (file_exists($dataPath . "index.json")) {
        $index = json_decode(file_get_contents($dataPath . "index.json"), true);
    }

    // 削除された動画を除外
    if (!isset($queryParams['deleted']) || $queryParams['deleted'] === "0") {
        foreach ($index as $id => $data) {
            if ($data['publishedAt'] === false && 
                ($data['title'] === "Deleted video" || $data['title'] === "Private video")
            ) {
                unset($index[$id]);
            }
        }
    }

    // 並び替え
    $sort = $queryParams['sort'] ?? 'recent';
    if ($sort != 'recent') {
        $sort_array = [];
        foreach ($index as $id => $data) {
            if (!isset($data['like'])) $index[$id]['like'] = 0;
            $sort_array[$id] = $data['publishedAt'];
        }
        
        switch ($sort) {
            case 'ancient':
                array_multisort($sort_array, SORT_ASC, $index);
                break;
            case 'most_view':
                array_multisort(array_column($index, 'view'), SORT_DESC, $index);
                break;
            case 'worst_view':
                array_multisort(array_column($index, 'view'), SORT_ASC, $index);
                break;
            case 'most_like':
                array_multisort(array_column($index, 'like'), SORT_DESC, $index);
                break;
            case 'word':
                array_multisort(array_column($index, 'title'), SORT_ASC, $index);
                break;
            case 'word_desc':
                array_multisort(array_column($index, 'title'), SORT_DESC, $index);
                break;
            case 'random':
                $keys = array_keys($index);
                shuffle($keys);
                $shuffled_index = [];
                foreach ($keys as $key) {
                    $shuffled_index[$key] = $index[$key];
                }
                $index = $shuffled_index;
                break;
        }
    }

    return $index;
}

/**
 * 検索フィルタリングを行う
 */
function filterVideos($index, $queryParams) {
    $words = [];
    if (!empty($queryParams['q'])) {
        $words = explode(" ", $queryParams['q']);
    }

    $filtered = [];
    foreach ($index as $id => $data) {
        $continue = false;
        foreach ($words as $word) {
            if (!isset($queryParams['method']) || $queryParams['method'] === "and") {
                if (
                       (!isset($queryParams['title']) || $queryParams['title'] !== "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($queryParams['expl']) || $queryParams['expl'] !== "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($queryParams['author']) || $queryParams['author'] !== "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word))) 
                    && (!isset($queryParams['tag']) || $queryParams['tag'] !== "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                ) {
                    $continue = true;
                }
            }
            if (isset($queryParams['method']) && $queryParams['method'] === "or") {
                if (
                       (!isset($queryParams['title']) || $queryParams['title'] !== "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($queryParams['expl']) || $queryParams['expl'] !== "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($queryParams['author']) || $queryParams['author'] !== "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word)))
                    && (!isset($queryParams['tag']) || $queryParams['tag'] !== "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                ) {
                    if ($continue === false) $continue = 0;
                    ++$continue;
                }
            }
        }

        if (isset($queryParams['t']) && $queryParams['t'] !== "all") {
            if ($queryParams['t'] != $data['type']) continue;
        }
        if (isset($queryParams['status']) && $queryParams['status'] !== "public") {
            if (!isset($data['status'])) continue;
            if ($queryParams['status'] != $data['status']) continue;
        }

        if (!isset($queryParams['deleted']) || $queryParams['deleted'] === "0") {
            if (isset($data['error']) && !empty($data['error'])) 
                continue;
        }

        if ($continue === true || (count($words) > 0 && $continue >= count($words))) continue;
        
        $filtered[$id] = $data;
    }

    return $filtered;
}

/**
 * ページネーションを生成
 */
function generatePagination($page, $viewCount, $totalCount, $queryParams, $lang) {
    $page_switch_html = '<div style="clear:both;">';
    
    if ($page !== 1) {
        $param_array = $queryParams;
        $param_array['page'] = $page - 50;
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    }
    
    $move_n = round(9 / 2);
    $num_op_tag = '';
    $c = 1;
    while ($c <= 9) {
        $n = round(($page + MAX_VIEW) / MAX_VIEW);
        $disp_num = $c;
        if ($n > $move_n) $disp_num = $c + $n - $move_n;
        $f = $disp_num * MAX_VIEW - MAX_VIEW + 1;
        if ($f > $totalCount) break;

        $param_array = $queryParams;
        $param_array['page'] = $f;
        $num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="' . make_param($param_array) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';

        if ($f === $page && $viewCount < MAX_VIEW) break;
        ++$c;
    }
    $page_switch_html .= $num_op_tag;
    
    if ($viewCount >= MAX_VIEW) {
        $param_array = $queryParams;
        $param_array['page'] = $page + 50;
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['next'] . '</strong></a>';
    } else {
        $page_switch_html .= '<strong>...</strong>';
    }
    $page_switch_html .= '</div>';

    return $page_switch_html;
}

/**
 * マトリックス表示用の動画HTMLを生成
 */
function generateMatrixVideoHtml($id, $data, $lang, $isMatrix = false) {
    $pathPrefix = $isMatrix ? '../' : '';
    
    // HTMLエスケープ
    $title = htmlspecialchars($data['title'], ENT_QUOTES);
    $channelTitle = htmlspecialchars($data['channelTitle'], ENT_QUOTES);

    $ago = "";
    if (isset($data['publishedAt']))
        $ago = time_elapsed_string($data['publishedAt']);

    $view_str = number_format($data['view']);
    
    if (isset($data['is_nicovideo']) && $data['is_nicovideo'] === true) {
        // ニコニコ動画
        return <<<EOD
        <div class="matrix_box">
            <span id="content_{$id}" style="margin-right:8px;">
                <a href="javascript:onClickThumbNC('{$id}');"><img id="{$id}" loading="lazy" data-src="./cache/thumb/{$id}.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </span>
            <br />
            <span>
                <span style="font-size:15px;"><a target="_blank" class="plain" href="https://www.nicovideo.jp/watch/{$id}">{$title}</a></span><br />
                <span style="font-size:12px;"><a class="plain" href="https://www.nicovideo.jp/user/{$data['channelId']}">{$channelTitle}</a></span>
                <span style="font-size:12px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:12px;"><a href="{$pathPrefix}?report&id={$id}&is_nicovideo=true">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://www.nicovideo.jp/watch/{$id}');">URL{$lang['copy']}</a></span>
            </span>
        </div>
        EOD;
    } else {
        // YouTube動画
        return <<<EOD
        <div class="matrix_box">
            <span id="content_{$id}" style="margin-right:8px;">
                <a href="javascript:onClickThumb('{$id}');"><img id="{$id}" loading="lazy" data-src="https://i.ytimg.com/vi/{$id}/hqdefault.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </span>
            <br />
            <span>
                <span style="font-size:15px;"><a target="_blank" class="plain" href="https://youtu.be/{$id}">{$title}</a></span><br />
                <span style="font-size:12px;"><a class="plain" href="https://www.youtube.com/channel/{$data['channelId']}">{$channelTitle}</a></span>
                <span style="font-size:12px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:12px;"><a href="{$pathPrefix}?report&id={$id}">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://youtu.be/{$id}');">URL{$lang['copy']}</a></span>
            </span>
        </div>
        EOD;
    }
}

/**
 * 動画検索・表示のメイン処理
 */
function processVideoSearch($lang, $currentUser, $isMatrix = false) {
    // クエリパラメータを取得
    $queryParams = [
        'q' => $_GET['q'] ?? '',
        'method' => $_GET['method'] ?? 'and',
        'title' => $_GET['title'] ?? '1',
        'expl' => $_GET['expl'] ?? '0',
        'author' => $_GET['author'] ?? '0',
        't' => $_GET['t'] ?? 'all',
        'sort' => $_GET['sort'] ?? 'recent',
        'page' => (int)($_GET['page'] ?? 1),
        'status' => $_GET['status'] ?? null,
        'deleted' => $_GET['deleted'] ?? null,
        'ai_class' => $_GET['ai_class'] ?? null,
        'tag' => $_GET['tag'] ?? null
    ];

    // 検索フォームを表示
    renderSearchForm($lang, $queryParams, $isMatrix);

    // データを読み込み、並び替え
    $index = loadAndSortIndex($queryParams, $isMatrix);

    // フィルタリング
    $filtered = filterVideos($index, $queryParams);

    // ページネーション処理
    $c = $view_c = 0;
    $video_contents_html = '';
    $page = $queryParams['page'];

    foreach ($filtered as $id => $data) {
        ++$c;
        if ($c < $page) continue;

        if ($view_c >= MAX_VIEW) break;
        ++$view_c;

        // 動画HTMLを生成
        if ($isMatrix) {
            $video_contents_html .= generateMatrixVideoHtml($id, $data, $lang, true);
        } else {
            $video_contents_html .= generateVideoHtml($id, $data, $lang, $currentUser);
        }
    }

    // ページネーション生成
    $dataPath = $isMatrix ? '../' . FilePaths::DATA_DIR : FilePaths::DATA_DIR;
    $page_count = 0;
    if (file_exists($dataPath . "index.json")) {
        $page_count = count(json_decode(file_get_contents($dataPath . "index.json"), true));
    }

    $page_switch_html = generatePagination($page, $view_c, $page_count, $queryParams, $lang);

    // 結果を表示
    if ($isMatrix) {
        echo $page_switch_html . "\n<hr />" . "<div class=\"matrix\">\n" . $video_contents_html . "\n</div>\n<br />\n<div style=\"clear:both;\"><hr /></div>\n" . $page_switch_html;
    } else {
        echo $page_switch_html . "\n<hr />" . $video_contents_html . "\n<br />\n<div style=\"clear:both;\"><hr /></div>\n" . $page_switch_html;
    }
}