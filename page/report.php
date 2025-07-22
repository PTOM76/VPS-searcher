<?php
// page/report.php
$id = $_GET['id'];
echo <<<EOD
    <iframe width="560" height="315" src="https://www.youtube.com/embed/{$id}" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
    <p>報告内容を以下から選んでください。</p>
    <form action="./" method="POST">
    <input type="hidden" name="id" value="{$id}" />
    <input type="hidden" name="do" value="report" />
    <input type="radio" name="t" value="比較などの動画である、または関係のない動画である">比較などの動画である、または関係のない動画である</input><br />
        <input type="radio" name="t" value="動画が削除、非公開である">動画が削除、非公開である</input><br />
        <input type="radio" name="t" value="法律に反する動画である">法律に反する動画である</input><br />
        <input type="radio" name="t" value="種類(ボイパ対決/素材)が間違えている">種類(ボイパ対決/素材)が間違えている</input><br />
        <input type="radio" name="t" value="その他">その他</input><br />
        <textarea name="reason" cols="75" rows="5" placeholder="理由等 (「その他」を選択した場合)"></textarea><br />
        <input type="submit" />
    </form>
EOD;
