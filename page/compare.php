<?php
// page/compare.php
// 複数のYouTube動画を、動画ごとに開始位置を合わせて同時再生する

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
        <p class="compare-help">動画を並べて、それぞれの開始位置を合わせてから一斉に再生します。位置は追加したあとで調整できます。</p>
    </div>

    <div class="compare-bar">
        <div class="compare-bar-add">
            <input type="text" id="compare-url" placeholder="YouTubeのURL または 動画ID" onkeydown="if (event.key === 'Enter') CompareVideos.add()">
            <button type="button" class="compare-btn compare-btn-primary" onclick="CompareVideos.add()">追加</button>
            <button type="button" class="compare-btn" onclick="CompareVideos.openFavorites()">お気に入り</button>
        </div>

        <div class="compare-bar-right">
            <div class="compare-seg" role="group" aria-label="表示サイズ">
                <button type="button" class="compare-seg-btn" data-size="170" onclick="CompareVideos.setSize(this)">小</button>
                <button type="button" class="compare-seg-btn is-active" data-size="240" onclick="CompareVideos.setSize(this)">中</button>
                <button type="button" class="compare-seg-btn" data-size="380" onclick="CompareVideos.setSize(this)">大</button>
            </div>

            <div class="compare-bar-play">
                <button type="button" class="compare-btn compare-btn-primary" onclick="CompareVideos.playAll()">同時再生</button>
                <button type="button" class="compare-btn" onclick="CompareVideos.pauseAll()">停止</button>
                <button type="button" class="compare-btn compare-btn-quiet" onclick="CompareVideos.clearAll()">クリア</button>
            </div>
        </div>
    </div>

    <div id="compare-grid"></div>

    <div id="compare-empty" class="compare-empty">
        <p class="compare-empty-title">まだ動画がありません</p>
        <p class="compare-empty-note">URLを貼って「追加」、または「お気に入り」から選んでください。</p>
    </div>
</div>

<!-- お気に入りの選択。開いている間だけ被せる (常時表示すると一覧が居座って邪魔になる) -->
<div id="compare-modal" class="compare-modal" hidden>
    <div class="compare-modal-backdrop" onclick="CompareVideos.closeFavorites()"></div>
    <div class="compare-modal-body" role="dialog" aria-modal="true" aria-label="お気に入りから追加">
        <div class="compare-modal-head">
            <strong>お気に入りから追加</strong>
            <button type="button" class="compare-icon-btn" onclick="CompareVideos.closeFavorites()" aria-label="閉じる">×</button>
        </div>

        <?php if (!Auth::isLoggedIn()): ?>
            <p class="compare-help">お気に入りから追加するには<a href="?do=login">ログイン</a>してください。</p>
        <?php elseif (empty($compareFavorites)): ?>
            <p class="compare-help">比較できるお気に入り(YouTube動画)がありません。</p>
        <?php else: ?>
            <div class="compare-picker">
                <?php foreach ($compareFavorites as $favorite): ?>
                    <?php
                    // サムネイル未保存のお気に入りもあるが、ここはYouTubeに絞ってあるのでIDから導ける
                    $thumbnail = !empty($favorite['thumbnail'])
                        ? $favorite['thumbnail']
                        : 'https://i.ytimg.com/vi/' . $favorite['video_id'] . '/mqdefault.jpg';
                    ?>
                    <a class="compare-picker-item" onclick="CompareVideos.add('<?php echo htmlspecialchars($favorite['video_id'], ENT_QUOTES); ?>')">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt=""  width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" loading="lazy">
                        <br />
                        <span class="compare-picker-title"><?php echo htmlspecialchars($favorite['title']); ?></span>
                    </a>
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

        // 何本並ぶかは画面幅と1枚の大きさで決まる。比較したい本数に合わせて選べるようにする
        function setSize(button) {
            document.querySelectorAll('.compare-seg-btn').forEach(function (el) {
                el.classList.toggle('is-active', el === button);
            });
            document.getElementById('compare-grid').style.setProperty('--compare-min', button.dataset.size + 'px');
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
            slot.className = 'compare-card';
            slot.innerHTML =
                '<div class="compare-card-player"><div id="' + slotId + '"></div></div>' +
                '<div class="compare-card-body">' +
                '<div class="compare-card-head">' +
                '<span class="compare-card-no"></span>' +
                '<span class="compare-card-title">読み込み中…</span>' +
                '<button type="button" class="compare-icon-btn compare-card-remove" aria-label="削除" title="削除">×</button>' +
                '</div>' +
                '<div class="compare-card-controls">' +
                '<span class="compare-field"><input type="number" min="0" step="0.1" value="0" aria-label="開始位置(秒)"><span class="compare-field-unit">秒</span></span>' +
                '<button type="button" class="compare-btn compare-btn-quiet compare-card-here" title="いま表示している位置を開始位置にする">現在位置</button>' +
                '</div>' +
                '</div>';

            document.getElementById('compare-grid').appendChild(slot);
            entries.push(entry);

            var offsetInput = slot.querySelector('.compare-field input');
            offsetInput.addEventListener('input', function () {
                entry.startSeconds = Math.max(0, parseFloat(offsetInput.value) || 0);
            });

            // 頭出しを秒数で打つのは手間なので、再生位置をそのまま開始位置に写せるようにする
            slot.querySelector('.compare-card-here').addEventListener('click', function () {
                if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;

                // 再生前のプレイヤーは値を返さないことがある。NaN を入れてしまわないよう確かめる
                var current = entry.player.getCurrentTime();
                if (typeof current !== 'number' || !isFinite(current)) return;

                entry.startSeconds = Math.max(0, Math.round(current * 10) / 10);
                offsetInput.value = entry.startSeconds;
            });

            slot.querySelector('.compare-card-remove').addEventListener('click', function () {
                remove(slotId);
            });

            loadTitle(videoId, slot.querySelector('.compare-card-title'));

            if (apiReady) createPlayer(entry);

            closeFavorites();
            renumber();
            refreshEmptyState();
        }

        /**
         * 題名を出す。プレイヤーの getVideoData() は onReady が来ないと使えず、
         * その onReady が発火しない環境があるので、oEmbed から直接取る
         * (APIキー不要)。取れなかったときは動画IDで代える。
         */
        function loadTitle(videoId, titleEl) {
            var url = 'https://www.youtube.com/oembed?url='
                + encodeURIComponent('https://www.youtube.com/watch?v=' + videoId) + '&format=json';

            fetch(url)
                .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
                .then(function (data) { return data && data.title ? data.title : videoId; })
                .catch(function () { return videoId; })
                .then(function (title) {
                    titleEl.textContent = title;
                    titleEl.title = title;
                });
        }

        function createPlayer(entry) {
            // start は整数秒までしか受け付けないため、小数の頭出しは playAll() の seekTo で行う
            entry.player = new YT.Player(entry.slotId, {
                videoId: entry.videoId,
                playerVars: {
                    start: Math.floor(entry.startSeconds),
                    // enablejsapi の postMessage は origin が合っていないと通らないことがある
                    origin: window.location.origin,
                },
            });
        }

        function remove(slotId) {
            entries = entries.filter(function (e) {
                if (e.slotId !== slotId) return true;
                if (e.player) e.player.destroy();
                return false;
            });

            var el = document.getElementById(slotId);
            if (el) el.closest('.compare-card').remove();

            renumber();
            refreshEmptyState();
        }

        function renumber() {
            document.querySelectorAll('#compare-grid .compare-card-no').forEach(function (el, i) {
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
