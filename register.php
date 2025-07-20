<?php
require_once './lib/auth.php';
require_once "lang.ini.php";

if (!isset($useLang))
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang];
Auth::setLanguage($lang);

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
    header('Location: ' . ($useLang === "ja" ? "./" : "./" . $useLang . ".php"));
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title><?php echo $lang['register_title']; ?> - <?php echo $lang['title']; ?></title>
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
            <h1><?php echo $lang['register_title']; ?></h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username"><?php echo $lang['username']; ?></label><br />
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email"><?php echo $lang['email']; ?></label><br />
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?php echo $lang['password']; ?></label><br />
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><?php echo $lang['confirm_password']; ?></label><br />
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <input type="submit" name="register" value="<?php echo $lang['register_button']; ?>">
        </form>

        <div class="auth-links">
            <p>
            	<?php echo $lang['already_have_account']; ?>
            	<a href="login.php<?php echo $useLang !== "ja" ? "?lang=" . $useLang : ""; ?>"><?php echo $lang['login']; ?></a>
            </p>
        </div>
    </div>
</body>
</html>
