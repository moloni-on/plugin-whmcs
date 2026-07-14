<?php

declare(strict_types=1);

namespace MoloniOn\Support;

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
        self::$language = self::isPortuguese($language) ? 'pt' : 'en';
        self::$loaded = false;
        self::$strings = [];
    }

    /**
     * Whether a language identifier denotes Portuguese. Accepts ISO-ish codes
     * ("pt", "pt-PT"), WHMCS language names ("portuguese", "portuguese-pt",
     * "portuguese-br") and non-standard slugs seen on real installs
     * ("portugues", "português"). Matched on the "portug" stem so all spellings
     * resolve, with or without the trailing "e"/accent.
     */
    private static function isPortuguese(string $language): bool
    {
        $language = strtolower($language);

        return strncmp($language, 'pt', 2) === 0 || strpos($language, 'portug') !== false;
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
