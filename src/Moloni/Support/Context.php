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

    /** The selected company payload + permissions, loaded once per request. */
    public static ?Company $company = null;

    private function __construct()
    {
    }

    public static function reset(): void
    {
        self::$sessionId = null;
        self::$accessToken = '';
        self::$companyId = 0;
        self::$company = null;
    }

    /**
     * The loaded company (permissions/fiscal data), or null before selection.
     */
    public static function company(): ?Company
    {
        return self::$company;
    }

    public static function setCompany(array $company): void
    {
        self::$company = new Company($company);
    }

    public static function hasCompany(): bool
    {
        return self::$companyId > 0;
    }
}
