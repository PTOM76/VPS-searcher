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
    <div class="compare-head">
        <h2><?php echo $lang['compare']; ?></h2>
        <p class="compare-help">動画を並べて、それぞれの開始位置を合わせてから一斉に再生します。開始位置は追加したあとで調整できます。</p>
    </div>

    <div class="compare-toolbar">
        <div class="compare-add">
            <input type="text" id="compare-url" placeholder="YouTubeのURL または 動画ID" onkeydown="if (event.key === 'Enter') CompareVideos.add()">
            <button type="button" class="compare-btn-primary" onclick="CompareVideos.add()">追加</button>
            <button type="button" onclick="CompareVideos.openFavorites()">お気に入りから</button>
        </div>

        <div class="compare-playback">
            <label class="compare-size">
                大きさ
                <select onchange="CompareVideos.setSize(this.value)">
                    <option value="180">小</option>
                    <option value="260" selected>中</option>
                    <option value="380">大</option>
                </select>
            </label>
            <button type="button" class="compare-btn-primary" onclick="CompareVideos.playAll()">同時再生</button>
            <button type="button" onclick="CompareVideos.pauseAll()">一時停止</button>
            <button type="button" onclick="CompareVideos.clearAll()">クリア</button>
        </div>
    </div>

    <div id="compare-grid"></div>

    <div id="compare-empty" class="compare-empty">
        まだ動画がありません。URLを貼るか、お気に入りから選んで追加してください。
    </div>
</div>

<!-- お気に入りの選択。開いている間だけ被せる (常時表示すると一覧が居座って邪魔になる) -->
<div id="compare-modal" class="compare-modal" hidden>
    <div class="compare-modal-backdrop" onclick="CompareVideos.closeFavorites()"></div>
    <div class="compare-modal-body" role="dialog" aria-modal="true" aria-label="お気に入りから追加">
        <div class="compare-modal-head">
            <strong>お気に入りから追加</strong>
            <button type="button" class="compare-modal-close" onclick="CompareVideos.closeFavorites()" aria-label="閉じる">×</button>
        </div>

        <?php if (!Auth::isLoggedIn()): ?>
            <p class="compare-help">お気に入りから追加するには<a href="?do=login">ログイン</a>してください。</p>
        <?php elseif (empty($compareFavorites)): ?>
            <p class="compare-help">比較できるお気に入り(YouTube動画)がありません。</p>
        <?php else: ?>
            <div class="compare-favorites-grid">
                <?php foreach ($compareFavorites as $favorite): ?>
                    <?php
                    // サムネイル未保存のお気に入りもあるが、ここはYouTubeに絞ってあるのでIDから導ける
                    $thumbnail = !empty($favorite['thumbnail'])
                        ? $favorite['thumbnail']
                        : 'https://i.ytimg.com/vi/' . $favorite['video_id'] . '/mqdefault.jpg';
                    ?>
                    <button type="button" class="compare-favorite-item" onclick="CompareVideos.add('<?php echo htmlspecialchars($favorite['video_id'], ENT_QUOTES); ?>')">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="" loading="lazy">
                        <span class="compare-favorite-title"><?php echo htmlspecialchars($favorite['title']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://www.youtube.com/iframe_api"></script>
<script>
    // 複数のYouTube動画を、動画ごとの開始位置を保ったまま並べて同時再生する
    var CompareVideos = (function () {
        var entries = [];
        var apiReady = false;
        var nextSlotId = 0;

        function extractVideoId(input) {
            input = input.trim();
            var m = input.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([A-Za-z0-9_-]{11})/);
            if (m) return m[1];
            // URLでなければ、そのままIDとして扱う (11文字のYouTube動画ID形式)
            if (/^[A-Za-z0-9_-]{11}$/.test(input)) return input;
            return null;
        }

        function openFavorites() {
            document.getElementById('compare-modal').hidden = false;
        }

        function closeFavorites() {
            document.getElementById('compare-modal').hidden = true;
        }

        function refreshEmptyState() {
            document.getElementById('compare-empty').hidden = entries.length > 0;
        }

        // 比較したい本数が並ぶかは画面幅と1枚の大きさで決まるので、大きさを選べるようにする
        function setSize(minWidth) {
            document.getElementById('compare-grid').style.setProperty('--compare-min', minWidth + 'px');
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
            var entry = { slotId: slotId, videoId: videoId, startSeconds: 0, player: null };

            var slot = document.createElement('div');
            slot.className = 'compare-slot';
            slot.innerHTML =
                '<div class="compare-slot-player" id="' + slotId + '"></div>' +
                '<div class="compare-slot-bar">' +
                '<span class="compare-slot-index">' + (entries.length + 1) + '</span>' +
                '<label class="compare-slot-offset">開始<input type="number" min="0" step="0.1" value="0">秒</label>' +
                '<button type="button" class="compare-slot-here" title="いま表示している位置を開始位置にする">現在</button>' +
                '<button type="button" class="compare-slot-remove" aria-label="削除">×</button>' +
                '</div>';

            document.getElementById('compare-grid').appendChild(slot);
            entries.push(entry);

            var offsetInput = slot.querySelector('.compare-slot-offset input');
            offsetInput.addEventListener('input', function () {
                entry.startSeconds = Math.max(0, parseFloat(offsetInput.value) || 0);
            });

            // 頭出しを秒数で打つのは手間なので、再生位置をそのまま開始位置に写せるようにする
            slot.querySelector('.compare-slot-here').addEventListener('click', function () {
                if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;
                entry.startSeconds = Math.max(0, Math.round(entry.player.getCurrentTime() * 10) / 10);
                offsetInput.value = entry.startSeconds;
            });

            slot.querySelector('.compare-slot-remove').addEventListener('click', function () {
                remove(slotId);
            });

            if (apiReady) createPlayer(entry);

            closeFavorites();
            refreshEmptyState();
        }

        function createPlayer(entry) {
            // start は整数秒までしか受け付けないため、小数の頭出しは playAll() の seekTo で行う
            entry.player = new YT.Player(entry.slotId, {
                videoId: entry.videoId,
                playerVars: { start: Math.floor(entry.startSeconds) },
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

            renumber();
            refreshEmptyState();
        }

        function renumber() {
            document.querySelectorAll('#compare-grid .compare-slot-index').forEach(function (el, i) {
                el.textContent = i + 1;
            });
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

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeFavorites();
        });

        return {
            add: add,
            playAll: playAll,
            pauseAll: pauseAll,
            clearAll: clearAll,
            openFavorites: openFavorites,
            closeFavorites: closeFavorites,
            setSize: setSize,
        };
    })();
</script>
