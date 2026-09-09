<?php
// page/compare.php
// 複数のYouTube動画を、動画ごとに開始秒数を指定して同時再生する

// お気に入りはYouTube以外(ニコニコ動画等)も混在しうるため、
// YouTubeの動画ID形式 (英数字・-・_ の11文字) のものだけを候補にする
$compareFavorites = [];
if (Auth::isLoggedIn()) {
    foreach (Auth::getFavorites($currentUser['id']) as $favorite) {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $favorite['video_id'])) {
            $compareFavorites[] = $favorite;
        }
    }
}
?>
<div id="compare-page">
    <h2><?php echo $lang['compare']; ?></h2>
    <p class="compare-help">動画を追加してください。開始秒数はあとから各動画のカードで調整できます。準備ができたら「同時再生」で一斉に再生します。</p>

    <div class="compare-source-tabs">
        <button type="button" class="compare-tab active" data-tab="url" onclick="CompareVideos.switchTab('url')">URLで追加</button>
        <button type="button" class="compare-tab" data-tab="favorites" onclick="CompareVideos.switchTab('favorites')">お気に入りから追加</button>
    </div>

    <div id="compare-tab-url" class="compare-tab-panel">
        <div class="compare-add-form">
            <input type="text" id="compare-url" placeholder="YouTubeのURLまたは動画ID">
            <button type="button" onclick="CompareVideos.add()">追加</button>
        </div>
    </div>

    <div id="compare-tab-favorites" class="compare-tab-panel" hidden>
        <?php if (!Auth::isLoggedIn()): ?>
            <p class="compare-help">お気に入りから追加するには<a href="?do=login">ログイン</a>してください。</p>
        <?php elseif (empty($compareFavorites)): ?>
            <p class="compare-help">比較できるお気に入り(YouTube動画)がありません。</p>
        <?php else: ?>
            <div class="compare-favorites-grid">
                <?php foreach ($compareFavorites as $favorite): ?>
                    <button type="button" class="compare-favorite-item" onclick="CompareVideos.add('<?php echo htmlspecialchars($favorite['video_id'], ENT_QUOTES); ?>')">
                        <?php if (!empty($favorite['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars($favorite['thumbnail']); ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <span class="compare-favorite-title"><?php echo htmlspecialchars($favorite['title']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="compare-controls">
        <button type="button" onclick="CompareVideos.playAll()">同時再生</button>
        <button type="button" onclick="CompareVideos.pauseAll()">全て一時停止</button>
        <button type="button" onclick="CompareVideos.clearAll()">全てクリア</button>
    </div>

    <div id="compare-grid"></div>
</div>

<script src="https://www.youtube.com/iframe_api"></script>
<script>
    // 複数のYouTube動画を、動画ごとの開始秒数を保ったまま並べて同時再生する
    var CompareVideos = (function () {
        var entries = [];
        var apiReady = false;
        var nextSlotId = 0;

        function extractVideoId(input) {
            input = input.trim();
            var patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([A-Za-z0-9_-]{11})/,
            ];
            for (var i = 0; i < patterns.length; i++) {
                var m = input.match(patterns[i]);
                if (m) return m[1];
            }
            // URLでなければ、そのままIDとして扱う (11文字のYouTube動画ID形式)
            if (/^[A-Za-z0-9_-]{11}$/.test(input)) return input;
            return null;
        }

        function switchTab(tab) {
            document.querySelectorAll('.compare-tab').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            document.getElementById('compare-tab-url').hidden = tab !== 'url';
            document.getElementById('compare-tab-favorites').hidden = tab !== 'favorites';
        }

        // お気に入りのボタンは videoId を直接渡してくる。URL欄からの追加は引数無しで呼ばれる
        function add(favoriteVideoId) {
            var videoId = favoriteVideoId;

            if (videoId === undefined) {
                var urlInput = document.getElementById('compare-url');
                videoId = extractVideoId(urlInput.value);
                if (videoId === null) {
                    alert('YouTubeのURLまたは動画IDを入力してください');
                    return;
                }
                urlInput.value = '';
            }

            var slotId = 'compare-slot-' + (nextSlotId++);

            var slot = document.createElement('div');
            slot.className = 'compare-slot';
            slot.innerHTML =
                '<div class="compare-slot-player" id="' + slotId + '"></div>' +
                '<div class="compare-slot-meta">' +
                '<label>開始秒数 <input type="number" class="compare-slot-start" min="0" value="0"></label>' +
                '<button type="button" class="compare-remove">削除</button>' +
                '</div>';
            document.getElementById('compare-grid').appendChild(slot);

            var entry = { slotId: slotId, videoId: videoId, startSeconds: 0, player: null };
            entries.push(entry);

            slot.querySelector('.compare-remove').addEventListener('click', function () {
                remove(slotId);
            });
            slot.querySelector('.compare-slot-start').addEventListener('input', function (event) {
                entry.startSeconds = Math.max(0, parseInt(event.target.value, 10) || 0);
            });

            if (apiReady) createPlayer(entry);
        }

        function createPlayer(entry) {
            entry.player = new YT.Player(entry.slotId, {
                videoId: entry.videoId,
                playerVars: { start: entry.startSeconds },
            });
        }

        function remove(slotId) {
            entries = entries.filter(function (e) {
                if (e.slotId !== slotId) return true;
                if (e.player) e.player.destroy();
                return false;
            });
            var el = document.getElementById(slotId);
            if (el) el.closest('.compare-slot').remove();
        }

        function playAll() {
            entries.forEach(function (e) {
                if (!e.player) return;
                e.player.seekTo(e.startSeconds, true);
                e.player.playVideo();
            });
        }

        function pauseAll() {
            entries.forEach(function (e) {
                if (e.player) e.player.pauseVideo();
            });
        }

        function clearAll() {
            entries.slice().forEach(function (e) { remove(e.slotId); });
        }

        window.onYouTubeIframeAPIReady = function () {
            apiReady = true;
            entries.forEach(function (e) {
                if (!e.player) createPlayer(e);
            });
        };

        return { add: add, playAll: playAll, pauseAll: pauseAll, clearAll: clearAll, switchTab: switchTab };
    })();
</script>
