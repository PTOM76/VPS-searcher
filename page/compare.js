/**
 * 動画比較 (page/compare.php)
 * 複数のYouTube動画を、動画ごとの開始位置を保ったまま並べて同時再生する。
 * ボ対の動画は左右で2本分が並んでいるものが多いので、半分だけ切り取って並べられるようにする。
 */
var CompareVideos = (function () {
    var TEXT = window.COMPARE_TEXT;
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

    function grid() {
        return document.getElementById('compare-grid');
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
        grid().style.setProperty('--compare-w', width + 'px');
        entries.forEach(function (e) {
            if (e.player && typeof e.player.setSize === 'function') e.player.setSize(width, width * 9 / 16);
        });
    }

    /** 隙間なく並べると、切り取った半分同士が1本の動画のように見えて比べやすい */
    function setJoined(joined) {
        grid().classList.toggle('is-joined', joined);
    }

    /** @param {string} crop full | left | right */
    function setCrop(entry, crop) {
        entry.slot.classList.remove('crop-full', 'crop-left', 'crop-right');
        entry.slot.classList.add('crop-' + crop);
    }

    function applyMute(entry) {
        if (!entry.player || typeof entry.player.mute !== 'function') return;
        if (entry.muted) return entry.player.mute();
        entry.player.unMute();
    }

    function buildOption(value, label) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }

    /**
     * 1本分の枠を作る。文字列はDOM APIで入れる (題名等をinnerHTMLに混ぜない)
     * @returns {Object} 枠と操作部品
     */
    function buildSlot(slotId) {
        var slot = document.createElement('div');
        slot.className = 'compare-slot crop-full';
        slot.innerHTML =
            '<div class="compare-view"><div id="' + slotId + '"></div></div>' +
            '<div class="favorite-title"><span class="compare-no"></span>. <span class="compare-title"></span></div>' +
            '<div class="favorite-actions">' +
            '<label><span class="compare-label-crop"></span> <select class="compare-crop"></select></label> ' +
            '<label><input type="checkbox" class="compare-mute"> <span class="compare-label-mute"></span></label><br>' +
            '<label><span class="compare-label-start"></span> <input type="number" class="compare-offset" min="0" step="0.1" value="0" style="width:5em"></label> ' +
            '<button type="button" class="compare-here"></button><br>' +
            '<button type="button" class="compare-left"></button> <button type="button" class="compare-right"></button> ' +
            '<button type="button" class="compare-remove"></button></div>';

        var q = function (sel) { return slot.querySelector(sel); };
        q('.compare-title').textContent = TEXT.loading;
        q('.compare-label-crop').textContent = TEXT.crop;
        q('.compare-label-mute').textContent = TEXT.mute;
        q('.compare-label-start').textContent = TEXT.start_seconds;
        q('.compare-here').textContent = TEXT.current_pos;
        q('.compare-here').title = TEXT.current_pos_title;
        q('.compare-left').textContent = TEXT.move_left;
        q('.compare-right').textContent = TEXT.move_right;
        q('.compare-remove').textContent = TEXT.remove;
        q('.compare-crop').append(buildOption('full', TEXT.crop_full), buildOption('left', TEXT.crop_left), buildOption('right', TEXT.crop_right));
        return { slot: slot, q: q };
    }

    /** 枠の操作部品を entry に結び付ける */
    function bindSlot(entry, q) {
        var offset = q('.compare-offset');
        offset.addEventListener('input', function () {
            entry.startSeconds = Math.max(0, parseFloat(offset.value) || 0);
        });

        // 頭出しを秒数で打つのは手間なので、再生位置をそのまま開始位置に写せるようにする
        q('.compare-here').addEventListener('click', function () {
            if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;
            // 再生前のプレイヤーは値を返さないことがある。NaN を入れてしまわないよう確かめる
            var current = entry.player.getCurrentTime();
            if (typeof current !== 'number' || !isFinite(current)) return;
            entry.startSeconds = Math.max(0, Math.round(current * 10) / 10);
            offset.value = entry.startSeconds;
        });

        q('.compare-crop').addEventListener('change', function () { setCrop(entry, this.value); });
        q('.compare-mute').addEventListener('change', function () {
            entry.muted = this.checked;
            applyMute(entry);
        });
        q('.compare-left').addEventListener('click', function () { move(entry, -1); });
        q('.compare-right').addEventListener('click', function () { move(entry, 1); });
        q('.compare-remove').addEventListener('click', function () { remove(entry); });
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
        var built = buildSlot(slotId);
        var entry = { slotId: slotId, videoId: videoId, startSeconds: 0, muted: false, player: null, slot: built.slot };

        grid().appendChild(built.slot);
        entries.push(entry);
        bindSlot(entry, built.q);
        loadTitle(videoId, built.q('.compare-title'));
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

    /**
     * 並び順を入れ替える。iframe は DOM 上で動かすと読み込み直されてしまうので、
     * 要素は動かさずに flex の order で見た目の順番だけを変える
     * @param {number} direction -1: 前へ, 1: 後ろへ
     */
    function move(entry, direction) {
        var index = entries.indexOf(entry);
        var target = index + direction;
        if (target < 0 || target >= entries.length) return;

        entries[index] = entries[target];
        entries[target] = entry;
        renumber();
    }

    function remove(entry) {
        entries = entries.filter(function (e) { return e !== entry; });
        if (entry.player) entry.player.destroy();
        entry.slot.remove();
        renumber();
        refreshEmptyState();
    }

    function renumber() {
        entries.forEach(function (e, i) {
            e.slot.style.order = i;
            e.slot.querySelector('.compare-no').textContent = i + 1;
        });
    }

    function playAll() {
        entries.forEach(function (e) {
            if (!e.player || typeof e.player.seekTo !== 'function') return;
            // 読み込み直後はプレイヤー側の音量状態が既定に戻っていることがあるので、再生のたびに合わせ直す
            applyMute(e);
            e.player.seekTo(e.startSeconds, true);
            e.player.playVideo();
        });
    }

    function pauseAll() {
        entries.forEach(function (e) {
            if (e.player && typeof e.player.pauseVideo === 'function') e.player.pauseVideo();
        });
    }

    function clearAll() {
        entries.slice().forEach(remove);
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
        setJoined: setJoined,
    };
})();
