<?php
// admin/tabs/blacklist.php
// admin/index.php からの include 専用。直接開かれたら何もしない
if (!defined('ADMIN_TABS')) exit;
// blacklist.json の編集

$blacklist = Blacklist::all();
?>
<p><?php echo $adminText['blacklist_help']; ?></p>

<form method="POST">
    <?php echo AdminView::csrfField(); ?>
    <input type="hidden" name="action" value="blacklist_add">
    <input type="text" name="target" placeholder="<?php echo AdminView::e($adminText['blacklist_id']); ?>" required>
    <input type="submit" value="<?php echo AdminView::e($adminText['blacklist_add_button']); ?>">
</form>

<?php if (empty($blacklist)): ?>
    <p><?php echo $adminText['blacklist_empty']; ?></p>
<?php else: ?>
    <table class="admin-table">
        <?php foreach (array_reverse($blacklist) as $videoId): ?>
            <tr>
                <td><a href="<?php echo AdminView::e(AdminView::videoUrl($videoId)); ?>" target="_blank" rel="noopener"><?php echo AdminView::e($videoId); ?></a></td>
                <td><?php echo AdminView::button('blacklist_remove', $videoId, $adminText['blacklist_remove_button']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
