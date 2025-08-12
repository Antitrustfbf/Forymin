<?php
declare(strict_types=1);

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || $token === null) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function read_post_string(string $key): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

function read_get_string(string $key): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
}

function truncate_text(string $text, int $width, string $ellipsis = "…"): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $width, $ellipsis, 'UTF-8');
    }
    // Fallback without mbstring
    if (strlen($text) <= $width) {
        return $text;
    }
    return substr($text, 0, max(0, $width - strlen($ellipsis))) . $ellipsis;
}