<?php
// lib/CompareQuery.php

/**
 * 動画比較の状態を表す URL パラメータ。
 *
 *   ?compare&v=ID~l~m~12.5,ID2&size=480&join=1&mode=start
 *
 * v の各要素は「動画ID~切り取り(l/r)~ミュート(m)~開始位置」で、後ろの既定値は省かれる。
 * 開始位置は mode=start (動画ごとに手で決める) の時だけ入る。書式は page/compare-url.js と揃えること。
 */
class CompareQuery {
    private const MAX_VIDEOS = 20;
    private const MAX_SECONDS = 3600;
    private const SIZES = ['320', '480', '640'];

    /**
     * 保存前に、受け取ったクエリ文字列を検査して組み立て直す (知らない値はここで落とす)
     *
     * @param string $query 先頭の ? を除いたクエリ文字列
     * @return string|null 動画が1本も無ければ null
     */
    public static function normalize(string $query): ?string {
        parse_str($query, $params);

        $videos = self::normalizeVideos((string)($params['v'] ?? ''));
        if ($videos === []) return null;

        $parts = ['compare', 'v=' . implode(',', $videos)];
        if (in_array($params['size'] ?? '', self::SIZES, true) && $params['size'] !== '320') $parts[] = 'size=' . $params['size'];
        if (($params['join'] ?? '') === '1') $parts[] = 'join=1';
        if (($params['mode'] ?? '') === 'start') $parts[] = 'mode=start';

        return implode('&', $parts);
    }

    /** @return int 何本の動画を並べた比較か */
    public static function countVideos(string $query): int {
        parse_str($query, $params);
        return count(self::normalizeVideos((string)($params['v'] ?? '')));
    }

    /**
     * @return string[] 正しい要素だけを、書式を整えて返す
     */
    private static function normalizeVideos(string $value): array {
        $videos = [];
        foreach (array_slice(explode(',', $value), 0, self::MAX_VIDEOS) as $item) {
            $video = self::normalizeVideo($item);
            if ($video !== null) $videos[] = $video;
        }
        return $videos;
    }

    private static function normalizeVideo(string $item): ?string {
        $fields = array_pad(explode('~', $item), 4, '');
        [$id, $crop, $mute, $start] = $fields;
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) return null;

        $out = [
            $id,
            in_array($crop, ['l', 'r'], true) ? $crop : '',
            $mute === 'm' ? 'm' : '',
            is_numeric($start) && abs((float)$start) <= self::MAX_SECONDS ? (string)round((float)$start, 1) : '',
        ];
        // 後ろの既定値は省く (URL を短くするため。compare-url.js と同じ規則)
        while (count($out) > 1 && end($out) === '') array_pop($out);
        return implode('~', $out);
    }
}
