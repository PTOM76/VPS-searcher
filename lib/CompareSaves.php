<?php
// lib/CompareSaves.php

require_once __DIR__ . '/JsonFileStore.php';
require_once __DIR__ . '/CompareQuery.php';

/**
 * ユーザーごとに保存した動画比較。中身は比較ページの URL パラメータ (CompareQuery) そのもの
 */
class CompareSaves {
    private const FILE = __DIR__ . '/../data/compare_saves.json';
    private const MAX_PER_USER = 100;
    private const MAX_NAME_LENGTH = 100;

    /**
     * 新しい順
     *
     * @return array<int, array{id: string, name: string, query: string, created_at: string}>
     */
    public static function listFor(string $userId): array {
        $rows = self::store()->read()[$userId] ?? [];
        return array_reverse($rows);
    }

    /**
     * @param array $list listFor() の結果
     * @return string 見つからなければ空文字
     */
    public static function nameOf(array $list, string $id): string {
        foreach ($list as $row) {
            if ($row['id'] === $id) return $row['name'];
        }
        return '';
    }

    /**
     * @param string $query 先頭の ? を除いた比較ページのクエリ文字列
     * @return string|null 保存した比較のID。名前が空・動画が無い・上限に達している時は null
     */
    public static function add(string $userId, string $name, string $query): ?string {
        $name = self::normalizeName($name);
        $query = CompareQuery::normalize($query);
        if ($name === '' || $query === null) return null;

        $row = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'query' => $query, 'created_at' => date('Y-m-d H:i:s')];
        $added = false;
        $saved = self::store()->update(function (array $rows) use ($userId, $row, &$added) {
            if (count($rows[$userId] ?? []) >= self::MAX_PER_USER) return $rows;
            $rows[$userId][] = $row;
            $added = true;
            return $rows;
        });
        return $saved && $added ? $row['id'] : null;
    }

    /**
     * 保存済みの比較を、今の比較で上書きする
     *
     * @param string $query 先頭の ? を除いた比較ページのクエリ文字列
     */
    public static function overwrite(string $userId, string $id, string $name, string $query): bool {
        $name = self::normalizeName($name);
        $query = CompareQuery::normalize($query);
        if ($name === '' || $query === null) return false;

        return self::change($userId, $id, function (array $row) use ($name, $query) {
            $row['name'] = $name;
            $row['query'] = $query;
            $row['updated_at'] = date('Y-m-d H:i:s');
            return $row;
        });
    }

    public static function rename(string $userId, string $id, string $name): bool {
        $name = self::normalizeName($name);
        if ($name === '') return false;

        return self::change($userId, $id, function (array $row) use ($name) {
            $row['name'] = $name;
            return $row;
        });
    }

    public static function delete(string $userId, string $id): bool {
        return self::change($userId, $id, function () { return null; });
    }

    /** アカウント削除時に、そのユーザーの保存分をまとめて消す */
    public static function deleteAllFor(string $userId): void {
        self::store()->update(function (array $rows) use ($userId) {
            unset($rows[$userId]);
            return $rows;
        });
    }

    /**
     * 自分の保存分の中から id の1件を差し替える
     *
     * @param callable $change 元の行を受け取り、新しい行 (null なら削除) を返す
     */
    private static function change(string $userId, string $id, callable $change): bool {
        $found = false;
        $saved = self::store()->update(function (array $rows) use ($userId, $id, $change, &$found) {
            foreach ($rows[$userId] ?? [] as $i => $row) {
                if ($row['id'] !== $id) continue;
                $found = true;
                $new = $change($row);
                if ($new === null) unset($rows[$userId][$i]);
                else $rows[$userId][$i] = $new;
            }
            if (isset($rows[$userId])) $rows[$userId] = array_values($rows[$userId]);
            return $rows;
        });
        return $saved && $found;
    }

    private static function normalizeName(string $name): string {
        return mb_substr(trim($name), 0, self::MAX_NAME_LENGTH);
    }

    private static function store(): JsonFileStore {
        return new JsonFileStore(self::FILE);
    }
}
