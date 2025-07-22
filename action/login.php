<?php
// action/login.php

$message = '';
$messageType = '';

// ログイン処理
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $result = Auth::login($username, $password);
        if ($result['success']) {
            header('Location: ./');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    } else {
        $message = $lang['username_password_required'];
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
    <h1><?php echo $lang['login'] ?? 'ログイン'; ?></h1>
    
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
            <label for="password"><?php echo $lang['password'] ?? 'パスワード'; ?>:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" name="login" class="btn-primary"><?php echo $lang['login'] ?? 'ログイン'; ?></button>
    </form>
    
    <div class="auth-links">
        <p><a href="?do=register"><?php echo $lang['register'] ?? 'アカウント作成'; ?></a></p>
        <p><a href="./"><?php echo $lang['back'] ?? '戻る'; ?></a></p>
    </div>
</div>
