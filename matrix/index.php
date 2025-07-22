<?php
require_once "../config.ini.php";
require_once "../lang.ini.php";
require_once "../lib/auth.php";
require_once "../lib/common.php";

if (isset($_GET['q'])) {
  $analytics = "../data/analytics/" . date("Y-m-d") . ".txt";
  $data = '';
  if (file_exists($analytics))
    $data = file_get_contents($analytics);
  file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
}

if (!isset($useLang))
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang];
Auth::setLanguage($lang);

// ユーザー情報を取得
$currentUser = Auth::getCurrentUser();

global $notice;

if (isset($_POST['do'])) {
    $url = $_POST['url'];

    $url_type = "none";
    if (false !== strpos($url, 'list=') || str_starts_with($url, 'PL')) {
        $url_type = "playlist";
    } else if (false !== strpos($url, 'watch/sm') || str_starts_with($url, 'sm')) {
        $url_type = "nicovideo";
    }
}

// HTMLヘッダーを出力
renderHtmlHead($lang['title'], $useLang, true);

// ナビゲーションバーを出力（matrix用）
renderNavigation($lang, $useLang, $currentUser, true);

// モバイルメニューを出力（matrix用）
renderMobileMenu($lang, $useLang, $currentUser, true);
?>
    <div id="contents">
    
    <?php echo empty($notice) ? "" : '<h3>' . $notice . '</h3>'; ?>
<?php
    // 動画検索・表示のメイン処理（マトリックス表示）
    processVideoSearch($lang, $currentUser, true);
?>
<br />
<?php renderFooter($lang, $useLang, true); ?>
</div>
</body>
</html>
