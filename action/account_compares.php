<?php
// action/account_compares.php
// マイページの「保存した比較」。action/account.php から include する

$savedCompares = CompareSaves::listFor($currentUser['id']);
?>
<div class="account-section">
    <h2><?php echo $lang['saved_compares']; ?></h2>
    <?php if (empty($savedCompares)): ?>
        <p><?php echo $lang['saved_compares_empty']; ?></p>
    <?php else: ?>
        <table class="mypage-info">
            <?php foreach ($savedCompares as $compare): ?>
                <tr>
                    <td>
                        <a href="./?<?php echo htmlspecialchars($compare['query'] . $langQuery); ?>"><?php echo htmlspecialchars($compare['name']); ?></a>
                        (<?php echo sprintf($lang['compare_video_count'], CompareQuery::countVideos($compare['query'])); ?>)
                    </td>
                    <td><?php echo htmlspecialchars($compare['created_at']); ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="rename_compare">
                            <input type="hidden" name="compare_id" value="<?php echo htmlspecialchars($compare['id']); ?>">
                            <input type="text" name="compare_name" size="16" maxlength="100" value="<?php echo htmlspecialchars($compare['name']); ?>" required>
                            <input type="submit" value="<?php echo htmlspecialchars($lang['rename']); ?>">
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($lang['confirm_delete_compare'], JSON_UNESCAPED_UNICODE)); ?>)">
                            <input type="hidden" name="action" value="delete_compare">
                            <input type="hidden" name="compare_id" value="<?php echo htmlspecialchars($compare['id']); ?>">
                            <input type="submit" value="<?php echo htmlspecialchars($lang['delete']); ?>">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
