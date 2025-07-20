<?php
require_once './auth/auth.php';
require_once "lang.ini.php";

if (!isset($useLang))
    $useLang = "ja";

$lang = $_lang[$useLang];

$message = '';
$messageType = '';

// ログイン処理
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $result = Auth::login($username, $password);
        if ($result['success']) {
            header('Location: ' . ($useLang === "ja" ? "./" : "./" . $useLang . ".php"));
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    } else {
        $message = 'ユーザー名とパスワードを入力してください';
        $messageType = 'error';
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: ' . ($useLang === "ja" ? "./" : "./" . $useLang . ".php"));
    exit;
}

// 既にログインしている場合はリダイレクト
if (Auth::isLoggedIn()) {
    header('Location: ' . ($useLang === "ja" ? "./" : "./" . $useLang . ".php"));
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title>ログイン - <?php echo $lang['title']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="/favicon.png" />
    <link rel="stylesheet" type="text/css" href="main.css" />
    <script src="darkmode.js"></script>
</head>
<body>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="image/darkmode.png" /></a></li>
        </ul>
    </div>

    <div class="auth-container">
    	<br />
        <div class="auth-header">
            <h1>ログイン</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">ユーザー名orメールアドレス</label><br />
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">パスワード</label><br />
                <input type="password" id="password" name="password" required>
            </div>
            
            <input type="submit" name="login" value="ログイン">
        </form>

        <div class="auth-links">
            <p>
            	アカウントをお持ちでない方は
            	<a href="register.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>" class="btn btn-secondary">新規登録</a>
            </p>
        </div>
    </div>
</body>
</html>
