<?php
declare(strict_types=1);

namespace App\Lib;

class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        if (!$token || !isset($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }
}
