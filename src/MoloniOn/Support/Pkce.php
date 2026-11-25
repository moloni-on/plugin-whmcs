<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * PKCE (RFC 7636) code verifier/challenge generation for the public-client
 * OAuth connect flow. Framework-free (no WHMCS dependency) so the crypto is
 * unit-testable on its own.
 */
final class Pkce
{
    /**
     * A fresh code verifier: 32 random bytes, base64url-encoded — 43
     * characters, the RFC 7636 minimum (and within its 43-128 range).
     */
    public static function verifier(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    /**
     * The S256 code challenge for a given verifier.
     */
    public static function challenge(string $verifier): string
    {
        return self::base64UrlEncode(hash('sha256', $verifier, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function __construct()
    {
    }
}
