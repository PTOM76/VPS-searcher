<?php
// page/post_admin.php
?>
<form action="./" method="POST">
    <input type="text" name ="url" />
    <input type="hidden" name="do" value="post_<?php echo getenv('PASS'); ?>" placeholder="Playlist URL" />
    <input type="radio" name="t" value="vps"><?php echo $lang['vps_radio']; ?></input>
    <input type="radio" name="t" value="material"><?php echo $lang['material_radio']; ?></input>
    <input type="submit" />
</form>
