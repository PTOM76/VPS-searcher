/**
 * 動画比較の状態 ⇔ URL パラメータ。
 *
 *   ?compare&v=ID~l~m~12.5,ID2&rel=-5&size=480&join=0&mode=start
 *
 * v の各要素は「動画ID~切り取り(l/r)~ミュート(m)~開始位置」で、後ろの既定値は省く。
 * 開始位置は mode=start (動画ごとに手で決める) の時だけ、rel (基準からの開始位置) はそれ以外の時だけ入る。書式は lib/CompareQuery.php と揃えること。
 */
var CompareUrl = (function () {
    var CROP_TO_CODE = { full: '', left: 'l', right: 'r' };
    var CODE_TO_CROP = { l: 'left', r: 'right' };
    var SIZES = [320, 480, 640];

    function round2(value) {
        return Math.round(value * 100) / 100;
    }

    /**
     * 貼られた URL から YouTube の動画IDを取り出す。
     * watch?app=desktop&v=... のように v が先頭でない URL や、shorts / live / embed も受ける
     * @param {string} input URL または動画ID
     * @returns {string|null}
     */
    function extractVideoId(input) {
        input = input.trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(input)) return input;
        var m = input.match(/youtube\.com\/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})/)
            || input.match(/(?:youtu\.be\/|youtube\.com\/(?:embed|shorts|live)\/)([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }

    /** @param {{id: string, crop: string, muted: boolean, start: number|null}} video */
    function encodeVideo(video) {
        var fields = [video.id, CROP_TO_CODE[video.crop] || '', video.muted ? 'm' : '', video.start === null ? '' : String(round2(video.start))];
        while (fields.length > 1 && fields[fields.length - 1] === '') fields.pop();
        return fields.join('~');
    }

    /** @returns {{id: string, crop: string, muted: boolean, start: number|null}|null} 動画IDが不正なら null */
    function decodeVideo(item) {
        var fields = item.split('~');
        if (!/^[A-Za-z0-9_-]{11}$/.test(fields[0])) return null;

        var start = parseFloat(fields[3]);
        return { id: fields[0], crop: CODE_TO_CROP[fields[1]] || 'full', muted: fields[2] === 'm', start: isFinite(start) ? start : null };
    }

    /** @returns {string} 先頭の ? を除いたクエリ文字列 */
    function build(state) {
        var parts = ['compare'];
        if (state.videos.length > 0) parts.push('v=' + state.videos.map(encodeVideo).join(','));
        if (state.mode !== 'start' && state.rel) parts.push('rel=' + round2(state.rel));
        if (state.size !== 320) parts.push('size=' + state.size);
        if (!state.join) parts.push('join=0');
        if (state.mode === 'start') parts.push('mode=start');

        // 言語をパラメータで指定している時は引き継ぐ (en.php 等はパスの側に入っている)
        var lang = new URLSearchParams(location.search).get('lang');
        if (lang) parts.push('lang=' + encodeURIComponent(lang));
        return parts.join('&');
    }

    /** @returns {{videos: Array, rel: number, size: number, join: boolean, mode: string}} */
    function read() {
        var params = new URLSearchParams(location.search);
        var size = parseInt(params.get('size'), 10);
        var videos = (params.get('v') || '').split(',').filter(Boolean).map(decodeVideo).filter(Boolean);

        return {
            videos: videos,
            rel: parseFloat(params.get('rel')) || 0,
            size: SIZES.indexOf(size) >= 0 ? size : 320,
            join: params.get('join') !== '0',
            mode: params.get('mode') === 'start' ? 'start' : 'base',
        };
    }

    /** 操作のたびに呼ばれるので、履歴は積まずに今の URL を差し替える */
    function write(state) {
        history.replaceState(null, '', location.pathname + '?' + build(state));
    }

    /** @param {HTMLElement} statusEl 結果を出す所 */
    function copy(statusEl, message) {
        navigator.clipboard.writeText(location.href).then(function () { statusEl.textContent = message; });
    }

    /**
     * 今の URL の比較をマイページに保存する
     * @param {string} name
     * @param {string} compareId 上書きする保存分のID。空なら新しく保存する
     * @returns {Promise<{success: boolean, compare_id: string, message: string}>}
     */
    function save(name, compareId) {
        var form = new FormData();
        form.append('action', 'save_compare');
        form.append('name', name);
        form.append('compare_id', compareId);
        form.append('query', location.search.replace(/^\?/, ''));

        return fetch('ajax/action.php', { method: 'POST', body: form }).then(function (res) { return res.json(); });
    }

    /**
     * 比較ページの保存欄から保存する。新しく保存した時は保存先の一覧に足して選んでおき、次からは上書きにする
     */
    function saveFromForm() {
        var target = document.getElementById('compare-save-target');
        var name = document.getElementById('compare-save-name').value;
        save(name, target.value).then(function (data) {
            document.getElementById('compare-share-status').textContent = data.message;
            if (!data.success) return;

            var option = target.querySelector('option[value="' + data.compare_id + '"]');
            if (!option) {
                option = document.createElement('option');
                option.value = data.compare_id;
                target.appendChild(option);
            }
            option.textContent = name;
            target.value = data.compare_id;
        });
    }

    return { read: read, write: write, copy: copy, saveFromForm: saveFromForm, extractVideoId: extractVideoId };
})();
