<?php
// action/logout.php

// ログアウト処理
Auth::logout();
header('Location: ./');
exit;
?>
