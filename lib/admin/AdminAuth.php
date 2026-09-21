<?php
// lib/admin/AdminAuth.php

/**
 * 管理画面の入室判定と CSRF トークン
 */
class AdminAuth {
    /**
     * ChreeID でログインしていて、ChreeID 側で検証済みのメールが管理者のものか。
     * users.json のメールは本人が書き換えられるので見ない
     */
    public static function isAdmin(): bool {
        if (!Auth::isLoggedIn()) return false;
        return ($_SESSION['chreeid_email'] ?? '') === AdminConfig::EMAIL;
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['admin_csrf'];
    }

    public static function verifyCsrf(string $token): bool {
        return !empty($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $token);
    }
}
