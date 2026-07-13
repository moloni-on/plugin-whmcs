<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * Static Moloni ON platform configuration (endpoints and identifiers).
 *
 * These values mirror the official Moloni ON platform configuration. The API
 * uses an OAuth2 authorization-code flow; see the auth endpoints below.
 */
final class Platform
{
    /** Module version. Single source of truth; also used to cache-bust assets. */
    public const VERSION = '1.0.0';

    /** GraphQL API base (also the OAuth grant/authorize host). */
    public const API_URL = 'https://api.molonion.pt/v1';

    /** Authorization center (user-facing consent screen). */
    public const AC_URL = 'https://ac.molonion.pt/';

    /** Media API host (PDF downloads use a signed token against this host). */
    public const MEDIA_API_URL = 'https://mediaapi.moloni.org';

    /** Public help/guides page (linked from the footer). */
    public const HELP_URL = 'https://www.molonion.pt/help';

    /** Default request timeout, in seconds. */
    public const API_TIMEOUT = 45;

    /** OAuth authorize endpoint (redirect target). */
    public const AUTH_AUTHORIZE = self::API_URL . '/auth/authorize';

    /** OAuth grant endpoint (code exchange and token refresh). */
    public const AUTH_GRANT = self::API_URL . '/auth/grant';

    /** User-agent sent on every API request. */
    public const USER_AGENT = 'WhmcsMoloniOn/1.0';

    private function __construct()
    {
    }
}
