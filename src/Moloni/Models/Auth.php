<?php

declare(strict_types=1);

namespace Moloni\Models;

/**
 * Single-row OAuth2 session store (mod_moloni_on_auth).
 *
 * Holds the developer credentials, the current access/refresh tokens and the
 * selected company id. There is at most one row.
 */
class Auth extends AbstractModel
{
    public static function table(): string
    {
        return 'mod_moloni_on_auth';
    }

    /**
     * The current auth row as an associative array, or null if none exists.
     *
     * @return array<string,mixed>|null
     */
    public static function row(): ?array
    {
        $row = self::query()->first();

        return $row ? (array) $row : null;
    }

    /**
     * Persist the developer credentials, resetting any existing session.
     */
    public static function setClient(string $clientId, string $clientSecret): void
    {
        self::query()->truncate();

        self::create([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_token' => '',
            'refresh_token' => '',
            'access_expire' => 0,
            'refresh_expire' => 0,
            'company_id' => 0,
        ]);
    }

    /**
     * Store freshly issued tokens and their expiry timestamps.
     */
    public static function setTokens(string $accessToken, string $refreshToken): void
    {
        $row = self::row();

        if ($row === null) {
            return;
        }

        self::query()->where('id', $row['id'])->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            // Access tokens last ~50 min; refresh tokens ~10 days.
            'access_expire' => time() + 3000,
            'refresh_expire' => time() + 864000,
        ]);
    }

    public static function setCompany(int $companyId): void
    {
        $row = self::row();

        if ($row === null) {
            return;
        }

        self::query()->where('id', $row['id'])->update(['company_id' => $companyId]);
    }

    /**
     * Remove tokens but keep the developer credentials (soft logout).
     */
    public static function clearTokens(): void
    {
        $row = self::row();

        if ($row === null) {
            return;
        }

        self::query()->where('id', $row['id'])->update([
            'access_token' => '',
            'refresh_token' => '',
            'access_expire' => 0,
            'refresh_expire' => 0,
            'company_id' => 0,
        ]);
    }

    public static function clearAll(): void
    {
        self::query()->truncate();
    }
}
