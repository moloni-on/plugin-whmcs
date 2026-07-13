<?php

declare(strict_types=1);

namespace MoloniOn\Services;

use MoloniOn\Api\ApiClient;
use MoloniOn\Api\MoloniClient;
use MoloniOn\Exceptions\ApiException;
use MoloniOn\Exceptions\AuthException;
use MoloniOn\Facades\LoggerFacade;
use MoloniOn\Models\Auth;
use MoloniOn\Support\Context;

/**
 * Drives the Moloni ON OAuth2 authorization-code flow and keeps the runtime
 * {@see Context} populated with the active session.
 *
 * Flow: setClient() -> authorizeUrl() (redirect) -> exchangeCode(callback) ->
 * ensureAuthenticated() (loads/refreshes tokens) -> selectCompany().
 */
class AuthService
{
    /** Session key holding the one-time OAuth CSRF `state`. */
    private const STATE_KEY = 'moloni_on_oauth_state';

    /** Refresh access tokens this many seconds before they actually expire. */
    private const EXPIRY_SKEW = 60;

    /** Fallback access-token lifetime (seconds) when the grant omits expiresIn. */
    private const DEFAULT_ACCESS_TTL = 3000;

    /** Fallback refresh-token lifetime (seconds, ~10 days) when omitted. */
    private const DEFAULT_REFRESH_TTL = 864000;

    private ApiClient $api;

    private MoloniClient $client;

    public function __construct(ApiClient $api, MoloniClient $client)
    {
        $this->api = $api;
        $this->client = $client;
    }

    /**
     * Store developer credentials (resets any existing session).
     */
    public function setClient(string $clientId, string $clientSecret): void
    {
        Auth::setClient($clientId, $clientSecret);
    }

    /**
     * Build the URL the admin must be redirected to in order to authorize.
     */
    public function authorizeUrl(string $redirectUri): string
    {
        $row = Auth::row();
        $clientId = (string) ($row['client_id'] ?? '');

        return $this->api->authorizeUrl($clientId, $redirectUri, $this->issueState());
    }

    /**
     * Generate and remember a one-time CSRF `state` for the OAuth redirect.
     */
    private function issueState(): string
    {
        $state = bin2hex(random_bytes(16));

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::STATE_KEY] = $state;
        }

        return $state;
    }

    /**
     * Validate (and consume) the `state` returned on the OAuth callback.
     * Fails closed when no state was issued for this session.
     */
    public function verifyState(string $state): bool
    {
        $expected = $_SESSION[self::STATE_KEY] ?? '';
        unset($_SESSION[self::STATE_KEY]);

        return $state !== '' && is_string($expected) && $expected !== '' && hash_equals($expected, $state);
    }

    /**
     * Exchange an authorization code (from the OAuth callback) for tokens.
     *
     * @throws AuthException
     */
    public function exchangeCode(string $code): void
    {
        $row = Auth::row();

        if ($row === null) {
            throw new AuthException('No stored credentials to complete authentication.');
        }

        $tokens = $this->api->grant((string) $row['client_id'], (string) $row['client_secret'], $code);
        $this->storeTokens($tokens);

        LoggerFacade::info('Authenticated with Moloni ON.');
    }

    /**
     * Ensure a usable access token is loaded into the Context, refreshing it
     * when expired. Returns false when re-authentication is required.
     */
    public function ensureAuthenticated(): bool
    {
        $row = Auth::row();

        if ($row === null || empty($row['access_token'])) {
            return false;
        }

        if ((int) $row['access_expire'] - self::EXPIRY_SKEW <= time()) {
            if (!$this->refresh($row)) {
                // Refresh token expired or the grant call failed: the session
                // can no longer be recovered, so kill it. The admin must run
                // the full login flow again.
                $this->logout();

                return false;
            }

            $row = Auth::row();
        }

        Context::$sessionId = (int) $row['id'];
        Context::$accessToken = (string) $row['access_token'];
        Context::$companyId = (int) $row['company_id'];

        return true;
    }

    public function hasCompany(): bool
    {
        return Context::$companyId > 0;
    }

    public function selectCompany(int $companyId): void
    {
        Auth::setCompany($companyId);
        Context::$companyId = $companyId;
    }

    /**
     * Load the selected company payload into the Context (best effort).
     */
    public function loadCompany(): void
    {
        if (!Context::hasCompany()) {
            return;
        }

        try {
            Context::setCompany($this->client->getCompany(Context::$companyId));
        } catch (ApiException $e) {
            Context::setCompany([]);
            LoggerFacade::warning('Could not load company details.', ['error' => $e->getMessage()]);
        }
    }

    public function logout(): void
    {
        Auth::clearTokens();
        Context::reset();
    }

    /**
     * Attempt to refresh the access token.
     *
     * Returns false only when the session is unrecoverable (refresh token gone,
     * expired, or rejected) — the caller then logs out. A transient failure
     * (network / 5xx / 429) propagates as an {@see ApiException} instead, so the
     * session is left intact for a later retry rather than being torn down.
     *
     * @param array<string,mixed> $row
     * @throws ApiException on a transient refresh failure
     */
    private function refresh(array $row): bool
    {
        if (empty($row['refresh_token']) || (int) $row['refresh_expire'] <= time()) {
            LoggerFacade::warning('Refresh token missing or expired; re-authentication required.');

            return false;
        }

        $tokens = $this->api->refresh(
            (string) $row['client_id'],
            (string) $row['client_secret'],
            (string) $row['refresh_token']
        );

        if ($tokens === null) {
            LoggerFacade::warning('Token refresh failed; re-authentication required.');

            return false;
        }

        $this->storeTokens($tokens);

        return true;
    }

    /**
     * Persist tokens, honouring the lifetimes the OAuth endpoint returned.
     *
     * @param array<string,mixed> $tokens
     */
    private function storeTokens(array $tokens): void
    {
        Auth::setTokens(
            (string) $tokens['accessToken'],
            (string) $tokens['refreshToken'],
            (int) ($tokens['expiresIn'] ?? self::DEFAULT_ACCESS_TTL),
            (int) ($tokens['refreshExpiresIn'] ?? self::DEFAULT_REFRESH_TTL)
        );
    }
}
