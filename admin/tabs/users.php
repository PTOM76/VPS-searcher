<?php
// admin/tabs/users.php
// admin/index.php からの include 専用。直接開かれたら何もしない
if (!defined('ADMIN_TABS')) exit;
// users.json のユーザー一覧

$users = Auth::getAllUsers();
?>
<p><?php echo sprintf($adminText['users_count'], count($users)); ?></p>

<table class="admin-table">
    <tr>
        <th><?php echo $adminText['user_name']; ?></th>
        <th><?php echo $adminText['user_email']; ?></th>
        <th><?php echo $adminText['user_created']; ?></th>
        <th><?php echo $adminText['user_chreeid']; ?></th>
        <th><?php echo $adminText['user_favorites']; ?></th>
        <th></th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo AdminView::e($user['username']); ?></td>
            <td><?php echo AdminView::e($user['email']); ?></td>
            <td><?php echo AdminView::e($user['created_at']); ?></td>
            <td><?php echo $user['chree_id'] === null ? '-' : '✓'; ?></td>
            <td><?php echo $user['favorites']; ?></td>
            <td>
                <?php if ($user['id'] === $currentUser['id']): ?>
                    <?php echo $adminText['user_self']; ?>
                <?php else: ?>
                    <?php echo AdminView::button('user_delete', $user['id'], $adminText['user_delete_button'], $adminText['user_delete_confirm']); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
