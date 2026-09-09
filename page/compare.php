<?php
// page/compare.php
// 複数のYouTube動画を、動画ごとに開始秒数を指定して同時再生する
?>
<div id="compare-page">
    <h2><?php echo $lang['compare']; ?></h2>
    <p class="compare-help">YouTubeのURL(または動画ID)と開始秒数を指定して「追加」。準備ができたら「同時再生」で一斉に再生します。</p>

    <div class="compare-add-form">
        <input type="text" id="compare-url" placeholder="YouTubeのURLまたは動画ID">
        <input type="number" id="compare-start" placeholder="開始秒数" value="0" min="0">
        <button type="button" onclick="CompareVideos.add()">追加</button>
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

        function add() {
            var urlInput = document.getElementById('compare-url');
            var startInput = document.getElementById('compare-start');

            var videoId = extractVideoId(urlInput.value);
            if (videoId === null) {
                alert('YouTubeのURLまたは動画IDを入力してください');
                return;
            }

            var startSeconds = Math.max(0, parseInt(startInput.value, 10) || 0);
            var slotId = 'compare-slot-' + (nextSlotId++);

            var slot = document.createElement('div');
            slot.className = 'compare-slot';
            slot.innerHTML =
                '<div class="compare-slot-player" id="' + slotId + '"></div>' +
                '<div class="compare-slot-meta">開始 ' + startSeconds + '秒<button type="button" class="compare-remove" data-slot="' + slotId + '">削除</button></div>';
            document.getElementById('compare-grid').appendChild(slot);
            slot.querySelector('.compare-remove').addEventListener('click', function () {
                remove(slotId);
            });

            var entry = { slotId: slotId, videoId: videoId, startSeconds: startSeconds, player: null };
            entries.push(entry);

            if (apiReady) createPlayer(entry);

            urlInput.value = '';
            startInput.value = '0';
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

        return { add: add, playAll: playAll, pauseAll: pauseAll, clearAll: clearAll };
    })();
</script>
