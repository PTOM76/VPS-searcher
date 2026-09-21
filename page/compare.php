<?php
// page/compare.php
require_once __DIR__ . '/../lib/SyncOffsets.php';
// 複数のYouTube動画を、動画ごとに開始位置を合わせて同時再生する

// お気に入りはYouTube以外(ニコニコ動画等)も混在しうるため、
// YouTubeの動画ID形式 (英数字・-・_ の11文字) のものだけを候補にする
$compareFavorites = [];
if (Auth::isLoggedIn()) {
    foreach (Auth::getFavorites($currentUser['id']) as $favorite) {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $favorite['video_id'])) $compareFavorites[] = $favorite;
    }
}

$compareText = [
    'invalid_url' => $lang['compare_invalid_url'],
    'loading' => $lang['compare_loading'],
    'remove' => $lang['remove'],
    'start_seconds' => $lang['compare_start_seconds'],
    'current_pos' => $lang['compare_current_pos'],
    'current_pos_title' => $lang['compare_current_pos_title'],
    'crop' => $lang['compare_crop'],
    'crop_full' => $lang['compare_crop_full'],
    'crop_left' => $lang['compare_crop_left'],
    'crop_right' => $lang['compare_crop_right'],
    'mute' => $lang['compare_mute'],
    'move_left' => $lang['compare_move_left'],
    'move_right' => $lang['compare_move_right'],
    'base' => $lang['compare_base'],
    'base_unset' => $lang['compare_base_unset'],
    'base_here' => $lang['compare_base_here'],
    'base_save' => $lang['compare_base_save'],
    'base_saved' => $lang['compare_base_saved'],
    'base_cleared' => $lang['compare_base_cleared'],
    'base_login' => $lang['compare_base_login'],
    'base_title' => $lang['compare_base_title'],
    'save_failed' => $lang['error_occurred'],
    'url_copied' => $lang['compare_url_copied'],
    'pause' => $lang['compare_pause_all'],
    'resume' => $lang['compare_resume'],
    'record_start' => $lang['compare_record_start'],
    'record_stop' => $lang['compare_record_stop'],
    'recording' => $lang['compare_recording'],
    'record_saved' => $lang['compare_record_saved'],
    'record_cancelled' => $lang['compare_record_cancelled'],
    'record_unsupported' => $lang['compare_record_unsupported'],
];
?>
<link rel="stylesheet" type="text/css" href="page/compare.css?v=<?php echo filemtime(__DIR__ . '/compare.css'); ?>" />
<div class="compare-container">
    <h2><?php echo $lang['compare']; ?></h2>

    <div class="compare-bar">
        <input type="text" id="compare-url" size="36" placeholder="<?php echo htmlspecialchars($lang['compare_url_placeholder']); ?>" onkeydown="if (event.key === 'Enter') CompareVideos.add()">
        <button type="button" onclick="CompareVideos.add()"><?php echo $lang['compare_add']; ?></button>
        <button type="button" onclick="CompareVideos.openFavorites()"><?php echo $lang['compare_from_favorites']; ?></button>
        <span class="flex-break"></span>
        <label for="compare-size"><?php echo $lang['compare_size']; ?>:</label>
        <select id="compare-size" onchange="CompareVideos.setSize(parseInt(this.value, 10))">
            <option value="320"><?php echo $lang['compare_size_s']; ?></option>
            <option value="480"><?php echo $lang['compare_size_m']; ?></option>
            <option value="640"><?php echo $lang['compare_size_l']; ?></option>
        </select>
        <button type="button" onclick="CompareVideos.playAll()"><?php echo $lang['compare_play_all']; ?></button>
        <button type="button" id="compare-toggle" onclick="CompareVideos.togglePause()"><?php echo $lang['compare_pause_all']; ?></button>
        <button type="button" onclick="CompareVideos.clearAll()"><?php echo $lang['compare_clear']; ?></button>
        <label><input type="checkbox" id="compare-join" checked onchange="CompareVideos.setJoined(this.checked)"> <?php echo $lang['compare_join']; ?></label>
        <label><input type="checkbox" id="compare-auto-sync" checked onchange="ComparePlayer.setAutoSync(this.checked)"> <?php echo $lang['compare_auto_sync']; ?></label>
    </div>
    <p>
        <?php echo $lang['compare_mode']; ?>:
        <label><input type="radio" name="compare-mode" value="base" checked onchange="CompareVideos.setMode(this.value)"> <?php echo $lang['compare_mode_base']; ?></label>
        <label><input type="radio" name="compare-mode" value="start" onchange="CompareVideos.setMode(this.value)"> <?php echo $lang['compare_mode_start']; ?></label>
    </p>
    <p class="compare-base-only">
        <label><?php echo $lang['compare_relative']; ?>: <input type="number" id="compare-relative" step="0.01" value="0" style="width:5em" oninput="CompareVideos.setRelative(parseFloat(this.value) || 0)"></label><br>
        <?php echo $lang['compare_relative_help']; ?>
    </p>
    <p>
        <button type="button" onclick="CompareUrl.copy(document.getElementById('compare-share-status'), COMPARE_TEXT.url_copied)"><?php echo $lang['compare_copy_url']; ?></button>
        <?php if (Auth::isLoggedIn()): ?>
            <input type="text" id="compare-save-name" size="24" maxlength="100" placeholder="<?php echo htmlspecialchars($lang['compare_save_name']); ?>">
            <button type="button" onclick="CompareUrl.save(document.getElementById('compare-save-name').value).then(function (m) { document.getElementById('compare-share-status').textContent = m; })"><?php echo $lang['compare_save']; ?></button>
        <?php else: ?>
            <?php echo $lang['compare_save_login']; ?>
        <?php endif; ?>
        <span id="compare-share-status"></span>
    </p>
    <p>
        <button type="button" id="compare-record" onclick="CompareRecorder.toggle()"><?php echo $lang['compare_record_start']; ?></button>
        <span id="compare-record-status"></span>
    </p>

    <div id="compare-grid" class="compare-grid"></div>

    <p id="compare-empty" class="empty-message"><?php echo $lang['compare_empty']; ?></p>
