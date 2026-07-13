<?php

declare(strict_types=1);

namespace MoloniOn\Api;

use MoloniOn\Exceptions\ApiException;
use MoloniOn\Exceptions\AuthException;
use MoloniOn\Support\Context;
use MoloniOn\Support\Platform;

/**
 * Low-level HTTP client for the Moloni ON API.
 *
 * Handles the OAuth2 grant/refresh calls (form-encoded) and GraphQL requests
 * (JSON with a Bearer token). Uses native cURL to avoid pulling an HTTP client
 * dependency into the WHMCS environment.
 */
class ApiClient
{
    private int $timeout;

    public function __construct(int $timeout = Platform::API_TIMEOUT)
    {
        $this->timeout = $timeout;
    }

    /**
     * Execute a GraphQL operation.
     *
     * The active company id (from {@see Context}) is injected as a variable so
     * callers never have to pass it explicitly. Returns the decoded response.
     *
     * @param array<string,mixed> $variables
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function request(string $operation, string $query, array $variables = []): array
    {
        if (Context::$companyId > 0 && !isset($variables['companyId'])) {
            $variables['companyId'] = Context::$companyId;
        }

        $body = ['query' => $query];

        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        $result = $this->httpPost(
            Platform::API_URL,
            (string) json_encode($body),
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Context::$accessToken,
                'User-Agent: ' . Platform::USER_AGENT,
            ]
        );

        // Distinguish an expired/invalid token (401) and transient outages
        // (429/5xx) from a business error before parsing: they are otherwise
        // indistinguishable once collapsed into the response body.
        $this->assertHttpOk($result['status']);

        $parsed = json_decode($result['body'], true);

        if (!is_array($parsed)) {
            throw new ApiException(
                'Invalid response from Moloni ON API.',
                ['raw' => $result['body'], 'status' => $result['status']]
            );
        }

        $this->assertNoErrors($operation, $parsed, $variables);

        // A non-2xx/3xx status that carried no recognisable GraphQL error node
        // is still a failure — never treat it as a successful response.
        if ($result['status'] >= 400) {
            throw new ApiException(
                'Moloni ON API returned HTTP ' . $result['status'] . '.',
                ['status' => $result['status']]
            );
        }

        return $parsed;
    }

    /**
     * Exchange an authorization code for access/refresh tokens.
     *
     * @return array{accessToken:string,refreshToken:string}
     * @throws AuthException
     */
    public function grant(string $clientId, string $clientSecret, string $code): array
    {
        $fields = http_build_query([
            'grantType' => 'authorization_code',
            'apiClientId' => $clientId,
            'clientSecret' => $clientSecret,
            'code' => $code,
        ]);

        $parsed = $this->grantRequest($fields);

        if (!isset($parsed['accessToken'], $parsed['refreshToken'])) {
            throw new AuthException(
                'Invalid credentials or authorization code.',
                ['response' => $this->redactSecrets($parsed)]
            );
        }

        return $parsed;
    }

    /**
     * Refresh an expired access token.
     *
     * Returns null only when the refresh token is definitively rejected (the
     * session cannot be recovered). A transient failure (network / 5xx / 429) is
     * rethrown instead, so the caller keeps the session and retries later rather
     * than forcing a needless full re-authentication.
     *
     * @return array{accessToken:string,refreshToken:string}|null Null when the
     *         refresh token is rejected.
     * @throws ApiException on a transient failure
     */
    public function refresh(string $clientId, string $clientSecret, string $refreshToken): ?array
    {
        $fields = http_build_query([
            'grantType' => 'refresh_token',
            'apiClientId' => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
        ]);

        try {
            $parsed = $this->grantRequest($fields);
        } catch (ApiException $e) {
            if (!empty($e->getData()['transient'])) {
                throw $e;
            }

            return null;
        }

        if (!isset($parsed['accessToken'], $parsed['refreshToken'])) {
            return null;
        }

        return $parsed;
    }

    /**
     * Build the OAuth2 authorize URL to redirect the admin to.
     */
    public function authorizeUrl(string $clientId, string $redirectUri, string $state = ''): string
    {
        $url = Platform::AUTH_AUTHORIZE
            . '?apiClientId=' . rawurlencode($clientId)
            . '&redirectUri=' . rawurlencode($redirectUri);

        if ($state !== '') {
            $url .= '&state=' . rawurlencode($state);
        }

        return $url;
    }

