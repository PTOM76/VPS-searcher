<?php
/**
 * VPS-search - ChreeID Lazy Provisioning
 *
 * DokuFarm の core/auth/ChreeIdProvisioner.php と同じ設計。
 * ログインが成立した時点で ChreeID 側にアカウントを裏で用意し、
 * その sub を users.json の chree_id に持つ。本人は何も操作しない。
 *
 * パスワードは平文を送らない。このサイトも ChreeID も PHP の password_hash()
 * による bcrypt なので、保存済みのハッシュをそのまま渡せば向こうでも照合できる。
 */
class ChreeIdProvisioner {
    /** ChreeID 側の応答を待つ秒数。ログインを止めたくないので短くする */
    private const TIMEOUT_SECONDS = 5;

    /**
     * 遅延登録が使える設定か
     */
    public static function isEnabled() {
        return defined('CHREEID_ISSUER') && defined('CHREEID_CLIENT_ID') && defined('CHREEID_CLIENT_SECRET')
            && CHREEID_CLIENT_ID !== '' && CHREEID_CLIENT_SECRET !== '';
    }

    /**
     * ログイン成立時に呼ぶ。まだ ChreeID を持たないユーザーに用意する。
     *
     * 失敗しても例外を投げない。ChreeID が落ちていてもこのサイトのログインは
     * 通さなければならない。移行は次のログインでやり直せばよい。
     *
     * @param array $user users.json の1行 (参照ではなく値)
     * @return string|null 取得できた sub。何もしなかった場合は null
     */
    public function ensure(array $user) {
        if (!self::isEnabled()) return null;

        // 既に持っているなら触らない
        if (!empty($user['chree_id'])) return $user['chree_id'];

        try {
            $sub = $this->request($user);
        } catch (\Throwable $e) {
            error_log('chreeid.provision_failed: ' . $e->getMessage());
            return null;
        }

        if ($sub === null) return null;

        Auth::linkChreeId($user['id'], $sub);

        return $sub;
    }

    /**
     * 引き取り (claim) 画面への一度きりの URL を取りに行く。
     *
     * @param array $user users.json の1行
     * @return string|null 既に引き取り済みなら null
     * @throws \RuntimeException ChreeID に繋がらない、または断られた場合
     */
    public function claimUrl(array $user) {
        $sub = $this->ensure($user);
        if ($sub === null) {
            throw new \RuntimeException('ChreeID のアカウントを用意できませんでした');
        }

        [$status, $data] = $this->post('/api/v1/service-accounts/claim-tickets', [
            'client_id' => CHREEID_CLIENT_ID,
            'client_secret' => CHREEID_CLIENT_SECRET,
            'service_user_id' => (string)$user['id'],
        ]);

        if ($status === 409) return null;
        if ($status !== 200 || !is_string($data['claim_url'] ?? null)) {
            throw new \RuntimeException("ChreeID が {$status} を返しました");
        }

        return $data['claim_url'];
    }

    /**
     * アカウント削除時に呼ぶ。ChreeID 側は物理削除せず、ログインできない
     * 状態にするだけ。失敗しても例外を投げない (このサイトの削除は止めない)。
     *
     * @param array $user users.json の1行
     * @return void
     */
    public function deactivate(array $user) {
        if (!self::isEnabled()) return;

        try {
            $this->post('/api/v1/service-accounts/deactivate', [
                'client_id' => CHREEID_CLIENT_ID,
                'client_secret' => CHREEID_CLIENT_SECRET,
                'service_user_id' => (string)$user['id'],
            ]);
        } catch (\Throwable $e) {
            error_log('chreeid.deactivate_failed: ' . $e->getMessage());
        }
    }

    /**
     * ChreeID にアカウントを要求する
     *
     * @param array $user users.json の1行
     * @return string|null 受け取った sub
     */
    private function request(array $user) {
        $payload = [
            'client_id' => CHREEID_CLIENT_ID,
            'client_secret' => CHREEID_CLIENT_SECRET,
            'service_user_id' => (string)$user['id'],
            'display_name' => (string)($user['username'] ?? ''),
        ];

        // このサイトのメールは確認手段が無いので、未検証として渡す
        if (!empty($user['email'])) {
            $payload['email'] = $user['email'];
            $payload['email_verified'] = '0';
        }

        if (!empty($user['password'])) {
            $payload['password_hash'] = $user['password'];
        }

        [$status, $data] = $this->post('/api/v1/service-accounts', $payload);
        if ($status !== 200) {
            throw new \RuntimeException("ChreeID が {$status} を返しました");
        }

        return is_string($data['sub'] ?? null) ? $data['sub'] : null;
    }

    /**
     * フォーム形式で POST して JSON を受け取る
     *
     * @param string $path ChreeID 上のパス
     * @param array $params 送る値
     * @return array [0: int ステータス, 1: array 本文]
     */
    private function post($path, array $params) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim(CHREEID_ISSUER, '/') . $path,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("ChreeID に到達できませんでした: {$error}");
        }

        $data = json_decode((string)$response, true);

        return [(int)$httpCode, is_array($data) ? $data : []];
    }
}
