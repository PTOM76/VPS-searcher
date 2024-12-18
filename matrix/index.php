<?php
if (isset($_GET['q'])) {
  $analytics = "../data/analytics/" . date("Y-m-d") . ".txt";
  $data = '';
  if (file_exists($analytics))
    $data = file_get_contents($analytics);
  file_put_contents($analytics, $data . "DATE: " . date("Y-m-d_H:i:s") . "\n" . "URI: " . $_SERVER['REQUEST_URI'] . "\nWORD: " . $_GET['q'] . "\n----------------\n");
}

set_time_limit(600);
date_default_timezone_set('UTC');

define("MAX_VIEW", 50);
ini_set('display_errors', 0);

if (!isset($useLang))
    $useLang = "ja";

require_once "../lang.ini.php";

$lang = $_lang[$useLang];

global $notice;


function kan2num($str) {
    $str = str_replace("一", "1", $str);
    $str = str_replace("二", "2", $str);
    $str = str_replace("三", "3", $str);
    $str = str_replace("四", "4", $str);
    $str = str_replace("五", "5", $str);
    $str = str_replace("六", "6", $str);
    $str = str_replace("七", "7", $str);
    $str = str_replace("八", "8", $str);
    $str = str_replace("九", "9", $str);
    $str = str_replace("！", "!", $str);
    $str = str_replace("？", "?", $str);
  return $str;
}

function make_param(array $params) : string {
    $param = "";
    foreach ($params as $key => $value) {
        if ($value === null || empty($value)) continue;
        $param .= $key . "=" . $value . "&";
    }
    if (!empty($param)) {
        $param = "?" . substr($param, 0, -1);
    }
    return $param;
}

    function time_elapsed_string($datetime, $full = false) {
        global $lang, $useLang;

        $now = new DateTime;
        $ago = new DateTime;
        $ago->setTimestamp($datetime);
        $diff = $now->diff($ago);
    
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
      
        $string = array(
            'y' => $lang['year'],
            'm' => $lang['month'],
            'w' => $lang['week'],
            'd' => $lang['day'],
            'h' => $lang['hour'],
            'i' => $lang['minu'],
            's' => $lang['sec'],
        );

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                // $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                $v = $diff->$k . '' . $v . ($diff->$k > 1 ? ($useLang === "en" ? 's ' : '') : '');
            } else {
                unset($string[$k]);
            }
        }
    
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . $lang['ago'] : $lang['justnow']; // ago, just now
    }

    if (isset($_POST['do'])) {
        $url = $_POST['url'];

        $url_type = "none";
        if (false !== strpos($url, 'list=') || str_starts_with($url, 'PL')) {
          $url_type = "playlist";
        } else if (false !== strpos($url, 'watch/sm') || str_starts_with($url, 'sm')) {
          $url_type = "nicovideo";
        } else {
          $url_type = "youtube";
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title><?php echo $lang['title']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="../favicon.png" />
    <script>
        function onClickThumb($id) {
            document.getElementById("content_" + $id).innerHTML = '<iframe width="320" height="180" src="https://www.youtube.com/embed/' + $id + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
        }
      
        function onClickThumbNC($id) {
            var $script = document.createElement('script');
            $script.setAttribute("type", "application/javascript");
            $script.setAttribute("src", "https://embed.nicovideo.jp/watch/" + $id + "/script?w=320&h=180");
            document.getElementById("content_" + $id).innerHTML = '';
            document.getElementById("content_" + $id).appendChild($script);
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Dropdown
            var dropdown = document.getElementsByClassName("dropdown");
            var i;

            for (i = 0; i < dropdown.length; i++) {
                dropdown[i].addEventListener("mouseover", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "block";
                });
                dropdown[i].addEventListener("mouseout", function() {
                    this.getElementsByClassName("dropdown-content")[0].style.display = "none";
                });
            }
            // Lazy Load

            const lazyImages = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.onload = () => img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        });
    </script>
    <script src="../darkmode.js"></script>
    <link rel="stylesheet" type="text/css" href="../main.css" />
</head>
<body>
    <div id="navi">
        <ul>
            <li><a href="<?php echo $useLang === "ja" ? "./" : "./" . $useLang . ".php"; ?>"><?php echo $lang['title']; ?></a></li>
            <li><a href="../?info"><?php echo $lang['info']; ?></a></li>
            <li><a href="../?post"><?php echo $lang['send_pl']; ?></a></li>
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Language</a>
                <div class="dropdown-content">
                    <a href="./<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="../image/japanese.png" /> 日本語</a>
                    <a href="./en.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="../image/english.png" /> English</a>
                    <a href="./zh.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="../image/chinese.png" /> 中国语</a>
                    <a href="./ko.php<?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="../image/korean.png" /> 한국인</a>
                </div>
            </li>

            <li><a href="../<?php echo $useLang === "ja" ? "" : $useLang . ".php"; ?><?= isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"><img src="../image/vertical.png" /></a></li>
            <li><a href="javascript:toggleDarkMode();"><img id="darkmode" src="../image/darkmode.png" /></a></li>        </ul>
    </div>
    <br /><br />
    <div id="contents">
    
    <?php echo empty($notice) ? "" : '<h3>' . $notice . '</h3>'; ?>
