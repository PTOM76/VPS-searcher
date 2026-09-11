<?php
// action/chreeid_callback.php
// ChreeIDからの戻り。認可コードをID Tokenに交換してログインさせる

require_once __DIR__ . '/../lib/ChreeIdAuth.php';

/**
 * 失敗の理由を残してログイン画面へ戻す。
 *
 * 以前は5つの失敗を同じ ?chreeid_error=1 に潰していたので、
 * 何が起きているのか外からも中からも分からなかった。
 *
 * @param string $reason 何段目で落ちたか
 * @return void
 */
function chreeid_fail($reason) {
    // logs/ は相対パスで作られないことがあるので、確実に残る側にも出す。
    // この repo の ChreeIdProvisioner も error_log() を使っている
    error_log('chreeid.login_failed: ' . $reason);
    ErrorHandler::log('chreeid.login_failed: ' . $reason, 'WARNING');

    header('Location: ./?do=login&chreeid_error=1');
    exit;
}

// 一度きりの値なので、成否にかかわらずここで取り出して捨てる
$state = $_SESSION['chreeid_state'] ?? '';
$nonce = $_SESSION['chreeid_nonce'] ?? '';
$codeVerifier = $_SESSION['chreeid_code_verifier'] ?? '';
unset($_SESSION['chreeid_state'], $_SESSION['chreeid_nonce'], $_SESSION['chreeid_code_verifier']);

// ChreeID 側が拒否した。error_description に理由が入っている
if (isset($_GET['error'])) {
    chreeid_fail('provider_error: ' . $_GET['error'] . ' / ' . ($_GET['error_description'] ?? ''));
}

// セッションが切れているか、第三者に開始させられた
if ($state === '' || ($_GET['state'] ?? '') !== $state) {
    chreeid_fail($state === '' ? 'state_missing' : 'state_mismatch');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    chreeid_fail('code_missing');
}

$chreeIdAuth = new ChreeIdAuth();
$claims = $chreeIdAuth->exchange($code, $codeVerifier, $nonce);

// トークン交換か ID Token の検証で落ちた。理由は lastError に入る
if ($claims === null) {
    chreeid_fail($chreeIdAuth->lastError ?: 'exchange_failed');
}

$user = $chreeIdAuth->findOrCreateUser($claims);
// メールが無いなど、こちらのユーザーを用意できなかった
if ($user === null || !isset($user['id'])) {
    chreeid_fail('user_unavailable: sub=' . ($claims['sub'] ?? '?') . ' email=' . ($claims['email'] ?? '(none)'));
}

Auth::loginAsUser($user);

header('Location: ./');
exit;
