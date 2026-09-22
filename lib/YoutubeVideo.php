<?php
// lib/YoutubeVideo.php

require_once __DIR__ . '/config.php';

/**
 * YouTube 動画を1本だけ取得して index.json に載せる
 */
class YoutubeVideo {
    /**
     * watch?v= / youtu.be / shorts / live / 動画IDそのもの から動画IDを取り出す
     *
     * @return string|null 取り出せなければ null
     */
    public static function parseId(string $url): ?string {
        $url = trim($url);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) return $url;
        if (preg_match('#[?&]v=([A-Za-z0-9_-]{11})#', $url, $m)) return $m[1];
        if (preg_match('#(?:youtu\.be/|/shorts/|/live/|/embed/)([A-Za-z0-9_-]{11})#', $url, $m)) return $m[1];
        return null;
    }

    /**
     * 動画を取得して index.json に追加・更新する
     *
     * @param string $type vps | material
     * @return bool 動画が存在して登録できたら true
     */
    public static function add(string $videoId, string $type): bool {
        $video = self::fetch($videoId);
        if ($video === null || !isset($video->snippet->publishedAt)) return false;

        $index = file_exists(FilePaths::INDEX_JSON) ? json_decode(file_get_contents(FilePaths::INDEX_JSON), true) : [];
        $index[$videoId] = self::toEntry($video, $type);
        array_multisort(array_column($index, 'publishedAt'), SORT_DESC, $index);
        file_put_contents(FilePaths::INDEX_JSON, json_encode($index, JSON_UNESCAPED_UNICODE));
        return true;
    }

    /** index.json の1件と同じ形にする。再生リスト経由の addPlaylist() と揃えること */
    private static function toEntry(object $video, string $type): array {
        $snippet = $video->snippet;
        return [
            'title' => $snippet->title,
            'description' => $snippet->description,
            'channelId' => $snippet->channelId,
            'channelTitle' => $snippet->channelTitle,
            'publishedAt' => strtotime($snippet->publishedAt),
            'view' => $video->statistics->viewCount ?? 0,
            'like' => $video->statistics->likeCount ?? 0,
            'tags' => (array) ($snippet->tags ?? []),
            'type' => $type,
            'status' => $video->status->privacyStatus ?? 'public',
        ];
    }

    /** @return object|null API の items[0]。無ければ null */
    private static function fetch(string $videoId): ?object {
        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics,status&key=" . API_KEY . "&id=" . urlencode($videoId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 本番サーバは CA 証明書で検証が通らないため、addPlaylist() と同じく検証を切る
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new RuntimeException("YouTube API fetch failed: {$videoId} ({$error})");

        $json = json_decode($body);
        // quota 切れ等を「動画が無い」と区別して管理画面に出す
        if (isset($json->error)) throw new RuntimeException("YouTube API error: " . strip_tags((string)$json->error->message));
        return $json->items[0] ?? null;
    }
}
