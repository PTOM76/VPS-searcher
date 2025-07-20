<?php
session_start();

class Auth {
    private static $usersFile = __DIR__ . '/../data/users.json';
    private static $favoritesFile = __DIR__ . '/../data/favorites.json';
    
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
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        // ユーザー名が既に存在するかチェック
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return ['success' => false, 'message' => 'ユーザー名が既に使用されています'];
            }
            if ($user['email'] === $email) {
                return ['success' => false, 'message' => 'メールアドレスが既に使用されています'];
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
        
        return ['success' => true, 'message' => 'アカウントが作成されました'];
    }
    
    public static function login($username, $password) {
        self::init();
        
        $users = json_decode(file_get_contents(self::$usersFile), true);
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return ['success' => true, 'message' => 'ログインしました'];
            }
        }
        
        return ['success' => false, 'message' => 'ユーザー名またはパスワードが間違っています'];
    }
    
    public static function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'ログアウトしました'];
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
                return ['success' => false, 'message' => '既にお気に入りに追加されています'];
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
        
        return ['success' => true, 'message' => 'お気に入りに追加しました'];
    }
    
    public static function removeFromFavorites($userId, $videoId) {
        self::init();
        
        $favorites = json_decode(file_get_contents(self::$favoritesFile), true);
        
        if (!isset($favorites[$userId])) {
            return ['success' => false, 'message' => 'お気に入りが見つかりません'];
        }
        
        $favorites[$userId] = array_filter($favorites[$userId], function($fav) use ($videoId) {
            return $fav['video_id'] !== $videoId;
        });
        
        $favorites[$userId] = array_values($favorites[$userId]); // インデックスを再配列
        
        file_put_contents(self::$favoritesFile, json_encode($favorites, JSON_UNESCAPED_UNICODE));
        
        return ['success' => true, 'message' => 'お気に入りから削除しました'];
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
}
?>
