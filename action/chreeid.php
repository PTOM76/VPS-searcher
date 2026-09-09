<?php
// action/chreeid.php
// ChreeIDの認可エンドポイントへリダイレクトする

require_once __DIR__ . '/../lib/ChreeIdAuth.php';

if (!ChreeIdAuth::isConfigured()) {
    header('Location: ./?do=login');
    exit;
}

// state は CSRF 対策、nonce は ID Token の取り違え対策。どちらも突き合わせるまでセッションに置く
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$codeVerifier = ChreeIdAuth::generateCodeVerifier();

$_SESSION['chreeid_state'] = $state;
$_SESSION['chreeid_nonce'] = $nonce;
$_SESSION['chreeid_code_verifier'] = $codeVerifier;

$chreeIdAuth = new ChreeIdAuth();

header('Location: ' . $chreeIdAuth->getAuthUrl($state, $nonce, $codeVerifier));
exit;
