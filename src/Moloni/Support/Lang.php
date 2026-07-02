<?php

declare(strict_types=1);

namespace Moloni\Support;

/**
 * Minimal translation helper.
 *
 * Loads the appropriate language array from /lang and returns strings by key,
 * falling back to the key itself when a translation is missing. Language is
 * chosen from the WHMCS admin locale, defaulting to English.
 */
final class Lang
{
    /** @var array<string,string> */
    private static array $strings = [];

    private static bool $loaded = false;

    private static string $language = 'en';

    public static function boot(string $language = 'en'): void
    {
        $language = strtolower(substr($language, 0, 2)) === 'pt' ? 'pt' : 'en';

        self::$language = $language;
        self::$loaded = false;
        self::$strings = [];
    }

    public static function language(): string
    {
        return self::$language;
    }

    /**
     * Translate a key, optionally interpolating :placeholders.
     *
     * @param array<string,string|int> $replacements
     */
    public static function get(string $key, array $replacements = []): string
    {
        if (!self::$loaded) {
            self::load();
        }

        $value = self::$strings[$key] ?? $key;

        foreach ($replacements as $token => $replacement) {
            $value = str_replace(':' . $token, (string) $replacement, $value);
        }

        return $value;
    }

    private static function load(): void
    {
        $file = dirname(__DIR__, 3) . '/lang/' . self::$language . '.php';

        if (is_file($file)) {
            $strings = require $file;
            self::$strings = is_array($strings) ? $strings : [];
        }

        self::$loaded = true;
    }
}
