/**
 * 動画比較の基準位置 (動画ごとに Bad Apple!! の「ながれ」が始まる秒数)。
 * 全ユーザー共通で保存され、開始位置は「基準位置 + 基準からの開始位置」で決まる。
 * 動画がサビ等から始まって「ながれ」を含まない場合、基準位置はマイナスになる。
 */
var CompareSync = (function () {
    var TEXT = window.COMPARE_TEXT;
    var offsets = window.COMPARE_OFFSETS || {};
    var NUDGES = [-1, -0.1, 0.1, 1];
    var relative = 0;

    function round1(value) {
        return Math.round(value * 10) / 10;
    }

    /** @returns {number|null} 未設定なら null */
    function baseOf(videoId) {
        return Object.prototype.hasOwnProperty.call(offsets, videoId) ? offsets[videoId] : null;
    }

    /** @returns {number|null} 基準位置が未設定なら null (開始位置は手で決めてもらう) */
    function startOf(entry) {
        return entry.base === null ? null : round1(entry.base + relative);
    }

    function setRelative(value) {
        relative = value;
    }

    function getRelative() {
        return relative;
    }

    /**
     * @param {string} videoId
     * @param {number|null} value null なら消す
     * @returns {Promise<void>}
     */
    function save(videoId, value) {
        var form = new FormData();
        form.append('action', 'set_sync_offset');
        form.append('video_id', videoId);
        form.append('offset', value === null ? '' : String(value));

        return fetch('ajax/action.php', { method: 'POST', body: form })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error('set_sync_offset failed');
                if (value === null) delete offsets[videoId];
                else offsets[videoId] = value;
            });
    }

    function button(label, onClick) {
        var el = document.createElement('button');
        el.type = 'button';
        el.textContent = label;
        el.addEventListener('click', onClick);
        return el;
    }

    /**
     * 基準位置の入力欄・微調整ボタン・保存ボタンの行を作る
     *
     * @param {Object} entry compare.js の entry。base を読み書きする
     * @param {Function} onChange 基準位置が変わった時 (開始位置を計算し直して、そこへ飛ばす)
     * @returns {HTMLElement}
     */
    function buildRow(entry, onChange) {
        var row = document.createElement('div');
        row.className = 'favorite-actions';

        var label = document.createElement('label');
        label.title = TEXT.base_title;
        label.textContent = TEXT.base + ' ';
        var input = document.createElement('input');
        input.type = 'number';
        input.step = '0.1';
        input.style.width = '5em';
        input.placeholder = TEXT.base_unset;
        input.value = entry.base === null ? '' : entry.base;
        label.appendChild(input);

        var status = document.createElement('span');

        function setBase(value) {
            entry.base = value;
            input.value = value === null ? '' : value;
            status.textContent = '';
            onChange(entry);
        }

        input.addEventListener('change', function () {
            setBase(input.value.trim() === '' ? null : round1(parseFloat(input.value) || 0));
        });

        row.appendChild(label);
        NUDGES.forEach(function (delta) {
            row.append(' ', button((delta > 0 ? '+' : '') + delta, function () {
                setBase(round1((entry.base === null ? 0 : entry.base) + delta));
            }));
        });
        row.appendChild(document.createElement('br'));
        row.append(button(TEXT.base_here, function () {
            if (!entry.player || typeof entry.player.getCurrentTime !== 'function') return;
            setBase(round1(entry.player.getCurrentTime()));
        }), ' ', buildSaveButton(entry, status), ' ', status);
        return row;
    }

    function buildSaveButton(entry, status) {
        var saveButton = button(TEXT.base_save, function () {
            save(entry.videoId, entry.base)
                .then(function () { status.textContent = entry.base === null ? TEXT.base_cleared : TEXT.base_saved; })
                .catch(function () { status.textContent = TEXT.save_failed; });
        });
        if (!window.isLoggedIn) {
            saveButton.disabled = true;
            saveButton.title = TEXT.base_login;
        }
        return saveButton;
    }

    return { baseOf: baseOf, startOf: startOf, setRelative: setRelative, getRelative: getRelative, buildRow: buildRow };
})();
