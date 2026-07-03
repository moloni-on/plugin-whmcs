<?php

declare(strict_types=1);

namespace Moloni\Api;

use Moloni\Exceptions\ApiException;
use Moloni\Exceptions\AuthException;
use Moloni\Support\Context;
use Moloni\Support\Platform;

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

        $response = $this->httpPost(
            Platform::API_URL,
            (string) json_encode($body),
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Context::$accessToken,
                'User-Agent: ' . Platform::USER_AGENT,
            ]
        );

        $parsed = json_decode($response, true);

        if (!is_array($parsed)) {
            throw new ApiException('Invalid response from Moloni ON API.', ['raw' => $response]);
        }

        $this->assertNoErrors($operation, $parsed, $variables);

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
     * @return array{accessToken:string,refreshToken:string}|null Null on failure.
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
        $response = $this->httpPost(
            Platform::AUTH_GRANT,
            $fields,
            ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: ' . Platform::USER_AGENT]
        );

        $parsed = json_decode($response, true);

        if (!is_array($parsed)) {
            throw new ApiException('Invalid response from Moloni ON auth endpoint.', ['raw' => $response]);
        }

        if (isset($parsed['error'])) {
            throw new ApiException('Moloni ON auth error.', ['response' => $this->redactSecrets($parsed)]);
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
     * @throws ApiException
     */
    private function httpPost(string $url, string $body, array $headers): string
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

            throw new ApiException('Could not reach Moloni ON: ' . $error);
        }

        curl_close($ch);

        return (string) $response;
    }
}
