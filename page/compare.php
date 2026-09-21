<?php
// page/compare.php
// 複数のYouTube動画を、動画ごとに開始位置を合わせて同時再生する

// お気に入りはYouTube以外(ニコニコ動画等)も混在しうるため、
// YouTubeの動画ID形式 (英数字・-・_ の11文字) のものだけを候補にする
$compareFavorites = [];
if (Auth::isLoggedIn()) {
    foreach (Auth::getFavorites($currentUser['id']) as $favorite) {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $favorite['video_id'])) $compareFavorites[] = $favorite;
    }
}

$compareText = [
    'invalid_url' => $lang['compare_invalid_url'],
    'loading' => $lang['compare_loading'],
    'remove' => $lang['remove'],
    'start_seconds' => $lang['compare_start_seconds'],
    'current_pos' => $lang['compare_current_pos'],
    'current_pos_title' => $lang['compare_current_pos_title'],
];
?>
<div class="compare-container">
    <h2><?php echo $lang['compare']; ?></h2>
    <p><?php echo $lang['compare_help']; ?></p>

    <div class="compare-bar">
        <input type="text" id="compare-url" size="36" placeholder="<?php echo htmlspecialchars($lang['compare_url_placeholder']); ?>" onkeydown="if (event.key === 'Enter') CompareVideos.add()">
        <button type="button" onclick="CompareVideos.add()"><?php echo $lang['compare_add']; ?></button>
        <button type="button" onclick="CompareVideos.openFavorites()"><?php echo $lang['compare_from_favorites']; ?></button>
        <span class="flex-break"></span>
        <label for="compare-size"><?php echo $lang['compare_size']; ?>:</label>
        <select id="compare-size" onchange="CompareVideos.setSize(parseInt(this.value, 10))">
            <option value="320"><?php echo $lang['compare_size_s']; ?></option>
            <option value="480"><?php echo $lang['compare_size_m']; ?></option>
            <option value="640"><?php echo $lang['compare_size_l']; ?></option>
        </select>
        <button type="button" onclick="CompareVideos.playAll()"><?php echo $lang['compare_play_all']; ?></button>
        <button type="button" onclick="CompareVideos.pauseAll()"><?php echo $lang['compare_pause_all']; ?></button>
        <button type="button" onclick="CompareVideos.clearAll()"><?php echo $lang['compare_clear']; ?></button>
    </div>

    <div id="compare-grid" class="favorites-grid"></div>

    <p id="compare-empty" class="empty-message"><?php echo $lang['compare_empty']; ?></p>
</div>

