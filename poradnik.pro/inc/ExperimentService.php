<?php

declare(strict_types=1);

namespace PoradnikPro;

final class ExperimentService
{
    public static function variant(string $experimentName, array $variants = ['A', 'B']): string
    {
        $cookieName = 'pp_exp_' . sanitize_key($experimentName);
        if (! empty($_COOKIE[$cookieName]) && in_array($_COOKIE[$cookieName], $variants, true)) {
            return (string) $_COOKIE[$cookieName];
        }

        $seed = (string) wp_get_session_token() . '|' . $experimentName;
        $hash = abs(crc32($seed));
        $index = $hash % max(1, count($variants));
        $variant = (string) ($variants[$index] ?? 'A');

        if (! headers_sent()) {
            setcookie($cookieName, $variant, [
                'expires' => time() + (DAY_IN_SECONDS * 30),
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $variant;
    }
}