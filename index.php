<?php
require_once "secret.ini.php";
require_once "config.ini.php";
require_once "lang.ini.php";
require_once './lib/auth.php';
require_once './lib/common.php';
require_once './lib/xml_routing.php';

// アナリティクス処理
handleAnalytics();

// プレイリスト管理API処理
handlePlaylistAPI();

// ファイル更新処理
handleFileUpdates();

// 言語設定
if (!isset($useLang))
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang];
Auth::setLanguage($lang);

// ユーザー情報を取得
$currentUser = Auth::getCurrentUser();

global $notice;
$notice = '';

// POST処理
handlePostRequests($lang);

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
