/**
 * 動画比較の同時再生。
 * YouTube の iframe は読み込み・バッファの具合で動き出す時刻がずれるので、
 * 先に全部を開始位置でバッファさせて一時停止し、揃ってから一斉に再生する。再生中もずれを直し続ける。
 *
 * entry は compare.js のもの ({ player, startSeconds, muted })。
 * startSeconds がマイナスの動画は、その秒数だけ遅れて頭から再生する (動画が基準より後から始まる場合)。
 */
var ComparePlayer = (function () {
    var TEXT = window.COMPARE_TEXT;
    /** 読み込みが遅い動画を待つ上限。これを過ぎたら待たずに始める */
    var PREPARE_TIMEOUT_MS = 8000;
    /** 再生位置を見に行く間隔。見積もりの精度はこの細かさで決まる */
    var TRACK_INTERVAL_MS = 50;
    /** ずれを測る間隔 */
    var CHECK_INTERVAL_MS = 100;
    /**
     * 1回ごとの測定は見積もりの誤差 (数十 ms) を含むので、直近この回数分の中央値で判断する。
     * 1回ずつで判断すると誤差に反応して直し続け、かえってずれる
     */
    var SAMPLES = 10;
    /** 再生直後や位置を飛ばした直後はプレイヤーが揺れるので、この間は測らない */
    var SETTLE_MS = 3000;
    /** これを超えてずれ続けた時だけ位置を合わせる。見積もりの誤差より十分大きくしておく */
    var THRESHOLD_S = 0.15;

    var trackTimer = null;
    var checkTimer = null;
    var delayTimers = [];
    /** 準備中に停止・再実行されたとき、古い準備の続きを走らせないための番号 */
    var session = 0;
    var started = false;
    var playing = false;
    var autoSync = true;

    function usable(entry) {
        return entry.player && typeof entry.player.seekTo === 'function' && typeof entry.player.getPlayerState === 'function';
    }

    function isPlaying(entry) {
        return usable(entry) && entry.player.getPlayerState() === YT.PlayerState.PLAYING;
    }

    function setPlaying(value) {
        playing = value;
        var button = document.getElementById('compare-toggle');
        if (button) button.textContent = value ? TEXT.pause : TEXT.resume;
    }

    function stopTimers() {
        clearInterval(trackTimer);
        clearInterval(checkTimer);
        trackTimer = checkTimer = null;
        delayTimers.forEach(clearTimeout);
        delayTimers = [];
    }

    /**
     * getCurrentTime() は数十〜数百 ms おきにしか更新されない。
     * 値が変わった瞬間を performance.now() で覚えておき、そこからの経過時間を足して今の位置を見積もる
     */
    function track(entry) {
        var reported = entry.player.getCurrentTime();
        var now = performance.now();
        if (!entry.clock || entry.clock.reported !== reported) entry.clock = { reported: reported, at: now };
    }

    function estimate(entry) {
        var rate = typeof entry.player.getPlaybackRate === 'function' ? entry.player.getPlaybackRate() : 1;
        return entry.clock.reported + (performance.now() - entry.clock.at) / 1000 * rate;
    }

    function median(values) {
        var sorted = values.slice().sort(function (x, y) { return x - y; });
        return sorted[Math.floor(sorted.length / 2)];
    }

    /**
     * @param {Object} entry
     * @param {number} drift 多数派より進んでいる秒数 (マイナスなら遅れ)
     * @param {number} expected 本来いるべき位置
     */
    function correct(entry, drift, expected) {
        entry.drifts.push(drift);
        if (entry.drifts.length > SAMPLES) entry.drifts.shift();
        if (entry.drifts.length < SAMPLES) return;
        if (Math.abs(median(entry.drifts)) < THRESHOLD_S || expected < 0) return;

        entry.player.seekTo(expected, true);
        entry.clock = null;
        entry.drifts = [];
        entry.settleUntil = performance.now() + SETTLE_MS;
    }

    /**
     * 基準の経過時間は、再生中の動画の (見積もり位置 - 開始位置) の中央値。
     * 時計を基準にすると全部が同時に詰まった時に全部を動かしてしまうので、多数派に合わせる
     */
    function check(entries) {
        var now = performance.now();
        if (!autoSync) return;
        var targets = entries.filter(function (e) { return isPlaying(e) && e.clock && !(e.settleUntil > now); });
        if (targets.length < 2) return;

        var elapsed = targets.map(function (e) { return estimate(e) - e.startSeconds; });
        var center = median(elapsed);
        targets.forEach(function (e, i) { correct(e, elapsed[i] - center, e.startSeconds + center); });
    }

    function startWatching(entries) {
        var settleUntil = performance.now() + SETTLE_MS;
        entries.forEach(function (e) {
            e.clock = null;
            e.drifts = [];
            e.settleUntil = settleUntil;
        });
        trackTimer = setInterval(function () { entries.filter(isPlaying).forEach(track); }, TRACK_INTERVAL_MS);
        checkTimer = setInterval(function () { check(entries); }, CHECK_INTERVAL_MS);
    }

    /** ミュートで一瞬再生させてバッファを作り、開始位置で止める */
    function prepareOne(entry) {
        var player = entry.player;
        var target = Math.max(0, entry.startSeconds);
        var startedAt = Date.now();

        return new Promise(function (resolve) {
            player.mute();
            player.seekTo(target, true);
            player.playVideo();

            var poll = setInterval(function () {
                var ready = player.getPlayerState() === YT.PlayerState.PLAYING;
                if (!ready && Date.now() - startedAt < PREPARE_TIMEOUT_MS) return;
                clearInterval(poll);
                player.pauseVideo();
                player.seekTo(target, true);
                resolve();
            }, 50);
        });
    }

    function restoreMute(entry) {
        if (entry.muted) return entry.player.mute();
        entry.player.unMute();
    }

    function startAll(entries) {
        entries.forEach(function (entry) {
            restoreMute(entry);
            if (entry.startSeconds >= 0) return entry.player.playVideo();

            entry.player.seekTo(0, true);
            delayTimers.push(setTimeout(function () { entry.player.playVideo(); }, -entry.startSeconds * 1000));
        });
        startWatching(entries);
    }

    /** 開始位置から一斉に再生する @param {Array} entries compare.js の entry の配列 */
    function playAll(entries) {
        stopTimers();
        var mySession = ++session;
        var targets = entries.filter(usable);
        started = true;
        setPlaying(true);

        Promise.all(targets.map(prepareOne)).then(function () {
            if (mySession !== session) return;
            startAll(targets);
        });
    }

    function pauseAll(entries) {
        session++;
        stopTimers();
        setPlaying(false);
        entries.forEach(function (e) {
            if (usable(e)) e.player.pauseVideo();
        });
    }

    /** 止めた位置からそのまま再開する。ずれは再生しながら直す */
    function resumeAll(entries) {
        stopTimers();
        var targets = entries.filter(usable);
        setPlaying(true);
        targets.forEach(function (e) { e.player.playVideo(); });
        startWatching(targets);
    }

    /** 停止ボタン。再生中なら止め、止まっていれば再開する (まだ一度も再生していなければ開始位置から) */
    function toggle(entries) {
        if (playing) return pauseAll(entries);
        if (!started) return playAll(entries);
        resumeAll(entries);
    }

    /** @param {boolean} enabled 自動補正するか */
    function setAutoSync(enabled) {
        autoSync = enabled;
    }

    return { playAll: playAll, pauseAll: pauseAll, toggle: toggle, setAutoSync: setAutoSync };
})();
