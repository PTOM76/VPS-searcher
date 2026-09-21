<?php
// lib/admin/Blacklist.php

/**
 * blacklist.json (検索結果に出さない動画IDの一覧)
 */
class Blacklist {
    /**
     * @return string[]
     */
    public static function all(): array {
        if (!file_exists(FilePaths::BLACKLIST_JSON)) return [];
        return json_decode(file_get_contents(FilePaths::BLACKLIST_JSON), true) ?: [];
    }

    /**
     * 足したうえで、既に索引に入っている分も外す。
     * addPlaylist() は取り込み時に弾くだけなので、足しただけでは今出ている動画が残る
     *
     * @param string $videoId YouTube / ニコニコ動画の動画ID
     * @return bool 足せたか (形式が不正、または登録済みなら false)
     */
    public static function add(string $videoId): bool {
        if (!self::isValidId($videoId)) return false;

        $list = self::all();
        if (in_array($videoId, $list, true)) return false;

        $list[] = $videoId;
        self::save($list);
        self::removeFromIndex($videoId);
        return true;
    }

    /**
     * 外すだけ。索引には次回の更新で戻る
     *
     * @return bool 外せたか
     */
    public static function remove(string $videoId): bool {
        $list = self::all();
        $filtered = array_values(array_filter($list, function ($id) use ($videoId) { return $id !== $videoId; }));
        if (count($filtered) === count($list)) return false;

        self::save($filtered);
        return true;
    }

    public static function isValidId(string $videoId): bool {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $videoId) === 1;
    }

    private static function save(array $list): void {
        file_put_contents(FilePaths::BLACKLIST_JSON, json_encode($list, JSON_PRETTY_PRINT));
    }

    private static function removeFromIndex(string $videoId): void {
        if (!file_exists(FilePaths::INDEX_JSON)) return;

        $index = json_decode(file_get_contents(FilePaths::INDEX_JSON), true);
        if (!isset($index[$videoId])) return;

        unset($index[$videoId]);
        file_put_contents(FilePaths::INDEX_JSON, json_encode($index, JSON_UNESCAPED_UNICODE));
    }
}
