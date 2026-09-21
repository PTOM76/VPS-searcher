<?php
// lib/admin/TextRecordDirectory.php

/**
 * 「Key: value」の行が並んだテキストを1件1ファイルで置いているディレクトリ。
 * 通報 (report/) と投稿キュー (queue/) がこの形式。
 */
class TextRecordDirectory {
    /** @var string 末尾に / を付けたディレクトリ */
    private $dir;

    /** @var string 扱ってよいファイル名の正規表現。外から来た名前でディレクトリの外を触らせない */
    private $namePattern;

    /**
     * @param string $dir 末尾に / を付けたディレクトリ
     * @param string $namePattern 扱ってよいファイル名の正規表現
     */
    public function __construct(string $dir, string $namePattern) {
        $this->dir = $dir;
        $this->namePattern = $namePattern;
    }

    /**
     * 新しい順に全件
     *
     * @return array<int, array<string, string>> 各行の Key を小文字にした連想配列。file と mtime を足す
     */
    public function all(): array {
        $records = [];
        foreach (glob($this->dir . '*.txt') ?: [] as $path) {
            $name = basename($path);
            if (!preg_match($this->namePattern, $name)) continue;
            $records[] = ['file' => $name, 'mtime' => (string)filemtime($path)] + $this->parse(file_get_contents($path));
        }

        usort($records, function ($a, $b) { return (int)$b['mtime'] - (int)$a['mtime']; });
        return $records;
    }

    /**
     * @param string $name ファイル名
     * @return array<string, string>|null 無ければ null
     */
    public function find(string $name): ?array {
        if (!$this->isValidName($name)) return null;
        return ['file' => $name] + $this->parse(file_get_contents($this->dir . $name));
    }

    /**
     * @param string $name ファイル名
     * @return bool 消せたか
     */
    public function delete(string $name): bool {
        if (!$this->isValidName($name)) return false;
        return unlink($this->dir . $name);
    }

    private function isValidName(string $name): bool {
        return preg_match($this->namePattern, $name) === 1 && is_file($this->dir . $name);
    }

    /**
     * 最後のキー (Reason 等) は改行を含みうるので、次のキーが来るまで前の値に継ぎ足す
     *
     * @return array<string, string>
     */
    private function parse(string $text): array {
        $fields = [];
        $key = null;
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match('/^([A-Za-z]+): ?(.*)$/', $line, $m)) {
                $key = strtolower($m[1]);
                $fields[$key] = $m[2];
                continue;
            }
            if ($key !== null) $fields[$key] .= "\n" . $line;
        }
        return $fields;
    }
}
