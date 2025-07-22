<?php
// page/post.php
?>
<p>ボイパ対決、素材の動画情報を当ツールへ追加するための再生リストを送信できます<br />再生リストに入っているものがボイパ対決・素材であるかこちらで審査します。<br />比較動画などに関しては今のところ採用しません。<br /><br />YouTubeの動画・再生リストのURLかニコニコ動画のURL</p>
    <form action="./" method="POST">
        <input type="text" name ="url" />
        <input type="hidden" name="do" value="post" placeholder="Playlist URL" />
        <input type="radio" name="t" value="vps"><?php echo $lang['vps_radio']; ?></input>
        <input type="radio" name="t" value="material"><?php echo $lang['material_radio']; ?></input>
        <input type="submit" />
    </form>
