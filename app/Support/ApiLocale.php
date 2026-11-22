<?php

namespace App\Support;

/**
 * Lightweight, request-scoped locale helper for the mobile API.
 *
 * Supported locales : en (English), ms (Bahasa Malaysia), zh (Chinese).
 * Aliases          : bm → ms, zh-cn / zh-tw → zh, ms-my → ms, etc.
 * Middleware        : App\Http\Middleware\SetApiLocale calls set() on every request.
 */
class ApiLocale
{
    private static string $locale = 'en';

    private static array $allowed = ['en', 'ms', 'zh'];

    private static array $aliases = [
        'bm' => 'ms',
        'ms-my' => 'ms',
        'zh-cn' => 'zh',
        'zh-tw' => 'zh',
        'zh-hans' => 'zh',
        'zh-hant' => 'zh',
        'zh-sg' => 'zh',
    ];

    public static function set(string $locale): void
    {
        $locale = strtolower(trim($locale));
        $locale = self::$aliases[$locale] ?? $locale;
        self::$locale = in_array($locale, self::$allowed, true) ? $locale : 'en';
    }

    public static function get(): string
    {
        return self::$locale;
    }

    /**
     * Pick the correct translated string from an i18n array.
     * Falls back: requested locale → en → $fallback string.
     *
     * @param  array{en: string, ms: string, zh: string}|null  $i18n
     */
    public static function resolve(?array $i18n, string $fallback = ''): string
    {
        if (! is_array($i18n)) {
            return $fallback;
        }

        $value = $i18n[self::$locale] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $en = $i18n['en'] ?? null;
        if (is_string($en) && trim($en) !== '') {
            return $en;
        }

        return $fallback;
    }

    /**
     * Pick the correct translated string list from an i18n array.
     * Used for JSON array fields such as how_it_helps_i18n.
     * Falls back: requested locale → en → $fallback array.
     *
     * @param  array{en: string[], ms: string[], zh: string[]}|null  $i18n
     * @return string[]
     */
    public static function resolveList(?array $i18n, array $fallback = []): array
    {
        if (! is_array($i18n)) {
            return $fallback;
        }

        $value = $i18n[self::$locale] ?? null;
        if (is_array($value) && count($value) > 0) {
            return array_values($value);
        }

        $en = $i18n['en'] ?? null;
        if (is_array($en) && count($en) > 0) {
            return array_values($en);
        }

        return $fallback;
    }
}
