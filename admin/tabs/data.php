<?php
// admin/tabs/data.php
// admin/index.php からの include 専用。直接開かれたら何もしない
if (!defined('ADMIN_TABS')) exit;
// データ更新・再生リスト/動画の登録・投稿キュー

$lastUpdate = file_exists(FilePaths::TIME_TXT) ? (int)file_get_contents(FilePaths::TIME_TXT) : 0;
$queue = AdminActions::queue()->all();
?>
<div class="account-section">
    <h2><?php echo $adminText['data_update_heading']; ?></h2>
    <p><?php echo $adminText['data_last_update']; ?>: <?php echo $lastUpdate > 0 ? date('Y-m-d H:i', $lastUpdate) : '-'; ?></p>
    <p>
        <?php echo AdminView::button('data_update', '', $adminText['data_update_button']); ?>
        <?php echo AdminView::button('data_full_update', '', $adminText['data_full_update_button'], $adminText['confirm']); ?>
    </p>
    <p><?php echo $adminText['data_update_help']; ?></p>
</div>

<div class="account-section">
    <h2><?php echo $adminText['data_add_heading']; ?></h2>
    <form method="POST">
        <?php echo AdminView::csrfField(); ?>
        <input type="hidden" name="action" value="data_add">
        <input type="text" name="target" size="50" placeholder="<?php echo AdminView::e($adminText['data_add_placeholder']); ?>" required>
        <label><input type="radio" name="type" value="vps" required> <?php echo $lang['vps_radio']; ?></label>
        <label><input type="radio" name="type" value="material"> <?php echo $lang['material_radio']; ?></label>
        <input type="submit" value="<?php echo AdminView::e($adminText['data_add_button']); ?>">
    </form>
</div>

<div class="account-section">
    <h2><?php echo $adminText['queue_heading']; ?></h2>
    <?php if (empty($queue)): ?>
        <p><?php echo $adminText['queue_empty']; ?></p>
    <?php else: ?>
        <table class="admin-table">
            <tr>
                <th><?php echo $adminText['report_date']; ?></th>
                <th><?php echo $adminText['queue_url']; ?></th>
                <th><?php echo $adminText['queue_type']; ?></th>
                <th></th>
            </tr>
            <?php foreach ($queue as $item): ?>
                <?php $url = trim($item['url'] ?? ''); ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', (int)$item['mtime']); ?></td>
                    <td><a href="<?php echo AdminView::e($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo AdminView::e($url); ?></a></td>
                    <td><?php echo AdminView::e($item['type'] ?? ''); ?></td>
                    <td>
                        <?php echo AdminView::button('queue_approve', $item['file'], $adminText['queue_approve_button']); ?>
                        <?php echo AdminView::button('queue_reject', $item['file'], $adminText['queue_reject_button'], $adminText['confirm']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
