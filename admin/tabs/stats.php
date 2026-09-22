<?php
// admin/tabs/stats.php
// admin/index.php からの include 専用。直接開かれたら何もしない
if (!defined('ADMIN_TABS')) exit;
// 検索ログと収録動画の統計

require_once __DIR__ . '/../../lib/admin/SearchAnalytics.php';

const STATS_PERIODS = [7, 30, 90];
$days = in_array((int)($_GET['days'] ?? 0), STATS_PERIODS, true) ? (int)$_GET['days'] : 7;
$analytics = new SearchAnalytics($days);

$index = file_exists(FilePaths::INDEX_JSON) ? json_decode(file_get_contents(FilePaths::INDEX_JSON), true) : [];
$typeCounts = array_count_values(array_map(fn($v) => (string)($v['type'] ?? ''), $index));
$latest = reset($index);
$latestId = key($index);
?>
<div class="account-section">
    <h2><?php echo $adminText['stats_videos_heading']; ?></h2>
    <table class="admin-table">
        <tr><th><?php echo $adminText['stats_videos_total']; ?></th><td><?php echo count($index); ?></td></tr>
        <tr><th><?php echo $lang['vps_radio']; ?></th><td><?php echo $typeCounts['vps'] ?? 0; ?></td></tr>
        <tr><th><?php echo $lang['material_radio']; ?></th><td><?php echo $typeCounts['material'] ?? 0; ?></td></tr>
        <?php if ($latest): ?>
            <tr>
                <th><?php echo $adminText['stats_latest_video']; ?></th>
                <td><?php echo date('Y-m-d H:i', (int)$latest['publishedAt']); ?>
                    <a href="https://youtu.be/<?php echo AdminView::e((string)$latestId); ?>" target="_blank" rel="noopener noreferrer"><?php echo AdminView::e($latest['title']); ?></a></td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<div class="account-section">
    <h2><?php echo $adminText['stats_search_heading']; ?></h2>
    <p>
        <?php foreach (STATS_PERIODS as $i => $period): ?>
            <?php echo $i > 0 ? ' | ' : ''; ?>
            <?php $label = sprintf($adminText['stats_days'], $period); ?>
            <?php if ($period === $days): ?>
                <strong><?php echo $label; ?></strong>
            <?php else: ?>
                <a href="./?tab=stats&amp;days=<?php echo $period; ?>"><?php echo $label; ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        (<?php echo sprintf($adminText['stats_total'], $analytics->total()); ?>)
    </p>

    <h3><?php echo $adminText['stats_top_words']; ?></h3>
    <?php $topWords = $analytics->topWords(30); ?>
    <?php if (empty($topWords)): ?>
        <p><?php echo $adminText['stats_empty']; ?></p>
    <?php else: ?>
        <table class="admin-table">
            <tr><th><?php echo $adminText['stats_word']; ?></th><th><?php echo $adminText['stats_count']; ?></th></tr>
            <?php foreach ($topWords as $word => $count): ?>
                <tr>
                    <td><a href="../?q=<?php echo urlencode((string)$word); ?>" target="_blank"><?php echo AdminView::e((string)$word); ?></a></td>
                    <td><?php echo $count; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3><?php echo $adminText['stats_recent_words']; ?></h3>
    <?php $recent = $analytics->recent(50); ?>
    <?php if (empty($recent)): ?>
        <p><?php echo $adminText['stats_empty']; ?></p>
    <?php else: ?>
        <table class="admin-table">
            <tr><th><?php echo $adminText['report_date']; ?></th><th><?php echo $adminText['stats_word']; ?></th></tr>
            <?php foreach ($recent as $entry): ?>
                <tr>
                    <td><?php echo AdminView::e($entry['date']); ?></td>
                    <td><a href="..<?php echo AdminView::e($entry['uri']); ?>" target="_blank"><?php echo AdminView::e($entry['word']); ?></a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3><?php echo $adminText['stats_daily']; ?></h3>
    <table class="admin-table">
        <tr><th><?php echo $adminText['stats_date']; ?></th><th><?php echo $adminText['stats_count']; ?></th></tr>
        <?php foreach (array_reverse($analytics->daily(), true) as $date => $count): ?>
            <tr><td><?php echo $date; ?></td><td><?php echo $count; ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
