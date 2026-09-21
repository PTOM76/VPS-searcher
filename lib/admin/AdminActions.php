<?php
// lib/admin/AdminActions.php

require_once __DIR__ . '/TextRecordDirectory.php';
require_once __DIR__ . '/Blacklist.php';

/**
 * 管理画面の POST 操作。どれも結果の文言 (管理画面の言語キーで引いたもの) を返す
 */
class AdminActions {
    /** @var array<string, string> 管理画面の文言 */
    private $text;

    /**
     * @param array<string, string> $text 管理画面の文言
     */
    public function __construct(array $text) {
        $this->text = $text;
    }

    public static function reports(): TextRecordDirectory {
        return new TextRecordDirectory(FilePaths::REPORT_DIR, '/^[A-Za-z0-9_-]+-\d+\.txt$/');
    }

    public static function queue(): TextRecordDirectory {
        // キューのファイル名は投稿された URL から作られているので、区切り文字だけ弾いて広めに受ける
        return new TextRecordDirectory(FilePaths::QUEUE_DIR, '#^(pl|nc|yt)_[^/\\\\]+\.txt$#');
    }

    /**
     * @param array<string, string> $post $_POST
     * @return string 結果の文言
     */
    public function handle(array $post): string {
        $target = (string)($post['target'] ?? '');

        switch ($post['action'] ?? '') {
            case 'report_done': return $this->result(self::reports()->delete($target), 'report_done');
            case 'report_blacklist': return $this->blacklistReport($target);
            case 'blacklist_add': return $this->result(Blacklist::add(trim($target)), 'blacklist_added');
            case 'blacklist_remove': return $this->result(Blacklist::remove($target), 'blacklist_removed');
            case 'data_update': return $this->refresh(false);
            case 'data_full_update': return $this->refresh(true);
            case 'data_add': return $this->addEntry(trim($target), (string)($post['type'] ?? ''));
            case 'queue_approve': return $this->approveQueue($target);
            case 'queue_reject': return $this->result(self::queue()->delete($target), 'queue_rejected');
            case 'user_delete': return $this->result(Auth::adminDeleteUser($target), 'user_deleted');
        }
        return $this->text['failed'];
    }

    private function result(bool $ok, string $key): string {
        return $ok ? $this->text[$key] : $this->text['failed'];
    }

    /** 通報された動画を非表示にし、その通報は対応済みとして消す */
    private function blacklistReport(string $file): string {
        $reports = self::reports();
        $report = $reports->find($file);
        if ($report === null) return $this->text['failed'];

        Blacklist::add(trim($report['id'] ?? ''));
        $reports->delete($file);
        return $this->text['blacklist_added'];
    }

    /** YouTube API を何度も叩くので、既定の実行時間では途中で切れる */
    private function refresh(bool $full): string {
        set_time_limit(0);
        refreshPlaylists($full);
        return $this->text['data_updated'];
    }

    private function addEntry(string $url, string $type): string {
        if ($url === '' || !in_array($type, ['vps', 'material'], true)) return $this->text['failed'];

        set_time_limit(0);
        addAdminEntry($url, $type);
        return $this->text['data_added'];
    }

    /** 投稿キューの1件を登録して、キューからは消す */
    private function approveQueue(string $file): string {
        $queue = self::queue();
        $item = $queue->find($file);
        if ($item === null) return $this->text['failed'];

        $message = $this->addEntry(trim($item['url'] ?? ''), trim($item['type'] ?? ''));
        if ($message === $this->text['data_added']) $queue->delete($file);
        return $message;
    }
}
