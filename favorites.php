<?php
require_once './lib/auth.php';
require_once "lang.ini.php";

if (!isset($useLang))
    $useLang = "ja";

$lang = $_lang[$useLang];

// ログインチェック
if (!Auth::isLoggedIn()) {
    header('Location: login.php' . ($useLang !== "ja" ? "?lang=" . $useLang : ""));
    exit;
}

$user = Auth::getCurrentUser();
$message = '';
$messageType = '';

// お気に入りから削除
if (isset($_POST['remove_favorite'])) {
    $videoId = $_POST['video_id'] ?? '';
    if (!empty($videoId)) {
        $result = Auth::removeFromFavorites($user['id'], $videoId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// お気に入り一覧を取得
$favorites = Auth::getFavorites($user['id']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title>お気に入り - <?php echo $lang['title']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="/favicon.png" />
    <link rel="stylesheet" type="text/css" href="main.css" />
    <script src="darkmode.js"></script>
    <style>
        .favorites-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .favorites-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        .favorites-header h1 {
            color: var(--text-color);
            margin: 0;
        }
        .back-link {
            color: #007cba;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #007cba;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: #007cba;
            color: white;
        }
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .favorite-item {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .favorite-item:hover {
            transform: translateY(-2px);
        }
        .favorite-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .favorite-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--text-color);
        }
        .favorite-description {
            font-size: 14px;
            color: var(--text-color-secondary);
            margin-bottom: 10px;
            max-height: 60px;
            overflow: hidden;
        }
        .favorite-date {
            font-size: 12px;
            color: var(--text-color-secondary);
            margin-bottom: 10px;
        }
        .favorite-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #007cba;
            color: white;
        }
        .btn-primary:hover {
            background: #005a87;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .empty-message {
            text-align: center;
            padding: 50px;
            color: var(--text-color-secondary);
        }
        .empty-message h3 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li class="pc"><a href="?info"><?php echo $lang['info']; ?></a></li>
            <li class="pc"><a href="?post"><?php echo $lang['send_pl']; ?></a></li>
            <li class="dropdown pc">
                <a href="javascript:void(0)" class="dropbtn">Language</a>
                <div class="dropdown-content">
                    <a href="./favorites.php"><img src="./image/japanese.png" /> 日本語</a>
                    <a href="./favorites.php?lang=en"><img src="./image/english.png" /> English</a>
                    <a href="./favorites.php?lang=zh"><img src="./image/chinese.png" /> 中国语</a>
                    <a href="./favorites.php?lang=ko"><img src="./image/korean.png" /> 한국인</a>
                </div>
            </li>
            <li><a href="./matrix/<?php echo $useLang === "ja" ? "" : $useLang . ".php"; ?>"><img src="image/matrix.png" /></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="image/darkmode.png" /></a></li>
            <li class="pc"><span style="color: var(--text-color);">ようこそ、<?php echo htmlspecialchars($user['username']); ?>さん</span></li>
            <li class="pc"><a href="login.php?logout">ログアウト</a></li>
        </ul>
    </div>

    <div class="favorites-container">
        <div class="favorites-header">
            <h1>お気に入り動画</h1>
            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>" class="back-link">戻る</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
            <div class="empty-message">
                <h3>お気に入りがありません</h3>
                <p>動画をお気に入りに追加すると、ここに表示されます。</p>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite): ?>
                    <div class="favorite-item">
                        <?php if (!empty($favorite['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars($favorite['thumbnail']); ?>" alt="Thumbnail" class="favorite-thumbnail">
                        <?php endif; ?>
                        
                        <div class="favorite-title">
                            <?php echo htmlspecialchars($favorite['title']); ?>
                        </div>
                        
                        <?php if (!empty($favorite['description'])): ?>
                            <div class="favorite-description">
                                <?php echo htmlspecialchars(substr($favorite['description'], 0, 150)); ?>
                                <?php if (strlen($favorite['description']) > 150): ?>...<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="favorite-date">
                            追加日: <?php echo htmlspecialchars($favorite['added_at']); ?>
                        </div>
                        
                        <div class="favorite-actions">
                            <a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>?q=<?php echo urlencode($favorite['title']); ?>" class="btn btn-primary">検索で見る</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($favorite['video_id']); ?>">
                                <button type="submit" name="remove_favorite" class="btn btn-danger" onclick="return confirm('お気に入りから削除しますか？')">削除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Dropdown
            var dropdown = document.getElementsByClassName("dropdown");
            var i;

            for (i = 0; i < dropdown.length; i++) {
                dropdown[i].addEventListener("mouseover", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "block";
                });
                dropdown[i].addEventListener("mouseout", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "none";
                });
            }
        });
    </script>
</body>
</html>
