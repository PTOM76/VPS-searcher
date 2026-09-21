<?php
// admin/index.php
// 管理画面。ChreeID で admin@pitan76.net としてログインしている時だけ入れる

// データのパス (data/ 等) はサイト直下からの相対で書かれているので、そこに合わせる
chdir(__DIR__ . '/..');
ob_start();

require_once 'secret.ini.php';
require_once 'config.ini.php';
require_once 'lang.ini.php';
require_once 'lib/auth.php';
require_once 'lib/common.php';
require_once 'lib/admin/AdminAuth.php';
require_once 'lib/admin/AdminActions.php';
require_once 'lib/admin/AdminView.php';
require_once 'admin/lang.php';

$useLang = 'ja';
$lang = $_lang[$useLang];
Auth::setLanguage($lang);
$adminText = $_adminLang[$useLang];
$currentUser = Auth::getCurrentUser();

const ADMIN_TABS = ['reports', 'blacklist', 'data', 'users'];
$tab = in_array($_GET['tab'] ?? '', ADMIN_TABS, true) ? $_GET['tab'] : 'reports';

if (AdminAuth::isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['admin_flash'] = AdminAuth::verifyCsrf((string)($_POST['csrf'] ?? ''))
        ? (new AdminActions($adminText))->handle($_POST)
        : $adminText['csrf_failed'];
    // 再読み込みで同じ操作が二重に走らないよう、結果を持って GET に戻す
    header('Location: ./?tab=' . $tab);
    exit;
}

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

// 管理画面を検索エンジンに載せない
header('X-Robots-Tag: noindex, nofollow');
if (!AdminAuth::isAdmin()) http_response_code(403);

renderHtmlHead($adminText['title'], $useLang, true);
?>
<div class="admin-container">
    <div class="favorites-header">
        <h1><?php echo $adminText['title']; ?></h1>
        <a href="../"><?php echo $adminText['back_to_site']; ?></a>
    </div>

<?php if (!AdminAuth::isAdmin()): ?>
    <p><?php echo $adminText['forbidden']; ?><br><?php echo $adminText['forbidden_hint']; ?></p>
    <p><a href="../?do=chreeid&amp;return=admin"><?php echo $adminText['login_chreeid']; ?></a></p>
<?php else: ?>
    <p class="admin-tabs">
        <?php foreach (ADMIN_TABS as $i => $name): ?>
            <?php echo $i > 0 ? ' | ' : ''; ?>
            <?php if ($name === $tab): ?>
                <strong><?php echo $adminText['tab_' . $name]; ?></strong>
            <?php else: ?>
                <a href="./?tab=<?php echo $name; ?>"><?php echo $adminText['tab_' . $name]; ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </p>

    <?php if ($flash !== ''): ?>
        <div class="message"><?php echo AdminView::e($flash); ?></div>
    <?php endif; ?>

    <?php include __DIR__ . '/tabs/' . $tab . '.php'; ?>
<?php endif; ?>
</div>
</body>
</html>
