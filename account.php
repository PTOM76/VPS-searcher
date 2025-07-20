<?php
require_once './lib/auth.php';
require_once './lib/common.php';
require_once "lang.ini.php";

if (!isset($useLang))
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang];
Auth::setLanguage($lang);

// ログインチェック
if (!Auth::isLoggedIn()) {
    header('Location: login.php' . ($useLang !== "ja" ? "?lang=" . $useLang : ""));
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
            if (!empty($currentPassword) && !empty($newPassword)) {
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
                    header('Location: ' . ($useLang === "ja" ? "./" : "./" . $useLang . ".php") . '?account_deleted=1');
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title><?php echo $lang['account_settings']; ?> - <?php echo $lang['title']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="/favicon.png" />
    <link rel="stylesheet" type="text/css" href="main.css" />
    <script src="darkmode.js"></script>
</head>
<body>
    <?php
    renderNavigation($lang, $useLang, $currentUser);
    renderMobileMenu($lang, $useLang, $currentUser);
    ?>
    
    <div class="auth-container">
        <br />
        <div class="auth-header">
            <h1><?php echo $lang['account_settings']; ?></h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="account-section">
            <h2><?php echo $lang['change_username']; ?></h2>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="update_username">
                <div class="form-group">
                    <label for="current_username"><?php echo $lang['username']; ?> (<?php echo $lang['current']; ?>)</label><br />
                    <input type="text" id="current_username" value="<?php echo htmlspecialchars($userDetails['username']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="new_username"><?php echo $lang['new_username']; ?></label><br />
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <input type="submit" value="<?php echo $lang['update']; ?>">
            </form>
        </div>

        <div class="account-section">
            <h2><?php echo $lang['change_password']; ?></h2>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label for="current_password"><?php echo $lang['current_password']; ?></label><br />
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password"><?php echo $lang['new_password']; ?></label><br />
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <input type="submit" value="<?php echo $lang['update']; ?>">
            </form>
        </div>

        <div class="account-section">
            <h2><?php echo $lang['change_email']; ?></h2>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="update_email">
                <div class="form-group">
                    <label for="current_email"><?php echo $lang['email']; ?> (<?php echo $lang['current']; ?>)</label><br />
                    <input type="email" id="current_email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="new_email"><?php echo $lang['new_email']; ?></label><br />
                    <input type="email" id="new_email" name="new_email" required>
                </div>
                <input type="submit" value="<?php echo $lang['update']; ?>">
            </form>
        </div>

        <div class="account-section">
            <h2><?php echo $lang['favorites']; ?></h2>
            <p><a href="favorites.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo $lang['favorites']; ?></a></p>
        </div>

        <div class="account-section danger">
            <h2><?php echo $lang['delete_account']; ?></h2>
            <p><?php echo $lang['confirm_delete']; ?></p>
            <form method="POST" class="auth-form" onsubmit="return confirm('<?php echo $lang['confirm_delete']; ?>')">
                <input type="hidden" name="action" value="delete_account">
                <div class="form-group">
                    <label for="delete_current_password"><?php echo $lang['current_password']; ?></label><br />
                    <input type="password" id="delete_current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_text"><?php echo $lang['delete_confirmation']; ?></label><br />
                    <input type="text" id="confirm_text" name="confirm_text" required>
                </div>
                <input type="submit" value="<?php echo $lang['delete']; ?>" class="delete-button">
            </form>
        </div>

        <div class="auth-links">
            <p>
                <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>">← <?php echo $lang['title']; ?></a>
            </p>
        </div>
    </div>
</body>
</html>