</div>

<!-- お気に入りの選択。開いている間だけ被せる (常時表示すると一覧が居座って邪魔になる) -->
<div id="compare-modal" class="compare-modal" hidden>
    <div class="compare-modal-backdrop" onclick="CompareVideos.closeFavorites()"></div>
    <div class="compare-modal-body" role="dialog" aria-modal="true" aria-label="<?php echo htmlspecialchars($lang['compare_from_favorites']); ?>">
        <div class="compare-modal-head">
            <strong><?php echo $lang['compare_from_favorites']; ?></strong>
            <button type="button" onclick="CompareVideos.closeFavorites()"><?php echo $lang['close']; ?></button>
        </div>

        <?php if (!Auth::isLoggedIn()): ?>
            <p><?php echo $lang['compare_login_required']; ?> <a href="?do=login"><?php echo $lang['login']; ?></a></p>
        <?php elseif (empty($compareFavorites)): ?>
            <p><?php echo $lang['compare_no_favorites']; ?></p>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($compareFavorites as $favorite): ?>
                    <?php
                    // サムネイル未保存のお気に入りもあるが、ここはYouTubeに絞ってあるのでIDから導ける
                    $thumbnail = !empty($favorite['thumbnail'])
                        ? $favorite['thumbnail']
                        : 'https://i.ytimg.com/vi/' . $favorite['video_id'] . '/mqdefault.jpg';
                    ?>
                    <a class="favorite-item compare-picker-item" style="width:240px" href="javascript:void(0)" onclick="CompareVideos.add('<?php echo htmlspecialchars($favorite['video_id'], ENT_QUOTES); ?>')">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="" width="240" height="135" style="width:240px;height:135px;object-fit:cover;" loading="lazy">
                        <div class="favorite-title"><?php echo htmlspecialchars($favorite['title']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    var COMPARE_TEXT = <?php echo json_encode($compareText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
    var COMPARE_OFFSETS = <?php echo json_encode((object)SyncOffsets::offsets()); ?>;
</script>
<?php foreach (['compare-slot.js', 'compare-sync.js', 'compare-play.js', 'compare-url.js', 'compare-record.js', 'compare.js'] as $script): ?>
    <script src="page/<?php echo $script; ?>?v=<?php echo filemtime(__DIR__ . '/' . $script); ?>"></script>
<?php endforeach; ?>
<script>CompareVideos.loadFromUrl();</script>
<script src="https://www.youtube.com/iframe_api"></script>
