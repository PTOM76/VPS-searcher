<?php
// lib/admin/SearchAnalytics.php

/**
 * handleAnalytics() が data/analytics/YYYY-MM-DD.txt に書いた検索ログを集計する
 */
class SearchAnalytics {
    /** @var array<int, array{date: string, uri: string, word: string}> 新しい順 */
    private $entries = [];

    /** @var array<string, int> 日付 => 検索回数 (空ワード含む) */
    private $daily = [];

    /**
     * @param int $days 直近何日分を読むか
     */
    public function __construct(int $days) {
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $this->loadDay($date);
        }
        $this->entries = array_reverse($this->entries);
    }

    private function loadDay(string $date): void {
        $file = FilePaths::ANALYTICS_DIR . $date . '.txt';
        $this->daily[$date] = 0;
        if (!file_exists($file)) return;

        // ローカルに持ってきたログは CRLF になっていることがある
        $text = str_replace("\r\n", "\n", file_get_contents($file));
        foreach (explode("----------------\n", $text) as $block) {
            if (!preg_match('/^DATE: (\S+)\nURI: (.*)\nWORD: (.*)$/m', $block, $m)) continue;
            $this->daily[$date]++;
            $this->entries[] = ['date' => str_replace('_', ' ', $m[1]), 'uri' => $m[2], 'word' => trim($m[3])];
        }
    }

    /**
     * 空ワード (検索条件だけ変えたもの) を除いた直近の検索
     *
     * @return array<int, array{date: string, uri: string, word: string}>
     */
    public function recent(int $limit): array {
        return array_slice(array_values(array_filter($this->entries, fn($e) => $e['word'] !== '')), 0, $limit);
    }

    /**
     * よく検索されたワード。大文字小文字と前後空白の違いはまとめる
     *
     * @return array<string, int> ワード => 回数 (多い順)
     */
    public function topWords(int $limit): array {
        $counts = [];
        foreach ($this->entries as $e) {
            if ($e['word'] === '') continue;
            $key = mb_strtolower($e['word']);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /** @return array<string, int> 日付 => 検索回数 (古い順) */
    public function daily(): array {
        return $this->daily;
    }

    public function total(): int {
        return array_sum($this->daily);
    }
}
