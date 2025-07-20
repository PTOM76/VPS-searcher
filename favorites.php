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
        $message = $result['message'] ?? '';
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
            <h1><?php echo $lang['favorites'] ?? 'お気に入り動画'; ?></h1>
            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>" class="plain"><?php echo $lang['back'] ?? '戻る'; ?></a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
            <div class="empty-message">
                <h3><?php echo $lang['no_favorites'] ?? 'お気に入りがありません'; ?></h3>
                <p><?php echo $lang['no_favorites_desc'] ?? '動画をお気に入りに追加すると、ここに表示されます。'; ?></p>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite): ?>
                    <div class="favorite-item">
                        <?php if (!empty($favorite['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars($favorite['thumbnail']); ?>" 
                                 alt="Thumbnail" 
                                 width="320"
                                 height="180"
                                 style="width:320px;height:180px;object-fit:cover;"
                                 data-video-id="<?php echo htmlspecialchars($favorite['video_id']); ?>"
                                 onclick="playVideoInPlace(this, '<?php echo htmlspecialchars($favorite['video_id']); ?>')">
                        <?php endif; ?>
                        
                        <div class="favorite-title">
                            <?php echo htmlspecialchars($favorite['title']); ?>
                        </div>
                        
                        <?php if (!empty($favorite['description'])): ?>
                            <div class="favorite-description">
                                <?php echo htmlspecialchars(substr($favorite['description'], 0, 100)); ?>
                                <?php if (strlen($favorite['description']) > 100): ?>...<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="favorite-date">
                            <?php echo $lang['added_date'] ?? '追加日'; ?>: <?php echo htmlspecialchars($favorite['added_at']); ?>
                        </div>
                        
                        <div class="favorite-actions">
                            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>?title=1&q=<?php echo urlencode($favorite['title']); ?>"><?php echo $lang['search_view'] ?? '検索で見る'; ?></a>
                            <form method="POST">
                                <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($favorite['video_id']); ?>">
                                <input type="submit" name="remove_favorite" value="<?php echo $lang['remove'] ?? '削除'; ?>" onclick="return confirm('<?php echo $lang['confirm_remove'] ?? 'お気に入りから削除しますか？'; ?>')">
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function playVideoInPlace(imgElement, videoId) {
            // YouTube形式のIDかチェック
            if (videoId.match(/^[a-zA-Z0-9_-]{11}$/)) {
                // YouTube埋め込みiframeを作成
                const iframe = document.createElement('iframe');
                iframe.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1';
                iframe.width = 320;
                iframe.height = 180;
                iframe.frameBorder = '0';
                iframe.allowFullscreen = true;
                iframe.allow = 'autoplay; encrypted-media';
                
                // 画像をiframeに置き換え
                imgElement.parentNode.replaceChild(iframe, imgElement);
                
            } else if (videoId.startsWith('sm') || videoId.startsWith('so') || videoId.startsWith('nm')) {
                // ニコニコ動画埋め込み
                const iframe = document.createElement('iframe');
                iframe.src = 'https://embed.nicovideo.jp/watch/' + videoId;
                iframe.width = imgElement.width || 320;
                iframe.height = (imgElement.height || 180);
                iframe.frameBorder = '0';
                iframe.allowFullscreen = true;
                iframe.className = 'favorite-';
                
                // 画像をiframeに置き換え
                imgElement.parentNode.replaceChild(iframe, imgElement);
                
            } else {
                // その他の場合はニコニコ動画として試行
                const iframe = document.createElement('iframe');
                iframe.src = 'https://embed.nicovideo.jp/watch/' + videoId;
                iframe.width = imgElement.width || 320;
                iframe.height = (imgElement.height || 180);
                iframe.frameBorder = '0';
                iframe.allowFullscreen = true;
                
                // 画像をiframeに置き換え
                imgElement.parentNode.replaceChild(iframe, imgElement);
            }
        }

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
