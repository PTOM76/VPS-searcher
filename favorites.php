<?php
require_once './lib/auth.php';
require_once './lib/common.php';
require_once "lang.ini.php";

if (!isset($useLang))
    $useLang = "ja";

$lang = $_lang[$useLang];

// ログインチェック
if (!Auth::isLoggedIn()) {
    header('Location: login.php' . ($useLang !== "ja" ? "?lang=" . $useLang : ""));
    exit;
}

$user = Auth::getCurrentUser();
$message = '';
$messageType = '';

// お気に入りから削除
if (isset($_POST['remove_favorite'])) {
    $videoId = $_POST['video_id'] ?? '';
    if (!empty($videoId)) {
        $result = Auth::removeFromFavorites($user['id'], $videoId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// お気に入り一覧を取得
$favorites = Auth::getFavorites($user['id']);

renderHtmlHead($lang['title'], $useLang);
renderNavigation($lang, $useLang, $currentUser);
renderMobileMenu($lang, $useLang, $currentUser);
?>
    <div class="favorites-container">
        <div class="favorites-header">
            <h1>お気に入り動画</h1>
            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>" class="back-link">戻る</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
            <div class="empty-message">
                <h3>お気に入りがありません</h3>
                <p>動画をお気に入りに追加すると、ここに表示されます。</p>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite): ?>
                    <div class="favorite-item">
                        <?php if (!empty($favorite['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars($favorite['thumbnail']); ?>" alt="Thumbnail" class="favorite-thumbnail">
                        <?php endif; ?>
                        
                        <div class="favorite-title">
                            <?php echo htmlspecialchars($favorite['title']); ?>
                        </div>
                        
                        <?php if (!empty($favorite['description'])): ?>
                            <div class="favorite-description">
                                <?php echo htmlspecialchars(substr($favorite['description'], 0, 150)); ?>
                                <?php if (strlen($favorite['description']) > 150): ?>...<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="favorite-date">
                            追加日: <?php echo htmlspecialchars($favorite['added_at']); ?>
                        </div>
                        
                        <div class="favorite-actions">
                            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>?q=<?php echo urlencode($favorite['title']); ?>" class="btn btn-primary">検索で見る</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($favorite['video_id']); ?>">
                                <button type="submit" name="remove_favorite" class="btn btn-danger" onclick="return confirm('お気に入りから削除しますか？')">削除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
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
        });
    </script>
</body>
</html>
