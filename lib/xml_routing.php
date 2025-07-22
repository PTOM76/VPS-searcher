<?php
/**
 * XML生成機能をindex.phpに統合するためのルーティング
 * sitemap.xml, rss.xml, feed.atom へのアクセスを処理
 */

// XMLルーティング処理を共通関数に追加
function handleXmlRouting() {
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    
    // XML関連のルーティング
    switch (basename($path)) {
        case 'sitemap.xml':
            $_GET['type'] = 'sitemap';
            include 'genxml.php';
            exit;
            
        case 'rss.xml':
        case 'feed.rss':
            $_GET['type'] = 'rss';
            include 'genxml.php';
            exit;
            
        case 'feed.atom':
            $_GET['type'] = 'atom';
            include 'genxml.php';
            exit;
    }
}

/**
 * robots.txtを生成
 */
function generateRobotsTxt() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    
    header('Content-Type: text/plain; charset=utf-8');
    
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /data/\n";
    echo "Disallow: /cache/\n";
    echo "Disallow: /queue/\n";
    echo "Disallow: /report/\n";
    echo "Disallow: /logs/\n";
    echo "Disallow: /lib/\n";
    echo "\n";
    echo "Sitemap: {$baseUrl}/sitemap.xml\n";
    
    exit;
}

// robots.txt のルーティング
if (basename($_SERVER['REQUEST_URI']) === 'robots.txt') {
    generateRobotsTxt();
}
?>
