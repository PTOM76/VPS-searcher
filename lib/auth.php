<?php
session_start();

class Auth {
    private static $usersFile = __DIR__ . '/../data/users.json';
    private static $favoritesFile = __DIR__ . '/../data/favorites.json';
    private static $lang = null;
    
    public static function setLanguage($lang) {
        self::$lang = $lang;
    }
    
    private static function getLang() {
        if (self::$lang === null) {
            // デフォルトで日本語を設定
            require_once __DIR__ . '/../lang.ini.php';
            self::$lang = $_lang['ja'];
        }
        return self::$lang;
    }
    
    public static function init() {
        if (!file_exists(self::$usersFile)) {
            file_put_contents(self::$usersFile, json_encode([], JSON_UNESCAPED_UNICODE));
        }
        if (!file_exists(self::$favoritesFile)) {
            file_put_contents(self::$favoritesFile, json_encode([], JSON_UNESCAPED_UNICODE));
        }
    }
    
    public static function register($username, $password, $email) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        // ユーザー名が既に存在するかチェック
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return ['success' => false, 'message' => $lang['username_already_exists']];
            }
            if ($user['email'] === $email) {
                return ['success' => false, 'message' => $lang['email_already_exists']];
            }
        }
        
        // パスワードをハッシュ化
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 新しいユーザーを追加
        $newUser = [
            'id' => uniqid(),
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $users[] = $newUser;
        file_put_contents(self::$usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
        
        return ['success' => true, 'message' => $lang['account_created']];
    }
    
    public static function login($username, $password) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return ['success' => true, 'message' => $lang['login_success']];
            }
        }
        
        return ['success' => false, 'message' => $lang['invalid_credentials']];
    }
    
    public static function logout() {
        $lang = self::getLang();
        session_destroy();
        return ['success' => true, 'message' => $lang['logout_success']];
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ];
        }
        return null;
    }
    
    public static function addToFavorites($userId, $videoId, $title, $description = '', $thumbnail = '') {
        self::init();
        
        $favorites = json_decode(file_get_contents(self::$favoritesFile), true);
        
        if (!isset($favorites[$userId])) {
            $favorites[$userId] = [];
        }
        
        // 既にお気に入りに追加されているかチェック
        foreach ($favorites[$userId] as $fav) {
            if ($fav['video_id'] === $videoId) {
                return ['success' => false];
            }
        }
        
        $favorites[$userId][] = [
            'video_id' => $videoId,
            'title' => $title,
            'description' => $description,
            'thumbnail' => $thumbnail,
            'added_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents(self::$favoritesFile, json_encode($favorites, JSON_UNESCAPED_UNICODE));
        
        return ['success' => true];
    }
    
    public static function removeFromFavorites($userId, $videoId) {
        self::init();
        
        $favorites = json_decode(file_get_contents(self::$favoritesFile), true);
        
        if (!isset($favorites[$userId])) {
            return ['success' => false];
        }
        
        $favorites[$userId] = array_filter($favorites[$userId], function($fav) use ($videoId) {
            return $fav['video_id'] !== $videoId;
        });
        
        $favorites[$userId] = array_values($favorites[$userId]); // インデックスを再配列
        
        file_put_contents(self::$favoritesFile, json_encode($favorites, JSON_UNESCAPED_UNICODE));
        
        return ['success' => true];
    }
    
    public static function getFavorites($userId) {
        self::init();
        
        $favorites = json_decode(file_get_contents(self::$favoritesFile), true);
        
        return isset($favorites[$userId]) ? $favorites[$userId] : [];
    }
    
    public static function isFavorite($userId, $videoId) {
        $favorites = self::getFavorites($userId);
        
        foreach ($favorites as $fav) {
            if ($fav['video_id'] === $videoId) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function updateUsername($userId, $newUsername) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        // ユーザー名が既に存在するかチェック
        foreach ($users as $user) {
            if ($user['username'] === $newUsername && $user['id'] !== $userId) {
                return ['success' => false, 'message' => $lang['username_already_exists']];
            }
        }
        
        // ユーザー情報を更新
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['username'] = $newUsername;
                $_SESSION['username'] = $newUsername;
                break;
            }
        }
        
        file_put_contents(self::$usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
        return ['success' => true, 'message' => $lang['username_updated']];
    }
    
    public static function updatePassword($userId, $currentPassword, $newPassword) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                if (password_verify($currentPassword, $user['password'])) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    file_put_contents(self::$usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
                    return ['success' => true, 'message' => $lang['password_updated']];
                } else {
                    return ['success' => false, 'message' => $lang['current_password_incorrect']];
                }
            }
        }
        
        return ['success' => false, 'message' => $lang['error_occurred']];
    }
    
    public static function updateEmail($userId, $newEmail) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        // メールアドレスが既に存在するかチェック
        foreach ($users as $user) {
            if ($user['email'] === $newEmail && $user['id'] !== $userId) {
                return ['success' => false, 'message' => $lang['email_already_exists']];
            }
        }
        
        // ユーザー情報を更新
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['email'] = $newEmail;
                break;
            }
        }
        
        file_put_contents(self::$usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
        return ['success' => true, 'message' => $lang['email_updated']];
    }
    
    public static function deleteAccount($userId, $currentPassword, $confirmText) {
        self::init();
        $lang = self::getLang();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        // 削除確認テキストをチェック
        if ($confirmText !== 'DELETE' && $confirmText !== '削除') {
            return ['success' => false, 'message' => $lang['delete_text_incorrect']];
        }
        
        foreach ($users as $key => $user) {
            if ($user['id'] === $userId) {
                if (password_verify($currentPassword, $user['password'])) {
                    // ユーザーを削除
                    unset($users[$key]);
                    file_put_contents(self::$usersFile, json_encode(array_values($users), JSON_UNESCAPED_UNICODE));
                    
                    // お気に入りも削除
                    $favorites = json_decode(file_get_contents(self::$favoritesFile), true);
                    if (isset($favorites[$userId])) {
                        unset($favorites[$userId]);
                        file_put_contents(self::$favoritesFile, json_encode($favorites, JSON_UNESCAPED_UNICODE));
                    }
                    
                    // セッションを削除
                    session_destroy();
                    
                    return ['success' => true, 'message' => $lang['account_deleted']];
                } else {
                    return ['success' => false, 'message' => $lang['current_password_incorrect']];
                }
            }
        }
        
        return ['success' => false, 'message' => $lang['error_occurred']];
    }
    
    public static function getUserDetails($userId) {
        self::init();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'created_at' => $user['created_at']
                ];
            }
        }
        
        return null;
    }
}
?>