<!-- お気に入りの選択。開いている間だけ被せる (常時表示すると一覧が居座って邪魔になる) -->
<div id="compare-modal" class="compare-modal" hidden>
    <div class="compare-modal-backdrop" onclick="CompareVideos.closeFavorites()"></div>
    <div class="compare-modal-body" role="dialog" aria-modal="true" aria-label="<?php echo htmlspecialchars($lang['compare_from_favorites']); ?>">
        <div class="compare-modal-head">
            <strong><?php echo $lang['compare_from_favorites']; ?></strong>
            <button type="button" onclick="CompareVideos.closeFavorites()"><?php echo $lang['close']; ?></button>
        </div>

        <?php if (!Auth::isLoggedIn()): ?>
            <p><?php echo $lang['compare_login_required']; ?> <a href="?do=login"><?php echo $lang['login']; ?></a></p>
        <?php elseif (empty($compareFavorites)): ?>
            <p><?php echo $lang['compare_no_favorites']; ?></p>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($compareFavorites as $favorite): ?>
                    <?php
                    // サムネイル未保存のお気に入りもあるが、ここはYouTubeに絞ってあるのでIDから導ける
                    $thumbnail = !empty($favorite['thumbnail'])
                        ? $favorite['thumbnail']
                        : 'https://i.ytimg.com/vi/' . $favorite['video_id'] . '/mqdefault.jpg';
                    ?>
                    <a class="favorite-item compare-picker-item" href="javascript:void(0)" onclick="CompareVideos.add('<?php echo htmlspecialchars($favorite['video_id'], ENT_QUOTES); ?>')">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="" width="320" height="180" style="width:320px;height:180px;object-fit:cover;" loading="lazy">
                        <div class="favorite-title"><?php echo htmlspecialchars($favorite['title']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://www.youtube.com/iframe_api"></script>
<script>
    /** 複数のYouTube動画を、動画ごとの開始位置を保ったまま並べて同時再生する */
    var CompareVideos = (function () {
        var TEXT = <?php echo json_encode($compareText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
        var entries = [];
        var apiReady = false;
        var nextSlotId = 0;
        var currentWidth = 320;

        /** @param {string} input URL または動画ID */
        function extractVideoId(input) {
            input = input.trim();
            var m = input.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([A-Za-z0-9_-]{11})/);
            if (m) return m[1];
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

        /** 何本並ぶかは画面幅と1枚の大きさで決まる。比較したい本数に合わせて選べるようにする */
        function setSize(width) {
            currentWidth = width;
            entries.forEach(function (e) {
                if (e.player && typeof e.player.setSize === 'function') e.player.setSize(width, width * 9 / 16);
            });
            document.querySelectorAll('#compare-grid .favorite-item').forEach(function (el) {
                el.style.width = width + 'px';
            });
        }

        /**
         * 1本分の枠を作る。文字列はDOM APIで入れる (題名等をinnerHTMLに混ぜない)
         * @returns {{slot: HTMLElement, title: HTMLElement, offset: HTMLInputElement, here: HTMLButtonElement, remove: HTMLButtonElement}}
         */
        function buildSlot(slotId) {
            var slot = document.createElement('div');
            slot.className = 'favorite-item';
            slot.style.width = currentWidth + 'px';
            slot.innerHTML =
                '<div id="' + slotId + '"></div>' +
                '<div class="favorite-title"><span class="compare-no"></span>. <span class="compare-title"></span></div>' +
                '<div class="favorite-actions"><label><span class="compare-label"></span> ' +
                '<input type="number" min="0" step="0.1" value="0" style="width:5em"></label> ' +
                '<button type="button" class="compare-here"></button> <button type="button" class="compare-remove"></button></div>';

            var parts = {
                slot: slot,
                title: slot.querySelector('.compare-title'),
                offset: slot.querySelector('input'),
                here: slot.querySelector('.compare-here'),
                remove: slot.querySelector('.compare-remove'),
            };
            parts.title.textContent = TEXT.loading;
            slot.querySelector('.compare-label').textContent = TEXT.start_seconds;
            parts.here.textContent = TEXT.current_pos;
            parts.here.title = TEXT.current_pos_title;
            parts.remove.textContent = TEXT.remove;
            return parts;
        }

        /** お気に入りのボタンは videoId を直接渡してくる。URL欄からの追加は引数無しで呼ばれる */
        function add(favoriteVideoId) {
            var videoId = favoriteVideoId;
            if (videoId === undefined) {
                var urlInput = document.getElementById('compare-url');
                videoId = extractVideoId(urlInput.value);
                if (videoId === null) return alert(TEXT.invalid_url);
                urlInput.value = '';
            }

            var slotId = 'compare-slot-' + (nextSlotId++);
            var entry = { slotId: slotId, videoId: videoId, startSeconds: 0, player: null };
            var parts = buildSlot(slotId);

            document.getElementById('compare-grid').appendChild(parts.slot);
            entries.push(entry);

            parts.offset.addEventListener('input', function () {
                entry.startSeconds = Math.max(0, parseFloat(parts.offset.value) || 0);
            });

            // 頭出しを秒数で打つのは手間なので、再生位置をそのまま開始位置に写せるようにする
            parts.here.addEventListener('click', function () {
                if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;
                // 再生前のプレイヤーは値を返さないことがある。NaN を入れてしまわないよう確かめる
                var current = entry.player.getCurrentTime();
                if (typeof current !== 'number' || !isFinite(current)) return;
                entry.startSeconds = Math.max(0, Math.round(current * 10) / 10);
                parts.offset.value = entry.startSeconds;
            });

            parts.remove.addEventListener('click', function () { remove(slotId); });

            loadTitle(videoId, parts.title);
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
            entry.player = new YT.Player(entry.slotId, {
                width: currentWidth,
                height: currentWidth * 9 / 16,
                videoId: entry.videoId,
                playerVars: {
                    start: Math.floor(entry.startSeconds),
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

            // destroy() でプレイヤーは元の div に戻るので、同じIDで枠ごと引ける
            var el = document.getElementById(slotId);
            if (el) el.closest('.favorite-item').remove();

            renumber();
            refreshEmptyState();
        }

        function renumber() {
            document.querySelectorAll('#compare-grid .compare-no').forEach(function (el, i) {
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
