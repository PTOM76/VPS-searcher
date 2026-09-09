<?php
set_time_limit(600);
date_default_timezone_set('UTC');
//define("API_KEY", getenv("API_KEY"));
define("MAX_VIEW", 50);

// サイト設定
define("SITE_REPOSITORY_URL", "https://github.com/PTOM76/VPS-searcher");
define("SITE_AUTHOR", "Pitan");
define("SITE_COPYRIGHT_YEARS", "2023-2026");

// ChreeID (WikiChree.COM 共通アカウント) 連携。
// id.wikichree.com/admin/clients でこのサイトをOFFICIALクライアントとして登録し、
// 発行された client_id / client_secret を secret.ini.php 側に設定すること
define("CHREEID_ISSUER", "https://id.wikichree.com");

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);