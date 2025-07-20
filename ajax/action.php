<?php
require_once '../lib/auth.php';

header('Content-Type: application/json');

// ログインチェック
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

$user = Auth::getCurrentUser();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_favorite':
        $videoId = $_POST['video_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $thumbnail = $_POST['thumbnail'] ?? '';
        
        if (!empty($videoId) && !empty($title)) {
            $result = Auth::addToFavorites($user['id'], $videoId, $title, $description, $thumbnail);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '必要な情報が不足しています']);
        }
        break;
        
    case 'remove_favorite':
        $videoId = $_POST['video_id'] ?? '';
        
        if (!empty($videoId)) {
            $result = Auth::removeFromFavorites($user['id'], $videoId);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '動画IDが必要です']);
        }
        break;
        
    case 'check_favorite':
        $videoId = $_POST['video_id'] ?? '';
        
        if (!empty($videoId)) {
            $isFavorite = Auth::isFavorite($user['id'], $videoId);
            echo json_encode(['success' => true, 'is_favorite' => $isFavorite]);
        } else {
            echo json_encode(['success' => false, 'message' => '動画IDが必要です']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '無効なアクションです']);
        break;
}
?>
