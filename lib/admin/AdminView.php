<?php
// lib/admin/AdminView.php

/**
 * 管理画面の表示部品
 */
class AdminView {
    public static function e(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES);
    }

    /** POST フォームに必ず入れる CSRF トークン */
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf" value="' . self::e(AdminAuth::csrfToken()) . '">';
    }

    /**
     * 1操作だけのボタン (フォーム1つ)
     *
     * @param string $action AdminActions::handle() の action
     * @param string $target 対象 (ファイル名・動画ID・ユーザーID)
     * @param string $label ボタンの文言
     * @param string|null $confirm 押す前に確かめる文言。null なら確かめない
     */
    public static function button(string $action, string $target, string $label, ?string $confirm = null): string {
        $onsubmit = $confirm === null ? '' : ' onsubmit="return confirm(' . self::e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"';
        return '<form method="POST" style="display:inline"' . $onsubmit . '>'
            . self::csrfField()
            . '<input type="hidden" name="action" value="' . self::e($action) . '">'
            . '<input type="hidden" name="target" value="' . self::e($target) . '">'
            . '<input type="submit" value="' . self::e($label) . '">'
            . '</form>';
    }

    /** 動画IDから視聴ページのURL。ニコニコ動画のIDは sm / so / nm で始まる */
    public static function videoUrl(string $videoId): string {
        if (preg_match('/^(sm|so|nm)\d+$/', $videoId)) return 'https://www.nicovideo.jp/watch/' . $videoId;
        return 'https://www.youtube.com/watch?v=' . $videoId;
    }
}
