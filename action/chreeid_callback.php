<?php
// action/chreeid_callback.php
// ChreeIDからの戻り。認可コードをID Tokenに交換してログインさせる

require_once __DIR__ . '/../lib/ChreeIdAuth.php';

// 一度きりの値なので、成否にかかわらずここで取り出して捨てる
$state = $_SESSION['chreeid_state'] ?? '';
$nonce = $_SESSION['chreeid_nonce'] ?? '';
$codeVerifier = $_SESSION['chreeid_code_verifier'] ?? '';
unset($_SESSION['chreeid_state'], $_SESSION['chreeid_nonce'], $_SESSION['chreeid_code_verifier']);

if (isset($_GET['error'])) {
    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

if ($state === '' || ($_GET['state'] ?? '') !== $state) {
    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

$chreeIdAuth = new ChreeIdAuth();
$claims = $chreeIdAuth->exchange($code, $codeVerifier, $nonce);

if ($claims === null) {
    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

$user = $chreeIdAuth->findOrCreateUser($claims);
if ($user === null || !isset($user['id'])) {
    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

Auth::loginAsUser($user);

header('Location: ./');
exit;
