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
        
    case 'set_sync_offset':
        // 空で送られたら基準位置を消す
        require_once '../lib/SyncOffsets.php';
        $videoId = (string)($_POST['video_id'] ?? '');
        $rawOffset = trim((string)($_POST['offset'] ?? ''));
        $offset = $rawOffset === '' ? null : (float)$rawOffset;

        $saved = is_numeric($rawOffset) || $rawOffset === ''
            ? SyncOffsets::set($videoId, $offset, $user['username'])
            : false;
        echo json_encode(['success' => $saved, 'offset' => $offset]);
        break;

    case 'save_compare':
        require_once '../lib/CompareSaves.php';
        $saved = CompareSaves::add($user['id'], (string)($_POST['name'] ?? ''), (string)($_POST['query'] ?? ''));
        echo json_encode(['success' => $saved, 'message' => $saved ? $lang['compare_saved'] : $lang['compare_save_failed']]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => $lang['error_occurred']]);
        break;
}
?>
