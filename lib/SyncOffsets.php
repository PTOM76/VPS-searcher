<?php
// lib/SyncOffsets.php

/**
 * 動画比較の基準位置 (動画ごとに Bad Apple!! の「ながれ」が始まる秒数)。
 * 全ユーザー共通の値で、ログインしていれば誰でも書き換えられる。
 */
class SyncOffsets {
    private const FILE = __DIR__ . '/../data/sync_offsets.json';

    /**
     * 前後1時間を超える位置は打ち間違いとみなす。
     * マイナスは「ながれ」が動画の始まりより前にある (動画が途中から始まっている) 場合
     */
    private const MAX_SECONDS = 3600;

    /**
     * @return array<string, array{offset: float, updated_by: string, updated_at: string}>
     */
    public static function all(): array {
        if (!file_exists(self::FILE)) return [];
        return json_decode(file_get_contents(self::FILE), true) ?: [];
    }

    /**
     * 画面に渡す用。動画ID => 秒数 だけにする (誰が変えたかは出さない)
     *
     * @return array<string, float>
     */
    public static function offsets(): array {
        return array_map(function ($row) { return (float)$row['offset']; }, self::all());
    }

    public static function isValid(string $videoId, float $offset): bool {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 && abs($offset) <= self::MAX_SECONDS;
    }

    /**
     * @param string $videoId YouTube の動画ID
     * @param float|null $offset 秒数。null なら消す
     * @param string $username 変更した人 (荒らされた時に追えるように残す)
     * @return bool 保存できたか
     */
    public static function set(string $videoId, ?float $offset, string $username): bool {
        if (!self::isValid($videoId, $offset ?? 0.0)) return false;

        return self::update(function (array $rows) use ($videoId, $offset, $username) {
            if ($offset === null) {
                unset($rows[$videoId]);
                return $rows;
            }
            $rows[$videoId] = ['offset' => round($offset, 1), 'updated_by' => $username, 'updated_at' => date('Y-m-d H:i:s')];
            return $rows;
        });
    }

    /**
     * 誰でも書けるので同時に保存されうる。読んでから書くまでをロックで囲む
     *
     * @param callable $change 今の内容を受け取り、新しい内容を返す
     * @return bool
     */
    private static function update(callable $change): bool {
        $handle = fopen(self::FILE, 'c+');
        if ($handle === false) return false;

        try {
            flock($handle, LOCK_EX);
            $rows = json_decode(stream_get_contents($handle), true) ?: [];
            $json = json_encode($change($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            ftruncate($handle, 0);
            rewind($handle);
            return fwrite($handle, $json) !== false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
