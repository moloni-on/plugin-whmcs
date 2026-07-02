<?php

declare(strict_types=1);

namespace Moloni\Support;

/**
 * Per-request runtime context.
 *
 * Holds the authenticated session data (access token, selected company and
 * the loaded company payload) so the API client can inject them into every
 * request without threading them through every call.
 */
final class Context
{
    public static ?int $sessionId = null;

    public static string $accessToken = '';

    public static int $companyId = 0;

    /** @var array<string,mixed> */
    public static array $company = [];

    private function __construct()
    {
    }

    public static function reset(): void
    {
        self::$sessionId = null;
        self::$accessToken = '';
        self::$companyId = 0;
        self::$company = [];
    }

    public static function hasCompany(): bool
    {
        return self::$companyId > 0;
    }
}
