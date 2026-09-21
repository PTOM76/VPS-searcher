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
        changed();
    }

    /** 隙間なく並べると、切り取った半分同士が1本の動画のように見えて比べやすい */
    function setJoined(joined) {
        grid().classList.toggle('is-joined', joined);
        changed();
    }

    /** @param {string} crop full | left | right */
    function setCrop(entry, crop) {
        entry.slot.classList.remove('crop-full', 'crop-left', 'crop-right');
        entry.slot.classList.add('crop-' + crop);
        entry.crop = crop;
        changed();
    }

    /** 基準位置が決まっている動画は、開始位置を「基準位置 + 基準からの開始位置」にする */
    function applyStart(entry) {
        if (entry.manualStart) return;
        var start = CompareSync.startOf(entry);
        if (start === null) return;
        entry.startSeconds = start;
        entry.offsetInput.value = start;
    }

    /** 基準位置を調整した時は、合っているか耳で確かめられるようにその位置へ飛ばす */
    function applyBase(entry) {
        entry.manualStart = false;
        applyStart(entry);
        changed();
        if (entry.player && typeof entry.player.seekTo === 'function') entry.player.seekTo(Math.max(0, entry.startSeconds), true);
    }

    /** @param {number} value 基準からの開始位置 (秒)。マイナスなら基準より前から */
    function setRelative(value) {
        CompareSync.setRelative(value);
        entries.forEach(applyStart);
        changed();
    }

    /** 今の状態を URL に写す。URL がそのまま共有・保存の単位になる */
    function changed() {
        CompareUrl.write({
            videos: entries.map(function (e) {
                return { id: e.videoId, crop: e.crop, muted: e.muted, start: e.manualStart ? e.startSeconds : null };
            }),
            rel: CompareSync.getRelative(),
            size: currentWidth,
            join: grid().classList.contains('is-joined'),
        });
    }

    function applyMute(entry) {
        if (!entry.player || typeof entry.player.mute !== 'function') return;
        if (entry.muted) return entry.player.mute();
        entry.player.unMute();
    }

    /** 枠の操作部品を entry に結び付ける */
    function bindSlot(entry, q) {
        var offset = q('.compare-offset');
        offset.addEventListener('input', function () {
            entry.startSeconds = parseFloat(offset.value) || 0;
            entry.manualStart = true;
            changed();
        });

        // 頭出しを秒数で打つのは手間なので、再生位置をそのまま開始位置に写せるようにする
        q('.compare-here').addEventListener('click', function () {
            if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;
            // 再生前のプレイヤーは値を返さないことがある。NaN を入れてしまわないよう確かめる
            var current = entry.player.getCurrentTime();
            if (typeof current !== 'number' || !isFinite(current)) return;
            entry.startSeconds = Math.round(current * 10) / 10;
            entry.manualStart = true;
            offset.value = entry.startSeconds;
            changed();
        });

        q('.compare-crop').addEventListener('change', function () { setCrop(entry, this.value); });
        q('.compare-mute').addEventListener('change', function () {
            entry.muted = this.checked;
            applyMute(entry);
            changed();
        });
        q('.compare-left').addEventListener('click', function () { move(entry, -1); });
        q('.compare-right').addEventListener('click', function () { move(entry, 1); });
        q('.compare-remove').addEventListener('click', function () { remove(entry); });
    }

    /**
     * お気に入りのボタンは videoId を直接渡してくる。URL欄からの追加は引数無しで呼ばれる
     * @param {string} [favoriteVideoId]
     * @param {{crop: string, muted: boolean, start: number|null}} [options] URL から復元する時の状態
     */
    function add(favoriteVideoId, options) {
        var videoId = favoriteVideoId;
        if (videoId === undefined) {
            var urlInput = document.getElementById('compare-url');
            videoId = extractVideoId(urlInput.value);
            if (videoId === null) return alert(TEXT.invalid_url);
            urlInput.value = '';
        }

        var slotId = 'compare-slot-' + (nextSlotId++);
        var built = CompareSlot.build(slotId);
        var entry = {
            slotId: slotId, videoId: videoId, startSeconds: 0, muted: false, crop: 'full', manualStart: false, player: null,
            slot: built.slot, offsetInput: built.q('.compare-offset'), base: CompareSync.baseOf(videoId),
        };

        grid().appendChild(built.slot);
        entries.push(entry);
        bindSlot(entry, built.q);
        built.slot.appendChild(CompareSync.buildRow(entry, applyBase));
        restoreOptions(entry, built.q, options);
        applyStart(entry);
        loadTitle(videoId, built.q('.compare-title'));
        if (apiReady) createPlayer(entry);

        closeFavorites();
        renumber();
        refreshEmptyState();
        changed();
    }

    function restoreOptions(entry, q, options) {
        if (!options) return;
        q('.compare-crop').value = options.crop;
        setCrop(entry, options.crop);
        entry.muted = options.muted;
        q('.compare-mute').checked = options.muted;
        if (options.start === null) return;
        entry.manualStart = true;
        entry.startSeconds = options.start;
        entry.offsetInput.value = options.start;
    }

    /** URL パラメータの比較を並べ直す。ページ読み込み時に1回だけ呼ぶ */
    function loadFromUrl() {
        var state = CompareUrl.read();
        document.getElementById('compare-size').value = state.size;
        document.getElementById('compare-join').checked = state.join;
        document.getElementById('compare-relative').value = state.rel;
        CompareSync.setRelative(state.rel);
        setSize(state.size);
        setJoined(state.join);
        state.videos.forEach(function (video) { add(video.id, video); });
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
                start: Math.max(0, Math.floor(entry.startSeconds)),
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
        changed();
    }

    function remove(entry) {
        entries = entries.filter(function (e) { return e !== entry; });
        if (entry.player) entry.player.destroy();
        entry.slot.remove();
        renumber();
        refreshEmptyState();
        changed();
    }

    function renumber() {
        entries.forEach(function (e, i) {
            e.slot.style.order = i;
            e.slot.querySelector('.compare-no').textContent = i + 1;
        });
    }

    function playAll() {
        ComparePlayer.playAll(entries);
    }

    function pauseAll() {
        ComparePlayer.pauseAll(entries);
    }

    function togglePause() {
        ComparePlayer.toggle(entries);
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
        togglePause: togglePause,
        clearAll: clearAll,
        openFavorites: openFavorites,
        closeFavorites: closeFavorites,
        setSize: setSize,
        setJoined: setJoined,
        setRelative: setRelative,
        loadFromUrl: loadFromUrl,
    };
})();
