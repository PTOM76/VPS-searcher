<?php
// アプリケーション設定

/**
 * プレイリスト設定
 */
class PlaylistConfig {
    const VPS_PLAYLIST_ID = "PLdKTS7WkYMJErsSEK6C0VAQin9N8zfVr5";
    const MATERIAL_PLAYLIST_ID = "PLdKTS7WkYMJFj8REOW1mRE_hEK0QY9FrM";
    
    /**
     * デフォルトプレイリストを取得
     */
    public static function getDefaultPlaylists() {
        return [
            self::VPS_PLAYLIST_ID => "vps",
            self::MATERIAL_PLAYLIST_ID => "material"
        ];
    }
}

/**
 * ファイルパス設定
 */
class FilePaths {
    const DATA_DIR = "data/";
    const QUEUE_DIR = "queue/";
    const REPORT_DIR = "report/";
    const ANALYTICS_DIR = "data/analytics/";
    const CACHE_THUMB_DIR = "cache/thumb/";
    
    const INDEX_JSON = "data/index.json";
    const AI_INDEX_JSON = "data/ai_index.json";
    const PLAYLISTS_JSON = "data/playlists.json";
    const NC_VIDEOS_JSON = "data/nc_videos.json";
    const YT_VIDEOS_JSON = "data/yt_videos.json";
    const BLACKLIST_JSON = "blacklist.json";
    const TIME_TXT = "time.txt";
    
    /**
     * ディレクトリが存在しない場合は作成
     */
    public static function ensureDirectoryExists($dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * URL正規表現パターン
 */
class UrlPatterns {
    const PLAYLIST_ID = '/.*?&list\=(.*?)/u';
    const NICOVIDEO_ID = '/.*?(sm.*?)/u';
    const YOUTUBE_ID = '/.*?watch\?v\=(.*?)/u';
}

/**
 * アプリケーション定数
 */
class AppConstants {
    const UPDATE_INTERVAL = 86400; // 24時間
    const MAX_DESCRIPTION_LENGTH = 100;
    
    // XML/Feed設定
    const SITEMAP_MAX_URLS = 50000; // サイトマップの最大URL数
    const RSS_MAX_ITEMS = 50; // RSSの最大アイテム数
    const ATOM_MAX_ENTRIES = 50; // Atomの最大エントリ数
    
    // SEO設定
    const SITE_NAME = 'ボ対検索ツール';
    const SITE_DESCRIPTION = 'ボイパ対決という音MADに特化した検索ツール。YouTube・ニコニコ動画の動画を検索できます。';
    const SITE_KEYWORDS = 'ボイパ対決,音MAD,検索,YouTube,ニコニコ動画,VPS';
}
