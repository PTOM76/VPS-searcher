<?php
require_once '../lib/auth.php';
require_once '../lang.ini.php';

header('Content-Type: application/json');

if (!isset($useLang))
    $useLang = $_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'ja');

$lang = $_lang[$useLang] ?? $_lang['ja'];
Auth::setLanguage($lang);

// ログインチェック
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => $lang['login_required']]);
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
            if ($result['success']) {
                $result['message'] = $lang['added_to_favorites'];
            }
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => $lang['error_occurred']]);
        }
        break;
        
    case 'remove_favorite':
        $videoId = $_POST['video_id'] ?? '';
        
        if (!empty($videoId)) {
            $result = Auth::removeFromFavorites($user['id'], $videoId);
            if ($result['success']) {
                $result['message'] = $lang['removed_from_favorites'];
            }
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => $lang['error_occurred']]);
        }
        break;
        
    case 'check_favorite':
        $videoId = $_POST['video_id'] ?? '';
        
        if (!empty($videoId)) {
            $isFavorite = Auth::isFavorite($user['id'], $videoId);
            echo json_encode(['success' => true, 'is_favorite' => $isFavorite]);
        } else {
            echo json_encode(['success' => false, 'message' => $lang['error_occurred']]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => $lang['error_occurred']]);
        break;
}
?>
