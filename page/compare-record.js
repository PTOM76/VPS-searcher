/**
 * 比較画面の録画。
 * YouTube の iframe の中身はページから取り出せないので、ブラウザのタブ共有 (getDisplayMedia) でタブごと録り、
 * 対応ブラウザ (Chrome / Edge) では動画が並んでいる範囲に切り抜く (Region Capture)。
 */
var CompareRecorder = (function () {
    var TEXT = window.COMPARE_TEXT;
    var MIME_TYPES = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];

    var recorder = null;
    var stream = null;
    var chunks = [];

    function supported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia && window.MediaRecorder);
    }

    function showStatus(message) {
        document.getElementById('compare-record-status').textContent = message;
    }

    function showRecording(recording) {
        document.getElementById('compare-record').textContent = recording ? TEXT.record_stop : TEXT.record_start;
    }

    /**
     * 動画が並んでいる範囲だけに切り抜く。
     * 切り抜けない (非対応ブラウザ・別のタブを選ばれた) 時はタブ全体のまま録るので、失敗は握って先へ進める
     */
    function cropToGrid(track) {
        if (!window.CropTarget || typeof track.cropTo !== 'function') return Promise.resolve();
        return CropTarget.fromElement(document.getElementById('compare-grid'))
            .then(function (target) { return track.cropTo(target); })
            .catch(function () {});
    }

    function pickMimeType() {
        for (var i = 0; i < MIME_TYPES.length; i++) {
            if (MediaRecorder.isTypeSupported(MIME_TYPES[i])) return MIME_TYPES[i];
        }
        return '';
    }

    function releaseStream() {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        stream = null;
        recorder = null;
        showRecording(false);
    }

    function fileName() {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return 'compare-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate())
            + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.webm';
    }

    function save() {
        var url = URL.createObjectURL(new Blob(chunks, { type: 'video/webm' }));
        var link = document.createElement('a');
        link.href = url;
        link.download = fileName();
        link.click();
        // クリック直後に解放するとダウンロードが始まらないブラウザがあるので、少し待つ
        setTimeout(function () { URL.revokeObjectURL(url); }, 10000);

        chunks = [];
        releaseStream();
        showStatus(TEXT.record_saved);
    }

    function startRecorder() {
        var mimeType = pickMimeType();
        chunks = [];
        recorder = new MediaRecorder(stream, mimeType ? { mimeType: mimeType } : undefined);
        recorder.ondataavailable = function (event) {
            if (event.data.size > 0) chunks.push(event.data);
        };
        recorder.onstop = save;
        recorder.start(1000);
        showRecording(true);
        showStatus(TEXT.recording);
    }

    function start() {
        if (!supported()) return showStatus(TEXT.record_unsupported);

        navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 30 }, audio: true, preferCurrentTab: true, selfBrowserSurface: 'include' })
            .then(function (captured) {
                stream = captured;
                var track = stream.getVideoTracks()[0];
                // ブラウザ側の「共有を停止」で止められた時も保存する
                track.addEventListener('ended', stop);
                return cropToGrid(track);
            })
            .then(startRecorder)
            .catch(function () {
                releaseStream();
                showStatus(TEXT.record_cancelled);
            });
    }

    function stop() {
        if (recorder && recorder.state !== 'inactive') return recorder.stop();
        releaseStream();
    }

    function toggle() {
        if (recorder) return stop();
        start();
    }

    return { toggle: toggle };
})();