<?php
    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $method = isset($_GET['method']) ? $_GET['method'] : 'and';
    $title = isset($_GET['title']) ? $_GET['title'] : '1';
    $expl = isset($_GET['expl']) ? $_GET['expl'] : '0';
    $author = isset($_GET['author']) ? $_GET['author'] : '0';
    $t = isset($_GET['t']) ? $_GET['t'] : 'all';

    $page = 1;
    if (isset($_GET['page']))
        $page = (int) $_GET['page'];

    ?>
    <form method="GET">
        <select name="sort">
            <option value="recent" <?php echo isset($_GET['sort']) && $_GET['sort'] === "recent" ? " selected" : ""; ?>><?php echo $lang['sort_recent']; ?></option>
            <option value="ancient" <?php echo isset($_GET['sort']) && $_GET['sort'] === "ancient" ? " selected" : ""; ?>><?php echo $lang['sort_ancient']; ?></option>
            <option value="most_view" <?php echo isset($_GET['sort']) && $_GET['sort'] === "most_view" ? " selected" : ""; ?>><?php echo $lang['sort_most_view']; ?></option>
            <option value="worst_view" <?php echo isset($_GET['sort']) && $_GET['sort'] === "worst_view" ? " selected" : ""; ?>><?php echo $lang['sort_worst_view']; ?></option>
            <option value="most_like" <?php echo isset($_GET['sort']) && $_GET['sort'] === "most_like" ? " selected" : ""; ?>><?php echo $lang['sort_most_like']; ?></option>
            <option value="word" <?php echo isset($_GET['sort']) && $_GET['sort'] === "word" ? " selected" : ""; ?>><?php echo $lang['sort_word']; ?></option>
            <option value="word_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] === "word_desc" ? " selected" : ""; ?>><?php echo $lang['sort_word_desc']; ?></option>
        </select>
        
        <input type="text" name ="q" value="<?php echo isset($_GET['q']) ? $_GET['q'] : '' ?>" />
        <input type="submit" value="<?php echo $lang['search']; ?>" />
        
        <input type="radio" name="method" value="and"<?php echo isset($_GET['method']) && $_GET['method'] === "and" || !isset($_GET['method']) ? " checked" : ""; ?>><?php echo $lang['and_search']; ?></input>
        <input type="radio" name="method" value="or"<?php echo isset($_GET['method']) && $_GET['method'] === "or" ? " checked" : ""; ?>><?php echo $lang['or_search']; ?></input>
        <br />
        <input type="checkbox" id="q_title" name="title" value="1" <?php echo isset($_GET['title']) && $_GET['title'] === "1" || !isset($_GET['q']) ? "checked " : ""; ?>/>
        <label for="q_title"><?php echo $lang['title_checkbox']; ?></label>
        <input type="checkbox" id="q_tag" name="tag" value="1" <?php echo isset($_GET['tag']) && $_GET['tag'] === "1" ? "checked " : ""; ?>/>
        <label for="q_tag"><?php echo $lang['tag_checkbox']; ?></label>
        <input type="checkbox" id="q_expl" name="expl" value="1" <?php echo isset($_GET['expl']) && $_GET['expl'] === "1" ? "checked " : ""; ?>/>
        <label for="q_expl"><?php echo $lang['overview_checkbox']; ?></label>
        <input type="checkbox" id="q_author" name="author" value="1" <?php echo isset($_GET['author']) && $_GET['author'] === "1" ? "checked " : ""; ?>/>
        <label for="q_author"><?php echo $lang['author_checkbox']; ?></label>

        |

        <input type="checkbox" id="q_unlisted" name="status" value="unlisted" <?php echo isset($_GET['status']) && $_GET['status'] === "unlisted" ? "checked " : ""; ?>/>
        <label for="q_unlisted"><?php echo $lang['unlisted_checkbox']; ?></label>
  
        |

        <input type="radio" name="t" value="all"<?php echo isset($_GET['t']) && $_GET['t'] === "all" || !isset($_GET['t']) ? " checked" : ""; ?>><?php echo $lang['all_radio']; ?></input>
        <input type="radio" name="t" value="vps"<?php echo isset($_GET['t']) && $_GET['t'] === "vps" ? " checked" : ""; ?>><?php echo $lang['vps_radio']; ?></input>
        <input type="radio" name="t" value="material"<?php echo isset($_GET['t']) && $_GET['t'] === "material" ? " checked" : ""; ?>><?php echo $lang['material_radio']; ?></input>
    </form>
    <br />
    <?php

    $video_contents_html = '';
      
    $index = [];
    $c = $view_c = 0;
    if (file_exists("../data/index.json")) {
        $index = json_decode(file_get_contents("../data/index.json"), true);
    }

    $page = 1;
    if (isset($_GET['page']))
        $page = (int) $_GET['page'];

    $words = [];
    if (!empty($_GET['q']))
        $words = explode(" ", $_GET['q']);

    // publishedAtがfalseでタイトルが"Deleted video"の場合は削除された動画として表示しない
    foreach ($index as $id => $data) {
        if ($data['publishedAt'] === false && 
            ($data['title'] === "Deleted video" || $data['title'] === "Private video")
        ) {
            unset($index[$id]);
        }
    }

    // 並び替え
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
    if ($sort != 'recent') {
        $sort_array = [];
        foreach ($index as $id => $data) {
            if (!isset($data['like'])) $index[$id]['like'] = 0;
            $sort_array[$id] = $data['publishedAt'];
        }
        if ($sort === 'ancient') {
            array_multisort($sort_array, SORT_ASC, $index);
        } else if ($sort === 'most_view') {
            array_multisort(array_column($index, 'view'), SORT_DESC, $index);
        } else if ($sort === 'worst_view') {
            array_multisort(array_column($index, 'view'), SORT_ASC, $index);
        } else if ($sort === 'most_like') {
            array_multisort(array_column($index, 'like'), SORT_DESC, $index);
        } else if ($sort === 'word') {
            array_multisort(array_column($index, 'title'), SORT_ASC, $index);
        } else if ($sort === 'word_desc') {
            array_multisort(array_column($index, 'title'), SORT_DESC, $index);
        }
    }

    foreach ($index as $id => $data) {
        $continue = false;
        foreach ($words as $word) {
            if (!isset($_GET['method']) || $_GET['method'] === "and")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] === "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] === "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] === "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word))) 
                    && (!isset($_GET['tag']) || !$_GET['tag'] === "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    )
                {
                    $continue = true;
                }
            if (isset($_GET['method']) && $_GET['method'] === "or")
                if (
                       (!isset($_GET['title']) || !$_GET['title'] === "1" || false === strpos(mb_strtolower(mb_convert_kana(kan2num($data['title']), "Hc")), mb_strtolower(mb_convert_kana(kan2num($word), "Hc"))))
                    && (!isset($_GET['expl']) || !$_GET['expl'] === "1" || false === strpos(mb_strtolower($data['description']), mb_strtolower($word)))
                    && (!isset($_GET['author']) || !$_GET['author'] === "1" || false === strpos(mb_strtolower($data['channelTitle']), mb_strtolower($word)))
                    && (!isset($_GET['tag']) || !$_GET['tag'] === "1" || false === strpos(mb_strtolower(implode(",", $data['tags'])), mb_strtolower($word)))
                    ) 
                {
                    if ($continue === false) $continue = 0;
                    ++$continue;
                }
        }

        if (isset($_GET['t']) && $_GET['t'] !== "all") {
            if ($_GET['t'] != $data['type']) continue;
        }
        if (isset($_GET['status']) && $_GET['status'] !== "public") {
            if (!isset($data['status'])) continue;
            if ($_GET['status'] != $data['status']) continue;
        }
        if ($continue === true || count($words) > 0 && $continue >= count($words)) continue;
        
        ++$c;
        if ($c < $page) continue;

        if ($view_c >= MAX_VIEW) break;
        ++$view_c;

        $description = mb_substr($data['description'], 0, 100, "UTF-8");
        if (mb_strlen($data['description']) > 100) {
            $description .= "...";
        }

        // HTMLエスケープ
        $data['title'] = htmlspecialchars($data['title'], ENT_QUOTES);
        $data['channelTitle'] = htmlspecialchars($data['channelTitle'], ENT_QUOTES);
        $data['description'] = htmlspecialchars($data['description'], ENT_QUOTES);

        $ago = "";
        if (isset($data['publishedAt']))
            $ago = time_elapsed_string($data['publishedAt']);

        $view_str = number_format($data['view']);
        if (isset($data['is_nicovideo']) && $data['is_nicovideo'] === true) {
          // ニコニコ
        $video_contents_html .= <<<EOD
        <div class="matrix_box">
            <span id="content_{$id}" style="margin-right:8px;">
                <a href="javascript:onClickThumbNC('{$id}');"><img id="{$id}" loading="lazy" data-src="./cache/thumb/{$id}.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </span>
            <br />
            <span>
                <span style="font-size:15px;"><a target="_blank" class="plain" href="https://www.nicovideo.jp/watch/{$id}">{$data['title']}</a></span><br />
                <span style="font-size:12px;"><a class="plain" href="https://www.nicovideo.jp/user/{$data['channelId']}">{$data['channelTitle']}</a></span>
                <span style="font-size:12px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:12px;"><a href="../?report&id={$id}&is_nicovideo=true">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://www.nicovideo.jp/watch/{$id}');">URL{$lang['copy']}</a></span>
            </span>
        </div>
        EOD;
          
        } else {
          // youtube
        $video_contents_html .= <<<EOD
        <div class="matrix_box">
            <span id="content_{$id}" style="margin-right:8px;">
                <a href="javascript:onClickThumb('{$id}');"><img id="{$id}" loading="lazy" data-src="https://i.ytimg.com/vi/{$id}/hqdefault.jpg" width="320px" height="180px" style="width:320px;height:180px;object-fit:cover;" /></a>
            </span>
            <br />
            <span>
                <span style="font-size:15px;"><a target="_blank" class="plain" href="https://youtu.be/{$id}">{$data['title']}</a></span><br />
                <span style="font-size:12px;"><a class="plain" href="https://www.youtube.com/channel/{$data['channelId']}">{$data['channelTitle']}</a></span>
                <span style="font-size:12px;">{$view_str} {$lang['view']}・{$ago}</span>
                <br />
                <span style="font-size:12px;"><a href="../?report&id={$id}">{$lang['report']}</a> |
                <a href="javascript:navigator.clipboard.writeText('https://youtu.be/{$id}');">URL{$lang['copy']}</a></span>
            </span>
        </div>
        EOD;
        }
    }
  
    $page_switch_html = '';

    $page_switch_html .= '<div style="clear:both;">';
    if ($page !== 1) {
        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort
        ];
        $param_array['page'] = $page - 50;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    }
        //$page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($page - 50) . '"><strong>' . $lang['prev'] . '</strong></a> | ';
    $move_n = round(9 / 2);
    $page_count = 0;

    if (file_exists("../data/index.json"))
        $page_count = count(json_decode(file_get_contents("../data/index.json"), true));

    $num_op_tag = '';
    $c = 1;
    while ($c <= 9) {
        $n = round(($page + MAX_VIEW) / MAX_VIEW);
        $disp_num = $c;
        if ($n > $move_n) $disp_num = $c + $n - $move_n;
        $f = $disp_num * MAX_VIEW - MAX_VIEW + 1;
        if ($f > $page_count) break;

        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort
        ];
        $param_array['page'] = $f;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        $num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="' . make_param($param_array) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';

        //$num_op_tag .= '<' . ($f == $page ? 'span' : 'a' ) . ' href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($f) . '"><strong>' . $disp_num . '</strong></' . ($f == $page ? 'span' : 'a' ) . '> | ';
        if ($f === $page && $view_c < MAX_VIEW) break;
        ++$c;
    }
    $page_switch_html .= $num_op_tag;
    if ($view_c >= MAX_VIEW) {
        $param_array = [
            'q' => $q,
            'method' => $method,
            'title' => $title,
            'expl' => $expl,
            'author' => $author,
            't' => $t,
            'sort' => $sort
        ];
        $param_array['page'] = $page + 50;
        if (isset($_GET['status'])) $param_array['status'] = $_GET['status'];
        $page_switch_html .= '<a href="' . make_param($param_array) . '"><strong>' . $lang['next'] . '</strong></a>';
        //$page_switch_html .= '<a href="?q=' . $q . '&method=' . $method . '&title=' . $title . '&expl=' . $expl . '&author=' . $author . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . '&t=' . $t . '&page=' . ($page + 50) . '"><strong>' . $lang['next'] . '</strong></a>';
    } else {
      $page_switch_html .= '<strong>...</strong>';
    }
    $page_switch_html .= '</div>';

    echo $page_switch_html . "\n<hr />" . "<div class=\"matrix\">\n" . $video_contents_html . "\n</div>\n<br />\n<div style=\"clear:both;\"><hr /></div>\n" , $page_switch_html;

?>
<br />
<span style="font-size:12px;">Languages: <a href="./" >日本語</a>, <a href="./en.php" >English</a>, <a href="./zh.php" >中国语</a>, <a href="./ko.php" >한국인</a><br /><br />
※当サイトのデータは再生リストから取得したものです。<br />
ソース: <a href="https://github.com/PTOM76/VPS-searcher">Gitリポジトリ</a><br />
Copyright 2023-2024 © Pitan.</span>
</div>
</body>
</html>
<?php
