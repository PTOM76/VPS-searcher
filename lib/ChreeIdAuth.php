<?php
/**
 * VPS-search - ChreeID (OpenID Connect) ログイン
 *
 * DokuFarm の core/auth/ChreeIdAuth.php と同じ設計。
 * ChreeID を OP、このサイトを RP とした Authorization Code + PKCE のログイン。
 * 「ChreeIDでログイン」ボタンから、既にChreeIDアカウントを持っている
 * (=引き取り済みの) ユーザー本人がログインするための経路。
 */
class ChreeIdAuth {
    private $issuer;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct() {
        $this->issuer = rtrim(CHREEID_ISSUER, '/');
        $this->clientId = CHREEID_CLIENT_ID;
        $this->clientSecret = CHREEID_CLIENT_SECRET;
        $this->redirectUri = self::redirectUri();
    }

    /**
     * ChreeID ログインが使える設定になっているか
     */
    /** 直近の失敗理由。呼び出し側がログに残すために持つ */
    public $lastError = null;

    /**
     * ID Token を受け付けなかった理由を控えて null を返す
     */
    private function tokenRejected($reason) {
        $this->lastError = 'id_token_' . $reason;

        return null;
    }

    public static function isConfigured() {
        return defined('CHREEID_CLIENT_ID') && CHREEID_CLIENT_ID !== ''
            && defined('CHREEID_ISSUER') && CHREEID_ISSUER !== '';
    }

    /**
     * ChreeID 側に登録してあるリダイレクト先。完全一致で照合される
     */
    public static function redirectUri() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . '/?do=chreeid_callback';
    }

    /**
     * PKCE の code_verifier を作る (RFC 7636 は 43〜128 文字を許す)
     */
    public static function generateCodeVerifier() {
        return self::base64Url(random_bytes(64));
    }

    /**
     * code_verifier から code_challenge を作る (S256)
     */
    public static function codeChallenge($verifier) {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    /**
     * 利用者を飛ばす先の URL
     */
    public function getAuthUrl($state, $nonce, $codeVerifier) {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ];

        return $this->issuer . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * 認可コードを ID Token に交換し、中身のクレームを返す
     *
     * @return array|null 失敗したら null
     */
    public function exchange($code, $codeVerifier, $nonce) {
        $this->lastError = null;

        $response = $this->post($this->issuer . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code_verifier' => $codeVerifier,
        ]);

        // post() が理由を控えているので上書きしない
        if ($response === null) {
            $this->lastError = $this->lastError ?: 'token_endpoint_failed';
            return null;
        }

        if (!isset($response['id_token']) || !is_string($response['id_token'])) {
            // ChreeID は OAuth の形でエラーを返す
            $this->lastError = 'token_endpoint_error: ' . ($response['error'] ?? 'no_id_token')
                . ' / ' . ($response['error_description'] ?? '');
            return null;
        }

        return $this->readIdToken($response['id_token'], $nonce);
    }

    /**
     * ID Token の中身を読む。
     *
     * 署名の検証は省く。トークンエンドポイントから TLS で直接受け取っており、
     * client_secret による認証も済んでいるため (OIDC Core 3.1.3.7)。
     * ただし iss / aud / exp / nonce は必ず確かめる。
     *
     * @return array|null 検証に通らなければ null
     */
    private function readIdToken($idToken, $nonce) {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) return $this->tokenRejected('malformed');

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) return $this->tokenRejected('undecodable');

        $claims = json_decode($payload, true);
        if (!is_array($claims)) return $this->tokenRejected('not_json');

        if (($claims['iss'] ?? null) !== $this->issuer) return $this->tokenRejected('iss_mismatch');
        if (($claims['aud'] ?? null) !== $this->clientId) return $this->tokenRejected('aud_mismatch');
        if (($claims['nonce'] ?? null) !== $nonce) return $this->tokenRejected('nonce_mismatch');

        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) || $exp < time()) return $this->tokenRejected('expired');

        if (!isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') return $this->tokenRejected('no_sub');

        return $claims;
    }

    /**
     * ChreeID のクレームからユーザーを引き当てる (無ければ作る)。
     *
     * @return array|null
     */
    public function findOrCreateUser(array $claims) {
        $chreeId = $claims['sub'];
        $email = $claims['email'] ?? null;
        $displayName = $claims['name'] ?? null;

        $user = Auth::findByChreeId($chreeId);
        if ($user !== null) return $user;

        // メールアドレスを手がかりにした遅延移行。
        // ChreeID 側で検証済みのアドレスでなければ、他人のアカウントを乗っ取れてしまう
        if (is_string($email) && $email !== '' && ($claims['email_verified'] ?? false) === true) {
            $user = Auth::findByEmail($email);
            if ($user !== null) {
                Auth::linkChreeId($user['id'], $chreeId);
                $user['chree_id'] = $chreeId;

                return $user;
            }
        }

        if (!is_string($email) || $email === '') return null;

        return Auth::createFromChreeId($chreeId, $email, is_string($displayName) ? $displayName : null);
    }

    /**
     * フォーム形式で POST して JSON を受け取る
     *
     * @return array|null
     */
    private function post($url, array $params) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = 'unreachable';
            return null;
        }

        $data = json_decode((string)$response, true);

        // 非200でも本文は捨てない。ChreeID は OAuth の形で理由を返す
        if ($httpCode !== 200) {
            $this->lastError = 'http_' . $httpCode . ': '
                . (is_array($data) ? (($data['error'] ?? '') . ' / ' . ($data['error_description'] ?? '')) : '');
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private static function base64Url($bytes) {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
