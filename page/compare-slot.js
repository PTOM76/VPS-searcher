/**
 * 動画比較の1本分の枠 (プレイヤー・題名・操作部品)
 */
var CompareSlot = (function () {
    var TEXT = window.COMPARE_TEXT;

    function buildOption(value, label) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }

    /**
     * 1本分の枠を作る。文字列はDOM APIで入れる (題名等をinnerHTMLに混ぜない)
     * @param {string} slotId プレイヤーを差し込む要素のID
     * @returns {{slot: HTMLElement, q: Function}} 枠と、枠内を引く関数
     */
    function build(slotId) {
        var slot = document.createElement('div');
        slot.className = 'compare-slot crop-full';
        slot.innerHTML =
            '<div class="compare-view"><div id="' + slotId + '"></div></div>' +
            '<div class="favorite-title"><span class="compare-no"></span>. <span class="compare-title"></span></div>' +
            '<div class="favorite-actions">' +
            '<label><span class="compare-label-crop"></span> <select class="compare-crop"></select></label> ' +
            '<label><input type="checkbox" class="compare-mute"> <span class="compare-label-mute"></span></label><br>' +
            '<label><span class="compare-label-start"></span> <input type="number" class="compare-offset" step="0.1" value="0" style="width:5em"></label> ' +
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

    return { build: build };
})();
