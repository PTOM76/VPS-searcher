/**
 * 動画比較の同時再生。
 * YouTube の iframe は読み込み・バッファの具合で動き出す時刻がずれるので、
 * 先に全部を開始位置でバッファさせて一時停止し、揃ってから一斉に再生する。再生中もずれを直し続ける。
 *
 * entry は compare.js のもの ({ player, startSeconds, muted })。
 * startSeconds がマイナスの動画は、その秒数だけ遅れて頭から再生する (動画が基準より後から始まる場合)。
 */
var ComparePlayer = (function () {
    /** 読み込みが遅い動画を待つ上限。これを過ぎたら待たずに始める */
    var PREPARE_TIMEOUT_MS = 8000;
    /** これ以上ずれたら位置を合わせ直す。getCurrentTime() の更新が粗いので、これより細かくは測れない */
    var DRIFT_TOLERANCE_S = 0.1;
    var CHECK_INTERVAL_MS = 500;

    var driftTimer = null;
    var delayTimers = [];
    /** 準備中に停止・再実行されたとき、古い準備の続きを走らせないための番号 */
    var session = 0;

    function usable(entry) {
        return entry.player && typeof entry.player.seekTo === 'function' && typeof entry.player.getPlayerState === 'function';
    }

    function stopTimers() {
        clearInterval(driftTimer);
        driftTimer = null;
        delayTimers.forEach(clearTimeout);
        delayTimers = [];
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
                var playing = player.getPlayerState() === YT.PlayerState.PLAYING;
                if (!playing && Date.now() - startedAt < PREPARE_TIMEOUT_MS) return;
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
        driftTimer = setInterval(function () { correctDrift(entries); }, CHECK_INTERVAL_MS);
    }

    /**
     * 基準の経過時間は、再生中の動画の (現在位置 - 開始位置) の中央値。
     * 時計を基準にすると全部が同時に詰まった時に全部を動かしてしまうので、多数派に合わせる
     */
    function correctDrift(entries) {
        var playing = entries.filter(function (e) {
            return usable(e) && e.player.getPlayerState() === YT.PlayerState.PLAYING;
        });
        if (playing.length < 2) return;

        var elapsed = playing.map(function (e) { return e.player.getCurrentTime() - e.startSeconds; });
        var median = elapsed.slice().sort(function (a, b) { return a - b; })[Math.floor(elapsed.length / 2)];

        playing.forEach(function (e, i) {
            if (Math.abs(elapsed[i] - median) < DRIFT_TOLERANCE_S) return;
            var expected = e.startSeconds + median;
            if (expected >= 0) e.player.seekTo(expected, true);
        });
    }

    /** @param {Array} entries compare.js の entry の配列 */
    function playAll(entries) {
        stopTimers();
        var mySession = ++session;
        var targets = entries.filter(usable);

        Promise.all(targets.map(prepareOne)).then(function () {
            if (mySession !== session) return;
            startAll(targets);
        });
    }

    function pauseAll(entries) {
        session++;
        stopTimers();
        entries.forEach(function (e) {
            if (usable(e)) e.player.pauseVideo();
        });
    }

    return { playAll: playAll, pauseAll: pauseAll };
})();
