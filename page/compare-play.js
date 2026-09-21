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
    /** ずれを判定する間隔 */
    var CHECK_INTERVAL_MS = 500;
    /** 再生直後や位置を飛ばした直後はプレイヤーが揺れるので、この間は判定しない */
    var SETTLE_MS = 1500;
    /** これ未満のずれは直さない / 速度で寄せきったとみなす */
    var TOLERANCE_S = 0.03;
    /** これを超えるずれは速度では寄せきれないので位置を飛ばす */
    var SEEK_THRESHOLD_S = 0.5;
    var NUDGE_RATE = 0.05;

    var trackTimer = null;
    var checkTimer = null;
    var delayTimers = [];
    /** 準備中に停止・再実行されたとき、古い準備の続きを走らせないための番号 */
    var session = 0;
    var started = false;
    var playing = false;

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

    /** YouTube 側が 1.05 倍などの細かい速度に対応しているか (対応していなければ位置を飛ばすしかない) */
    function canNudge(entry) {
        if (typeof entry.player.getAvailablePlaybackRates !== 'function') return false;
        return entry.player.getAvailablePlaybackRates().indexOf(1 + NUDGE_RATE) >= 0;
    }

    function setRate(entry, rate) {
        if (entry.player.getPlaybackRate() !== rate) entry.player.setPlaybackRate(rate);
    }

    /**
     * @param {Object} entry
     * @param {number} drift 基準より進んでいる秒数 (マイナスなら遅れ)
     * @param {number} expected 本来いるべき位置
     */
    function correct(entry, drift, expected) {
        var nudge = canNudge(entry);
        if (Math.abs(drift) < TOLERANCE_S) {
            if (nudge) setRate(entry, 1);
            entry.strikes = 0;
            return;
        }
        // 一度だけのずれは見積もりの誤差かもしれないので、続いた時だけ直す
        entry.strikes = (entry.strikes || 0) + 1;
        if (entry.strikes < 2) return;

        if (nudge && Math.abs(drift) < SEEK_THRESHOLD_S) return setRate(entry, drift > 0 ? 1 - NUDGE_RATE : 1 + NUDGE_RATE);
        if (!nudge && Math.abs(drift) < 0.1) return;
        if (expected < 0) return;

        entry.player.seekTo(expected, true);
        entry.clock = null;
        entry.settleUntil = performance.now() + SETTLE_MS;
        entry.strikes = 0;
    }

    /**
     * 基準の経過時間は、再生中の動画の (見積もり位置 - 開始位置) の中央値。
     * 時計を基準にすると全部が同時に詰まった時に全部を動かしてしまうので、多数派に合わせる
     */
    function check(entries) {
        var now = performance.now();
        var targets = entries.filter(function (e) { return isPlaying(e) && e.clock && !(e.settleUntil > now); });
        if (targets.length < 2) return;

        var elapsed = targets.map(function (e) { return estimate(e) - e.startSeconds; });
        var median = elapsed.slice().sort(function (a, b) { return a - b; })[Math.floor(elapsed.length / 2)];
        targets.forEach(function (e, i) { correct(e, elapsed[i] - median, e.startSeconds + median); });
    }

    function startWatching(entries) {
        var settleUntil = performance.now() + SETTLE_MS;
        entries.forEach(function (e) {
            e.clock = null;
            e.strikes = 0;
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

    return { playAll: playAll, pauseAll: pauseAll, toggle: toggle };
})();
