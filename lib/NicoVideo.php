<?php
// lib/NicoVideo.php

require_once __DIR__ . '/config.php';

/**
 * ニコニコ動画を1本取得して index.json に載せる
 */
class NicoVideo {
    /**
     * nicovideo.jp/watch/ / nico.ms / 動画IDそのもの から動画IDを取り出す。クエリ (?ref= 等) は捨てる
     *
     * @return string|null 取り出せなければ null
     */
    public static function parseId(string $url): ?string {
        if (preg_match('/^((?:sm|so|nm)\d+)$/', trim($url), $m)) return $m[1];
        if (preg_match('#(?:nicovideo\.jp/watch/|nico\.ms/)((?:sm|so|nm)\d+)#', $url, $m)) return $m[1];
        return null;
    }

    /** getUrlType() 用。ニコニコの URL / ID なら true */
    public static function matches(string $url): bool {
        return self::parseId($url) !== null;
    }

    /**
     * 動画を取得して index.json に追加・更新し、サムネイルも保存する
     *
     * @param string $type vps | material
     * @return bool 動画が存在して登録できたら true
     */
    public static function add(string $videoId, string $type): bool {
        $thumb = self::fetchThumb($videoId);
        if ($thumb === null) return false;

        $index = file_exists(FilePaths::INDEX_JSON) ? json_decode(file_get_contents(FilePaths::INDEX_JSON), true) : [];
        $index[$videoId] = self::toEntry($thumb, $type);
        array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        file_put_contents(FilePaths::INDEX_JSON, json_encode($index, JSON_UNESCAPED_UNICODE));

        $image = self::get((string)$thumb->thumbnail_url);
        FilePaths::ensureDirectoryExists(FilePaths::CACHE_THUMB_DIR);
        file_put_contents(FilePaths::CACHE_THUMB_DIR . $videoId . ".jpg", $image);
        return true;
    }

    private static function toEntry(SimpleXMLElement $thumb, string $type): array {
        $tags = [];
        foreach ($thumb->tags->tag ?? [] as $tag) $tags[] = (string)$tag;

        return [
            'is_nicovideo' => true,
            'title' => (string)$thumb->title,
            'description' => (string)$thumb->description,
            'channelId' => (string)($thumb->user_id ?? $thumb->ch_id),
            'channelTitle' => (string)($thumb->user_nickname ?? $thumb->ch_name),
            'publishedAt' => strtotime((string)$thumb->first_retrieve),
            'view' => (int)$thumb->view_counter,
            'tags' => $tags,
            'type' => $type,
        ];
    }

    /** @return SimpleXMLElement|null 削除済み・存在しない動画なら null */
    private static function fetchThumb(string $videoId): ?SimpleXMLElement {
        $xml = simplexml_load_string(self::get("https://ext.nicovideo.jp/api/getthumbinfo/" . $videoId));
        if ($xml === false || (string)$xml['status'] !== 'ok') return null;
        return $xml->thumb;
    }

    private static function get(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // User-Agent が無いと getthumbinfo が XML ではなく HTML を返す
        curl_setopt($ch, CURLOPT_USERAGENT, AppConstants::SITE_NAME);
        // 本番サーバは CA 証明書で検証が通らないため、addPlaylist() と同じく検証を切る
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new RuntimeException("niconico fetch failed: {$url} ({$error})");
        return $body;
    }
}
