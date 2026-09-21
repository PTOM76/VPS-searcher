<?php
// lib/JsonFileStore.php

/**
 * 複数の人が同時に書きうる JSON ファイル。読んでから書くまでをロックで囲む
 */
class JsonFileStore {
    /** @var string */
    private $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    public function read(): array {
        if (!file_exists($this->path)) return [];
        return json_decode(file_get_contents($this->path), true) ?: [];
    }

    /**
     * @param callable $change 今の内容 (array) を受け取り、新しい内容 (array) を返す
     * @return bool 書けたか
     */
    public function update(callable $change): bool {
        $handle = fopen($this->path, 'c+');
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
