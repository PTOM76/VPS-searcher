<?php
// action/account.php

// ログインチェック
if (!Auth::isLoggedIn()) {
    header('Location: /?do=login');
    exit;
}

$currentUser = Auth::getCurrentUser();
$userDetails = Auth::getUserDetails($currentUser['id']);

$message = '';
$messageType = '';

// フォーム処理
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_username':
            $newUsername = $_POST['new_username'] ?? '';
            if (!empty($newUsername)) {
                $result = Auth::updateUsername($currentUser['id'], $newUsername);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $currentUser = Auth::getCurrentUser();
                    $userDetails = Auth::getUserDetails($currentUser['id']);
                }
            } else {
                $message = $lang['all_fields_required'];
                $messageType = 'error';
            }
            break;
            
        case 'update_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if ($newPassword !== $confirmPassword) {
                $message = $lang['password_mismatch'];
                $messageType = 'error';
            } else if (!empty($currentPassword) && !empty($newPassword)) {
                if (strlen($newPassword) >= 6) {
                    $result = Auth::updatePassword($currentUser['id'], $currentPassword, $newPassword);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                } else {
                    $message = $lang['password_min_length'];
                    $messageType = 'error';
                }
            } else {
                $message = $lang['all_fields_required'];
                $messageType = 'error';
            }
            break;
            
        case 'update_email':
            $newEmail = $_POST['new_email'] ?? '';
            if (!empty($newEmail)) {
                if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $result = Auth::updateEmail($currentUser['id'], $newEmail);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    if ($result['success']) {
                        $userDetails = Auth::getUserDetails($currentUser['id']);
                    }
                } else {
                    $message = $lang['valid_email_required'];
                    $messageType = 'error';
                }
            } else {
                $message = $lang['all_fields_required'];
                $messageType = 'error';
            }
            break;
            
        case 'delete_account':
            $currentPassword = $_POST['current_password'] ?? '';
            $confirmText = $_POST['confirm_text'] ?? '';
            if (!empty($currentPassword) && !empty($confirmText)) {
                $result = Auth::deleteAccount($currentUser['id'], $currentPassword, $confirmText);
                if ($result['success']) {
                    header('Location: ./?account_deleted=1');
                    exit;
                } else {
                    $message = $result['message'];
                    $messageType = 'error';
                }
            } else {
                $message = $lang['all_fields_required'];
                $messageType = 'error';
            }
            break;

    }
}

// ChreeID の状態を毎回確認する。chree_id は自動発行された「未引き取りの
// サービスアカウント」の可能性があり、それだけでは「連携済み」と言えない。
// claim-tickets を叩き、409 (already_claimed) なら本当に引き取り済み
require_once __DIR__ . '/../lib/ChreeIdProvisioner.php';

$chreeIdStatus = 'disabled'; // disabled | not_provisioned | unclaimed | claimed | unreachable
$chreeIdClaimUrl = null;

if (!empty($userDetails['chree_id'])) {
    try {
        $chreeIdClaimUrl = (new ChreeIdProvisioner())->claimUrl($userDetails);
        $chreeIdStatus = $chreeIdClaimUrl !== null ? 'unclaimed' : 'claimed';
    } catch (\Throwable $e) {
        $chreeIdStatus = 'unreachable';
    }
} elseif (ChreeIdProvisioner::isEnabled()) {
    $chreeIdStatus = 'not_provisioned';
}
?>

<div class="auth-container">
    <?php if ($chreeIdStatus !== 'disabled'): ?>
    <div class="account-section">
        <h2>ChreeID連携</h2>
        <?php if ($chreeIdStatus === 'claimed'): ?>
            <p>このアカウントは ChreeID (WikiChree.COM共通アカウント) と連携済みです。ChreeIDのパスワード/パスキー/Google連携でもログインできます。</p>
        <?php elseif ($chreeIdStatus === 'unclaimed'): ?>
            <p>ChreeIDのサービスアカウントが裏で用意されていますが、まだ引き取っていません(このサイト固有のアカウントのままです)。</p>
            <p><a href="<?php echo htmlspecialchars($chreeIdClaimUrl); ?>" class="btn btn-secondary">ChreeIDアカウントとして引き取る</a></p>
        <?php elseif ($chreeIdStatus === 'not_provisioned'): ?>
            <p>次回ログイン時に自動で用意されます。</p>
        <?php elseif ($chreeIdStatus === 'unreachable'): ?>
            <p>ChreeIDに接続できませんでした。時間を置いて再度お試しください。</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="account-section">
        <h2><?php echo $lang['favorites']; ?></h2>
        お気に入りに入れたものは以下のリンクから確認できます。
        <p><a href="?do=favorites"><?php echo $lang['favorites']; ?></a></p>
    </div>

    <div class="auth-header">
        <h1><?php echo $lang['account_settings']; ?></h1>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="account-section">
        <h2><?php echo $lang['change_password']; ?></h2>
        <form method="POST" class="auth-form">
            <input type="hidden" name="action" value="update_password">
            <div class="form-group">
                <label for="current_password"><?php echo $lang['current_password']; ?></label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password"><?php echo $lang['new_password']; ?></label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password"><?php echo $lang['confirm_password']; ?></label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <input type="submit" value="<?php echo $lang['update']; ?>">
        </form>
    </div>

    <div class="account-section">
        <h2><?php echo $lang['change_email']; ?></h2>
        <form method="POST" class="auth-form">
            <input type="hidden" name="action" value="update_email">
            <div class="form-group">
                <label for="new_email"><?php echo $lang['new_email']; ?></label>
                <input type="email" id="new_email" name="new_email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" required>
            </div>
            <input type="submit" value="<?php echo $lang['update']; ?>">
        </form>
    </div>

    <div class="account-section danger">
        <h2><?php echo $lang['delete_account']; ?></h2>
        <p><?php echo $lang['confirm_delete']; ?></p>
        <form method="POST" class="auth-form" onsubmit="return confirm('<?php echo $lang['confirm_delete']; ?>')">
            <input type="hidden" name="action" value="delete_account">
            <div class="form-group">
                <label for="delete_current_password"><?php echo $lang['current_password']; ?></label>
                <input type="password" id="delete_current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_text"><?php echo $lang['delete_confirmation']; ?></label>
                <input type="text" id="confirm_text" name="confirm_text" required>
            </div>
            <input type="submit" value="<?php echo $lang['delete']; ?>" class="delete-button">
        </form>
    </div>

    <div class="auth-links">
        <p>
            <a href="./">← <?php echo $lang['title']; ?></a>
        </p>
    </div>
</div>
