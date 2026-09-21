<?php
// admin/tabs/reports.php
// admin/index.php からの include 専用。直接開かれたら何もしない
if (!defined('ADMIN_TABS')) exit;
// 通報 (report/) の一覧

$reports = AdminActions::reports()->all();
?>
<?php if (empty($reports)): ?>
    <p><?php echo $adminText['reports_empty']; ?></p>
<?php else: ?>
    <table class="admin-table">
        <tr>
            <th><?php echo $adminText['report_date']; ?></th>
            <th><?php echo $adminText['report_video']; ?></th>
            <th><?php echo $adminText['report_type']; ?></th>
            <th><?php echo $adminText['report_reason']; ?></th>
            <th></th>
        </tr>
        <?php foreach ($reports as $report): ?>
            <?php $videoId = trim($report['id'] ?? ''); ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', (int)$report['mtime']); ?></td>
                <td><a href="<?php echo AdminView::e(AdminView::videoUrl($videoId)); ?>" target="_blank" rel="noopener"><?php echo AdminView::e($videoId); ?></a></td>
                <td><?php echo AdminView::e($report['type'] ?? ''); ?></td>
                <td><?php echo nl2br(AdminView::e(trim($report['reason'] ?? ''))); ?></td>
                <td>
                    <?php echo AdminView::button('report_blacklist', $report['file'], $adminText['report_blacklist_button'], $adminText['confirm']); ?>
                    <?php echo AdminView::button('report_done', $report['file'], $adminText['report_done_button']); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
