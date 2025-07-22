<?php
require_once "config.ini.php";
require_once "lang.ini.php";
require_once './lib/config.php';
require_once './lib/common.php';

/**
 * XML生成クラス - サイトマップとRSSフィード
 */
class XmlGenerator {
    private $baseUrl;
    private $lang;
    
    public function __construct($baseUrl, $lang) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->lang = $lang;
    }
    
    /**
     * サイトマップXMLを生成
     */
    public function generateSitemap() {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // URLセット要素を作成
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $xml->appendChild($urlset);
        
        // メインページ
        $this->addUrlToSitemap($xml, $urlset, '/', '1.0', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/en.php', '1.0', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/zh.php', '1.0', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/ko.php', '1.0', 'weekly');
        
        // マトリックス表示
        $this->addUrlToSitemap($xml, $urlset, '/matrix/', '0.9', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/matrix/en.php', '0.9', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/matrix/zh.php', '0.9', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/matrix/ko.php', '0.9', 'weekly');
        
        // 静的ページ
        $this->addUrlToSitemap($xml, $urlset, '/?info', '0.8', 'weekly');
        $this->addUrlToSitemap($xml, $urlset, '/?post', '0.7', 'weekly');
        
        // 動画ページ（最新100件）
        //$this->addVideoUrlsToSitemap($xml, $urlset);
        
        return $xml->saveXML();
    }
    
    /**
     * RSS フィードを生成
     */
    public function generateRssFeed($limit = 50) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // RSS要素を作成
        $rss = $xml->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $rss->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->appendChild($rss);
        
        // チャンネル要素
        $channel = $xml->createElement('channel');
        $rss->appendChild($channel);
        
        // チャンネル情報
        $this->addChannelInfo($xml, $channel);
        
        // 動画アイテムを追加
        $this->addVideoItemsToRss($xml, $channel, $limit);
        
        return $xml->saveXML();
    }
    
    /**
     * Atom フィードを生成
     */
    public function generateAtomFeed($limit = 50) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Feed要素を作成
        $feed = $xml->createElement('feed');
        $feed->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $xml->appendChild($feed);
        
        // フィード情報
        $this->addAtomFeedInfo($xml, $feed);
        
        // 動画エントリを追加
        $this->addVideoEntriesToAtom($xml, $feed, $limit);
        
        return $xml->saveXML();
    }
    
    /**
     * サイトマップにURLを追加
     */
    private function addUrlToSitemap($xml, $urlset, $path, $priority = '0.5', $changefreq = 'weekly') {
        $url = $xml->createElement('url');
        
        $loc = $xml->createElement('loc', htmlspecialchars($this->baseUrl . $path));
        $url->appendChild($loc);
        
        $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
        $url->appendChild($lastmod);
        
        $changefreqEl = $xml->createElement('changefreq', $changefreq);
        $url->appendChild($changefreqEl);
        
        $priorityEl = $xml->createElement('priority', $priority);
        $url->appendChild($priorityEl);
        
        $urlset->appendChild($url);
    }
    
    /**
     * 動画URLをサイトマップに追加
     */
    private function addVideoUrlsToSitemap($xml, $urlset) {
        if (!file_exists(FilePaths::INDEX_JSON)) {
            return;
        }
        
        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
        $count = 0;
        
        foreach ($index as $id => $data) {
            if ($count >= 100) break; // 最新100件まで
            
            // 削除された動画はスキップ
            if (isset($data['error']) || $data['publishedAt'] === false) {
                continue;
            }
            
            $searchUrl = "/?q=" . urlencode($data['title']);
            $this->addUrlToSitemap($xml, $urlset, $searchUrl, '0.6', 'monthly');
            
            $count++;
        }
    }
    
    /**
     * RSSチャンネル情報を追加
     */
    private function addChannelInfo($xml, $channel) {
        $title = $xml->createElement('title', 'ボ対検索ツール - 最新動画');
        $channel->appendChild($title);
        
        $link = $xml->createElement('link', $this->baseUrl);
        $channel->appendChild($link);
        
        $description = $xml->createElement('description', 'ボイパ対決関連動画の最新情報をお届けします');
        $channel->appendChild($description);
        
        $language = $xml->createElement('language', 'ja');
        $channel->appendChild($language);
        
        $lastBuildDate = $xml->createElement('lastBuildDate', date('r'));
        $channel->appendChild($lastBuildDate);
        
        $generator = $xml->createElement('generator', 'VPS-searcher XML Generator');
        $channel->appendChild($generator);
        
        $copyright = $xml->createElement('copyright', 'Copyright 2023-2025 © Pitan');
        $channel->appendChild($copyright);
    }
    
    /**
     * RSS動画アイテムを追加
     */
    private function addVideoItemsToRss($xml, $channel, $limit) {
        if (!file_exists(FilePaths::INDEX_JSON)) {
            return;
        }
        
        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
        $count = 0;
        
        foreach ($index as $id => $data) {
            if ($count >= $limit) break;
            
            // 削除された動画はスキップ
            if (isset($data['error']) || $data['publishedAt'] === false) {
                continue;
            }
            
            $item = $xml->createElement('item');
            
            $title = $xml->createElement('title');
            $title->appendChild($xml->createCDATASection($data['title']));
            $item->appendChild($title);
            
            // 動画の実際のURL
            if (isset($data['is_nicovideo']) && $data['is_nicovideo']) {
                $videoUrl = "https://www.nicovideo.jp/watch/{$id}";
            } else {
                $videoUrl = "https://youtu.be/{$id}";
            }
            
            $link = $xml->createElement('link', htmlspecialchars($videoUrl));
            $item->appendChild($link);
            
            $description = $xml->createElement('description');
            $desc = mb_substr($data['description'], 0, 200) . (mb_strlen($data['description']) > 200 ? '...' : '');
            $description->appendChild($xml->createCDATASection($desc));
            $item->appendChild($description);
            
            $pubDate = $xml->createElement('pubDate', date('r', $data['publishedAt']));
            $item->appendChild($pubDate);
            
            $guid = $xml->createElement('guid', $id);
            $guid->setAttribute('isPermaLink', 'false');
            $item->appendChild($guid);
            
            $author = $xml->createElement('dc:creator');
            $author->appendChild($xml->createCDATASection($data['channelTitle']));
            $item->appendChild($author);
            
            $category = $xml->createElement('category', $data['type']);
            $item->appendChild($category);
            
            $channel->appendChild($item);
            $count++;
        }
    }
    
    /**
     * Atomフィード情報を追加
     */
    private function addAtomFeedInfo($xml, $feed) {
        $title = $xml->createElement('title', 'ボ対検索ツール - 最新動画');
        $feed->appendChild($title);
        
        $link = $xml->createElement('link');
        $link->setAttribute('href', $this->baseUrl);
        $feed->appendChild($link);
        
        $linkSelf = $xml->createElement('link');
        $linkSelf->setAttribute('href', $this->baseUrl . '/feed.atom');
        $linkSelf->setAttribute('rel', 'self');
        $feed->appendChild($linkSelf);
        
        $id = $xml->createElement('id', $this->baseUrl);
        $feed->appendChild($id);
        
        $updated = $xml->createElement('updated', date('c'));
        $feed->appendChild($updated);
        
        $subtitle = $xml->createElement('subtitle', 'ボイパ対決関連動画の最新情報');
        $feed->appendChild($subtitle);
        
        $author = $xml->createElement('author');
        $authorName = $xml->createElement('name', 'Pitan');
        $author->appendChild($authorName);
        $feed->appendChild($author);
    }
    
    /**
     * Atom動画エントリを追加
     */
    private function addVideoEntriesToAtom($xml, $feed, $limit) {
        if (!file_exists(FilePaths::INDEX_JSON)) {
            return;
        }
        
        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
        $count = 0;
        
        foreach ($index as $id => $data) {
            if ($count >= $limit) break;
            
            // 削除された動画はスキップ
            if (isset($data['error']) || $data['publishedAt'] === false) {
                continue;
            }
            
            $entry = $xml->createElement('entry');
            
            $title = $xml->createElement('title');
            $title->appendChild($xml->createCDATASection($data['title']));
            $entry->appendChild($title);
            
            $entryId = $xml->createElement('id', $this->baseUrl . '/video/' . $id);
            $entry->appendChild($entryId);
            
            // 動画の実際のURL
            if (isset($data['is_nicovideo']) && $data['is_nicovideo']) {
                $videoUrl = "https://www.nicovideo.jp/watch/{$id}";
            } else {
                $videoUrl = "https://youtu.be/{$id}";
            }
            
            $link = $xml->createElement('link');
            $link->setAttribute('href', htmlspecialchars($videoUrl));
            $entry->appendChild($link);
            
            $updated = $xml->createElement('updated', date('c', $data['publishedAt']));
            $entry->appendChild($updated);
            
            $published = $xml->createElement('published', date('c', $data['publishedAt']));
            $entry->appendChild($published);
            
            $summary = $xml->createElement('summary');
            $desc = mb_substr($data['description'], 0, 200) . (mb_strlen($data['description']) > 200 ? '...' : '');
            $summary->appendChild($xml->createCDATASection($desc));
            $entry->appendChild($summary);
            
            $author = $xml->createElement('author');
            $authorName = $xml->createElement('name');
            $authorName->appendChild($xml->createCDATASection($data['channelTitle']));
            $author->appendChild($authorName);
            $entry->appendChild($author);
            
            $category = $xml->createElement('category');
            $category->setAttribute('term', $data['type']);
            $entry->appendChild($category);
            
            $feed->appendChild($entry);
            $count++;
        }
    }
    
    /**
     * robots.txtを生成
     */
    public function generateRobotsTxt() {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /data/\n";
        $content .= "Disallow: /cache/\n";
        $content .= "Disallow: /queue/\n";
        $content .= "Disallow: /report/\n";
        $content .= "Disallow: /logs/\n";
        $content .= "Disallow: /lib/\n";
        $content .= "\n";
        $content .= "Sitemap: {$this->baseUrl}/sitemap.xml\n";
        
        return $content;
    }
    
    /**
     * XMLファイルを生成してファイルに保存
     */
    public function generateAndSaveFiles($limit = 50) {
        $results = [];
        
        try {
            // サイトマップ生成
            $sitemapContent = $this->generateSitemap();
            $sitemapFile = 'sitemap.xml';
            file_put_contents($sitemapFile, $sitemapContent);
            $results['sitemap'] = [
                'success' => true,
                'file' => $sitemapFile,
                'size' => filesize($sitemapFile)
            ];
            
            // RSSフィード生成
            $rssContent = $this->generateRssFeed($limit);
            $rssFile = 'rss.xml';
            file_put_contents($rssFile, $rssContent);
            $results['rss'] = [
                'success' => true,
                'file' => $rssFile,
                'size' => filesize($rssFile)
            ];
            
            // Atomフィード生成
            $atomContent = $this->generateAtomFeed($limit);
            $atomFile = 'feed.atom';
            file_put_contents($atomFile, $atomContent);
            $results['atom'] = [
                'success' => true,
                'file' => $atomFile,
                'size' => filesize($atomFile)
            ];
            
            // robots.txt生成
            $robotsContent = $this->generateRobotsTxt();
            $robotsFile = 'robots.txt';
            file_put_contents($robotsFile, $robotsContent);
            $results['robots'] = [
                'success' => true,
                'file' => $robotsFile,
                'size' => filesize($robotsFile)
            ];
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 生成されたファイルが存在するかチェック
     */
    public function checkGeneratedFiles() {
        $files = ['sitemap.xml', 'rss.xml', 'feed.atom', 'robots.txt'];
        $status = [];
        
        foreach ($files as $file) {
            $filePath = $file;
            $status[$file] = [
                'exists' => file_exists($filePath),
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'modified' => file_exists($filePath) ? filemtime($filePath) : 0
            ];
        }
        
        return $status;
    }
}

// リクエスト処理
$type = $_GET['type'] ?? 'sitemap';
$format = $_GET['format'] ?? 'xml';
$limit = (int)($_GET['limit'] ?? 50);
$generateFiles = true;

// ベースURLを取得
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

// 言語設定
$useLang = $_GET['lang'] ?? 'ja';
$lang = $_lang[$useLang] ?? $_lang['ja'];

$generator = new XmlGenerator($baseUrl, $lang);

// ファイル生成モード
if ($generateFiles) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $results = $generator->generateAndSaveFiles($limit);
        echo json_encode([
            'success' => true,
            'message' => 'ファイル生成が完了しました',
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ファイル状態確認モード
if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $status = $generator->checkGeneratedFiles();
        echo json_encode([
            'success' => true,
            'files' => $status,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// レスポンスヘッダーを設定
header('Content-Type: application/xml; charset=utf-8');

try {
    switch ($type) {
        case 'sitemap':
            echo $generator->generateSitemap();
            break;
            
        case 'rss':
            echo $generator->generateRssFeed($limit);
            break;
            
        case 'atom':
            echo $generator->generateAtomFeed($limit);
            break;
            
        case 'robots':
            header('Content-Type: text/plain; charset=utf-8');
            echo $generator->generateRobotsTxt();
            break;
            
        default:
            throw new Exception('Invalid type parameter');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<error>Invalid request: ' . htmlspecialchars($e->getMessage()) . '</error>';
}
?>
