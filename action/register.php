<?php
// action/register.php

$message = '';
$messageType = '';

// 登録処理
if (isset($_POST['register'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (!empty($username) && !empty($password) && !empty($confirm_password) && !empty($email)) {
        if ($password === $confirm_password) {
            if (strlen($password) >= 6) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result = Auth::register($username, $password, $email);
                    if ($result['success']) {
                        $message = $result['message'] . ' ' . $lang['redirect_to_login'];
                        $messageType = 'success';
                    } else {
                        $message = $result['message'];
                        $messageType = 'error';
                    }
                } else {
                    $message = $lang['valid_email_required'];
                    $messageType = 'error';
                }
            } else {
                $message = $lang['password_min_length'];
                $messageType = 'error';
            }
        } else {
            $message = $lang['password_mismatch'];
            $messageType = 'error';
        }
    } else {
        $message = $lang['all_fields_required'];
        $messageType = 'error';
    }
}

// 既にログインしている場合はリダイレクト
if (Auth::isLoggedIn()) {
    header('Location: ./');
    exit;
}
?>

<div class="auth-container">
    <h1><?php echo $lang['register'] ?? 'アカウント作成'; ?></h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" class="auth-form">
        <div class="form-group">
            <label for="username"><?php echo $lang['username'] ?? 'ユーザー名'; ?>:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="email"><?php echo $lang['email'] ?? 'メールアドレス'; ?>:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password"><?php echo $lang['password'] ?? 'パスワード'; ?>:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password"><?php echo $lang['confirm_password'] ?? 'パスワード（確認）'; ?>:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" name="register" class="btn-primary"><?php echo $lang['register'] ?? 'アカウント作成'; ?></button>
    </form>
    
    <div class="auth-links">
        <p><a href="?do=login"><?php echo $lang['login'] ?? 'ログイン'; ?></a></p>
        <p><a href="./"><?php echo $lang['back'] ?? '戻る'; ?></a></p>
    </div>
</div>
