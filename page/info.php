<?php
// page/info.php
?>
<div id="badapple">
    <!-- メニューにはめ込む -->
    <img src="image/badapple.gif" style="width: 25%; text-align: center; opacity: 75%; position: absolute; top: 0; left: 50%; transform: translate(-50%, 0%);" />
    <script>
        var img = document.querySelector("#badapple img");
        img.onload = function() {
            setTimeout(function() {
                document.querySelector("#badapple").remove();
            }, 3100);
        }
    </script>

</div>

<h2>Information</h2>

<p id="next_birth">
    <?php

    $date = new DateTime("2014-05-05");
    $now = new DateTime();
    $interval = $date->diff($now);
    $year = $interval->y;
    $day = $interval->days;
    $next = $year + 1;
    $next_date = new DateTime("2014-05-05");
    $next_date->add(new DateInterval("P" . $next . "Y"));
    $next_interval = $now->diff($next_date);
    $next_day = $next_interval->days;
    echo sprintf($lang['next_birth'], $next, $next_day);
    ?>
</p>

<h3>Description</h3>
<p>
    このサイトはボイパ対決という音MADに特化した検索ツールです。<br />
    The site is a search tool specialized in sound MAD called "Vocal Percussion Showdown".<br />
    本サイトはYouTube APIを使用しています。<br />
    This site uses the YouTube API.<br />
    <br />
    サイト名: HIKAKINボイパ対決シリーズ専用検索ツール / Vocal Percussion Showdown Search Tool<br />
    サイトURL: <a href="https://vps-search.pitan76.net/">https://vps-search.pitan76.net/</a><br />
    開発者: Pitan<br />
    開発言語: PHP, JavaScript (HTML, CSS)<br />
    ソースコード: <a href="https://github.com/PTOM76/VPS-searcher" target="_blank">GitHub</a><br />
</p>

<h3>Related Links</h3>
<ul>
    <li><a href="https://dic.nicovideo.jp/a/hikakin%E3%83%9C%E3%82%A4%E3%83%91%E5%AF%BE%E6%B1%BA%E3%82%B7%E3%83%AA%E3%83%BC%E3%82%BA" target="_blank">HIKAKINボイパ対決シリーズとは - ニコニコ大百科</a></li>
    <li><a href="https://w.atwiki.jp/vpsseries/" target="_blank">HIKAKINボイパ対決シリーズWiki</a></li>
    <li><a href="https://w.atwiki.jp/voicepercussionhkkn/" target="_blank">HIKAKIN Wiki - Vocal Percussion Showdown</a></li>
    <li><a href="./ai/">AIによるタイトルラベリングツール</a></li>
</ul>

<h3>Data Count</h3>
<p>
    <?php
    $vps = $material = 0;

    if (file_exists("cache/info.json") && filemtime("cache/info.json") + 86400 > time()) {
        $info = json_decode(file_get_contents("cache/info.json"), true);
        $vps = $info['vps'];
        $material = $info['material'];
    } else {
        $index_data = json_decode(file_get_contents("data/index.json"), true);
        foreach ($index_data as $id => $data) {
            if ($data['type'] === "vps") ++$vps;
            if ($data['type'] === "material") ++$material;
        }
        file_put_contents("cache/info.json", json_encode(['vps' => $vps, 'material' => $material]));
    }

    echo $lang['total'] . ": " . number_format($vps + $material) . "<br />";
    
    echo $lang['vps_radio'] . ": " . number_format($vps) . "<br />";
    echo $lang['material_radio'] . ": " . number_format($material) . "<br />";
    ?>

</p>

<hr />
