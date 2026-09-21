<?php
// action/favorites.php

// ログインチェック
if (!Auth::isLoggedIn()) {
    header('Location: ?do=login');
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
$langQuery = $useLang !== 'ja' ? '&lang=' . $useLang : '';
?>

<div class="favorites-container">
    <div class="favorites-header">
        <h1><?php echo $lang['favorites']; ?></h1>
        <p>
            <?php echo sprintf($lang['favorites_count'], count($favorites)); ?>
            | <a href="?do=account<?php echo $langQuery; ?>"><?php echo $lang['mypage']; ?></a>
            | <a href="?compare<?php echo $langQuery; ?>"><?php echo $lang['compare']; ?></a>
        </p>
        <?php if (!empty($favorites)): ?>
            <p><?php echo $lang['favorites_desc']; ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($favorites)): ?>
        <div class="empty-message">
            <h3><?php echo $lang['no_favorites']; ?></h3>
            <p><?php echo $lang['no_favorites_desc']; ?></p>
        </div>
    <?php else: ?>
        <div class="favorites-grid">
            <?php foreach ($favorites as $favorite): ?>
                <div class="favorite-item">
                    <?php
                    // 古いお気に入りはサムネイル未保存のことがある。YouTubeならIDから導ける
                    $thumbnail = $favorite['thumbnail'] ?? '';
                    if (empty($thumbnail) && preg_match('/^[A-Za-z0-9_-]{11}$/', $favorite['video_id'])) $thumbnail = 'https://i.ytimg.com/vi/' . $favorite['video_id'] . '/mqdefault.jpg';
                    ?>
                    <?php if (!empty($thumbnail)): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" 
                             class="favorite-thumbnail"
                             loading="lazy"
                             title="<?php echo htmlspecialchars($lang['favorites_desc']); ?>"
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
                        <?php echo $lang['added_date']; ?>: <?php echo htmlspecialchars($favorite['added_at']); ?>
                    </div>
                    
                    <div class="favorite-actions">
                        <a href="./?title=1&q=<?php echo urlencode($favorite['title']); ?>"><?php echo $lang['search_view']; ?></a>
                        <form method="POST">
                            <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($favorite['video_id']); ?>">
                            <input type="submit" name="remove_favorite" value="<?php echo $lang['remove']; ?>" onclick="return confirm('<?php echo htmlspecialchars(addslashes($lang['confirm_remove']), ENT_QUOTES); ?>')">
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
</script>
