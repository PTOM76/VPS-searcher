<?php
/**
 * エラーハンドリングとログ管理
 */

class ErrorHandler {
    private static $logPath = 'logs/error.log';
    
    /**
     * エラーログを記録
     */
    public static function log($message, $level = 'ERROR') {
        FilePaths::ensureDirectoryExists(dirname(self::$logPath));
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents(self::$logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * API呼び出しエラーの処理
     */
    public static function handleApiError($response, $context = '') {
        if (empty($response)) {
            self::log("API call failed: Empty response. Context: $context");
            return false;
        }
        
        $data = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log("API call failed: Invalid JSON response. Context: $context");
            return false;
        }
        
        if (isset($data->error)) {
            self::log("API call failed: {$data->error->message}. Context: $context");
            return false;
        }
        
        return $data;
    }
    
    /**
     * ファイル操作エラーの処理
     */
    public static function handleFileError($operation, $filePath, $error = '') {
        $message = "File operation failed: $operation on $filePath";
        if ($error) {
            $message .= ". Error: $error";
        }
        self::log($message);
    }
}

/**
 * データバリデーション
 */
class DataValidator {
    /**
     * URL の検証
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * YouTube動画ID の検証
     */
    public static function isValidYouTubeId($id) {
        return preg_match('/^[a-zA-Z0-9_-]{11}$/', $id);
    }
    
    /**
     * ニコニコ動画ID の検証
     */
    public static function isValidNicovideoId($id) {
        return preg_match('/^sm\d+$/', $id);
    }
    
    /**
     * プレイリストID の検証
     */
    public static function isValidPlaylistId($id) {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $id);
    }
    
    /**
     * 検索クエリの検証
     */
    public static function validateSearchQuery($query) {
        // XSS対策
        $query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
        
        // 長さ制限
        if (mb_strlen($query) > 100) {
            $query = mb_substr($query, 0, 100);
        }
        
        return trim($query);
    }
}
