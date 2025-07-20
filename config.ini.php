<?php
set_time_limit(600);
date_default_timezone_set('UTC');
//define("API_KEY", getenv("API_KEY"));
define("MAX_VIEW", 50);

// サイト設定
define("SITE_REPOSITORY_URL", "https://github.com/PTOM76/VPS-searcher");
define("SITE_AUTHOR", "Pitan");
define("SITE_COPYRIGHT_YEARS", "2023-2025");

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);