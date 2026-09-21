/**
 * 比較画面の録画。
 * YouTube の iframe の中身はページから取り出せないので、ブラウザのタブ共有 (getDisplayMedia) でタブごと受け取り、
 * 各動画のプレイヤー部分だけを切り出して canvas に横一列に並べ、それを録画する。
 * (並べている入れ物ごと切り抜くと、画面幅いっぱいの余白や動画の下の題名・操作欄まで入ってしまう)
 * (Chrome の Region Capture は条件が厳しく切り抜けないことがあるので使わない)
 * ファイルは CompareMp4 (WebCodecs) で作る。使えないブラウザだけ MediaRecorder で録る (再生位置を変えられないことがある)
 */
var CompareRecorder = (function () {
    var TEXT = window.COMPARE_TEXT;
    /** MediaRecorder で録る時の形式。mp4 を優先する (webm は長さが入らず、開けないプレイヤーがある) */
    var MIME_TYPES = [
        'video/mp4;codecs=avc1.640028,mp4a.40.2',
        'video/mp4;codecs=avc1,mp4a.40.2',
        'video/mp4',
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
    ];
    var FPS = 30;
    var VIDEO_BITS_PER_SECOND = 8000000;
    var AUDIO_BITS_PER_SECOND = 256000;

    var recorder = null;
    /** CompareMp4 で録っている時の書き出し先。MediaRecorder の時は null */
    var writer = null;
    var captured = null;
    var source = null;
    var drawing = false;
    var chunks = [];
    var mimeType = '';

    function supported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia && window.MediaRecorder);
    }

    function showStatus(message) {
        document.getElementById('compare-record-status').textContent = message;
    }

    function showRecording(recording) {
        document.getElementById('compare-record').textContent = recording ? TEXT.record_stop : TEXT.record_start;
    }

    function pickMimeType() {
        for (var i = 0; i < MIME_TYPES.length; i++) {
            if (MediaRecorder.isTypeSupported(MIME_TYPES[i])) return MIME_TYPES[i];
        }
        return '';
    }

    /** タブの音声にはマイク向けの処理がかかって音が悪くなるので、全部切る */
    function requestCapture() {
        return navigator.mediaDevices.getDisplayMedia({
            video: { frameRate: FPS },
            audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false, channelCount: 2, sampleRate: 48000 },
            preferCurrentTab: true,
            selfBrowserSurface: 'include',
            systemAudio: 'include',
        });
    }

    /** 受け取った映像を再生する (見えない) video。ここから canvas に描き写す */
    function playSource(stream) {
        source = document.createElement('video');
        source.muted = true;
        source.srcObject = stream;
        return source.play().then(function () {
            if (source.videoWidth > 0) return;
            return new Promise(function (resolve) { source.onloadedmetadata = resolve; });
        });
    }

    /** 表示している順 (compare.js は並べ替えを CSS の order で行う) のプレイヤー部分 */
    function views() {
        return Array.prototype.slice.call(document.querySelectorAll('#compare-grid .compare-view')).sort(function (x, y) {
            return (parseInt(x.parentNode.style.order, 10) || 0) - (parseInt(y.parentNode.style.order, 10) || 0);
        });
    }

    /**
     * 要素の範囲を、受け取った映像のピクセルの範囲に直す。
     * タブの映像はウィンドウの表示領域そのままなので、幅の比で拡大すればよい
     */
    function regionOf(element) {
        var rect = element.getBoundingClientRect();
        var scale = source.videoWidth / window.innerWidth;
        return { x: rect.left * scale, y: rect.top * scale, w: rect.width * scale, h: rect.height * scale };
    }

    /** H.264 は幅・高さが偶数でないと作れない */
    function even(value) {
        return Math.max(2, Math.round(value / 2) * 2);
    }

    /**
     * 各プレイヤーを左から順に隙間なく描く。
     * canvas の大きさは録画を始めた時で固定する (途中で変えるとエンコーダが壊れる) ので、
     * 始めた時の各プレイヤーの幅を覚えておき、その枠に収めて描く
     */
    function layoutAtStart() {
        return views().map(function (view) {
            var r = regionOf(view);
            return { w: r.w, h: r.h };
        });
    }

    function drawViews(context, slots) {
        var x = 0;
        views().forEach(function (view, i) {
            if (!slots[i]) return;
            var r = regionOf(view);
            context.drawImage(source, r.x, r.y, r.w, r.h, x, 0, slots[i].w, slots[i].h);
            x += slots[i].w;
        });
    }

    /**
     * @param {boolean} crop false なら受け取った映像をそのまま描く (ウィンドウ・画面全体を選ばれた時)
     */
    function startDrawing(crop) {
        var slots = crop ? layoutAtStart() : [];
        var width = crop ? slots.reduce(function (sum, s) { return sum + s.w; }, 0) : source.videoWidth;
        var height = crop ? Math.max.apply(null, slots.map(function (s) { return s.h; }).concat([0])) : source.videoHeight;

        var canvas = document.createElement('canvas');
        canvas.width = even(width);
        canvas.height = even(height);
        var context = canvas.getContext('2d');

        drawing = true;
        (function draw() {
            if (!drawing) return;
            context.fillStyle = '#000';
            context.fillRect(0, 0, canvas.width, canvas.height);
            if (crop) drawViews(context, slots);
            else context.drawImage(source, 0, 0, canvas.width, canvas.height);
            if (writer) writer.addFrame();
            requestAnimationFrame(draw);
        })();
        return canvas;
    }

    function showStarted() {
        showRecording(true);
        showStatus(TEXT.recording);
    }

    /** 使えれば CompareMp4 (再生位置を変えられる MP4)、だめなら MediaRecorder で録る */
    function startRecording(canvas) {
        if (!CompareMp4.supported()) return startRecorder(canvas);

        return CompareMp4.create(canvas, captured.getAudioTracks()[0])
            .then(function (created) {
                writer = created;
                showStarted();
                if (created.audioCodec === 'opus') showStatus(TEXT.record_opus);
            })
            // コーデックが使えない等で作れなかった時は MediaRecorder に切り替える
            .catch(function () { startRecorder(canvas); });
    }

    function startRecorder(canvas) {
        var tracks = canvas.captureStream(FPS).getVideoTracks().concat(captured.getAudioTracks());
        mimeType = pickMimeType();
        chunks = [];
        recorder = new MediaRecorder(new MediaStream(tracks), {
            mimeType: mimeType || undefined,
            videoBitsPerSecond: VIDEO_BITS_PER_SECOND,
            audioBitsPerSecond: AUDIO_BITS_PER_SECOND,
        });
        recorder.ondataavailable = function (event) {
            if (event.data.size > 0) chunks.push(event.data);
        };
        recorder.onstop = function () {
            download(new Blob(chunks, { type: mimeType.split(';')[0] || 'video/webm' }));
        };
        recorder.start(1000);
        showStarted();
    }

    function release() {
        drawing = false;
        if (captured) captured.getTracks().forEach(function (track) { track.stop(); });
        captured = null;
        source = null;
        recorder = null;
        writer = null;
        showRecording(false);
    }

    function fileName(extension) {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return 'compare-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate())
            + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.' + extension;
    }

    function download(blob) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = fileName(blob.type === 'video/mp4' ? 'mp4' : 'webm');
        link.click();
        // クリック直後に解放するとダウンロードが始まらないブラウザがあるので、少し待つ
        setTimeout(function () { URL.revokeObjectURL(url); }, 10000);

        chunks = [];
        release();
        showStatus(TEXT.record_saved);
    }

    function start() {
        if (!supported()) return showStatus(TEXT.record_unsupported);

        requestCapture()
            .then(function (stream) {
                captured = stream;
                var track = stream.getVideoTracks()[0];
                // ブラウザ側の「共有を停止」で止められた時も保存する
                track.addEventListener('ended', stop);
                return playSource(stream).then(function () {
                    // ウィンドウ・画面全体を選ばれた時は座標が合わないので切り抜かない。
                    // タブを選んだ時の displaySurface はブラウザによって入らないことがあるので、'browser' かどうかでは判定しない
                    var surface = track.getSettings().displaySurface;
                    return startRecording(startDrawing(surface !== 'monitor' && surface !== 'window' && views().length > 0));
                });
            })
            .catch(function () {
                release();
                showStatus(TEXT.record_cancelled);
            });
    }

    function stop() {
        if (writer) {
            var finishing = writer;
            writer = null;
            drawing = false;
            return finishing.stop().then(download);
        }
        if (recorder && recorder.state !== 'inactive') return recorder.stop();
        release();
    }

    function toggle() {
        if (recorder || writer) return stop();
        start();
    }

    return { toggle: toggle };
})();