    /**
     * Fail fast on the HTTP-transport outcomes that must not be read as a
     * business response: a rejected token (401 → re-auth) and transient outages
     * (429 / 5xx → retryable). Everything else is left to the body-level checks.
     *
     * @throws AuthException on 401
     * @throws ApiException on a transient status
     */
    private function assertHttpOk(int $status): void
    {
        if ($status === 401) {
            throw new AuthException('Moloni ON rejected the access token (HTTP 401).', ['status' => 401]);
        }

        if ($this->isTransientStatus($status)) {
            throw new ApiException(
                'Moloni ON is temporarily unavailable (HTTP ' . $status . ').',
                ['status' => $status, 'transient' => true]
            );
        }
    }

    /**
     * Whether an HTTP status denotes a transient, retryable condition (rate
     * limiting or a server-side error) rather than a permanent rejection.
     */
    private function isTransientStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * @param array<string,mixed> $parsed
     * @param array<string,mixed> $variables The data sent with the request.
     * @throws ApiException
     */
    private function assertNoErrors(string $operation, array $parsed, array $variables = []): void
    {
        if (!empty($parsed['errors'])) {
            throw new ApiException('Moloni ON API error.', ['errors' => $parsed['errors'], 'sent' => $variables]);
        }

        // Moloni returns validation/business errors in the per-operation
        // `errors` node, sometimes alongside a partial `data` node. Treat any
        // non-empty operation error as fatal so failures are never swallowed.
        $operationErrors = $parsed['data'][$operation]['errors'] ?? [];

        if (!empty($operationErrors)) {
            throw new ApiException(
                'Moloni ON API rejected the request.',
                ['errors' => $operationErrors, 'sent' => $variables]
            );
        }
    }

    /**
     * @return array<string,mixed>
     * @throws ApiException
     */
    private function grantRequest(string $fields): array
    {
        $result = $this->httpPost(
            Platform::AUTH_GRANT,
            $fields,
            ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: ' . Platform::USER_AGENT]
        );

        // A transient outage must be flagged so a token refresh does not mistake
        // it for a rejected refresh token and tear the session down.
        if ($this->isTransientStatus($result['status'])) {
            throw new ApiException(
                'Moloni ON auth endpoint is temporarily unavailable (HTTP ' . $result['status'] . ').',
                ['status' => $result['status'], 'transient' => true]
            );
        }

        $parsed = json_decode($result['body'], true);

        if (!is_array($parsed)) {
            throw new ApiException(
                'Invalid response from Moloni ON auth endpoint.',
                ['raw' => $result['body'], 'status' => $result['status']]
            );
        }

        if (isset($parsed['error'])) {
            throw new ApiException(
                'Moloni ON auth error.',
                ['response' => $this->redactSecrets($parsed), 'status' => $result['status']]
            );
        }

        return $parsed;
    }

    /**
     * Strip token/secret fields from a decoded auth response so they can never
     * leak into exception context — and therefore into logs — if the context
     * is ever recorded.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactSecrets(array $data): array
    {
        foreach (['accessToken', 'refreshToken', 'clientSecret', 'apiClientId', 'code'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }

    /**
     * Download a binary resource (e.g. a document PDF) from a media URL.
     *
     * @throws ApiException
     */
    public function download(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $content = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false) {
            throw new ApiException('Could not download file from Moloni ON: ' . $error);
        }

        if ($status >= 400) {
            throw new ApiException('Moloni ON media API returned HTTP ' . $status . '.');
        }

        // A successful-but-empty body ('') is passed through as-is: whether an
        // empty payload is an error depends on the resource, so that check is
        // deliberately left to the caller (e.g. DocumentService::downloadPdf).
        return (string) $content;
    }

    /**
     * @param array<int,string> $headers
     * @return array{status:int,body:string}
     * @throws ApiException
     */
    private function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            // A connection-level failure is transient: it never reached Moloni,
            // so it must not be read as a rejected token/refresh.
            throw new ApiException('Could not reach Moloni ON: ' . $error, ['transient' => true]);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $response];
    }
}
