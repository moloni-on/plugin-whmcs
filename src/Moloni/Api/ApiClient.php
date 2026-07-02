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

        $this->assertNoErrors($operation, $parsed);

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
            throw new AuthException('Invalid credentials or authorization code.', ['response' => $parsed]);
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
    public function authorizeUrl(string $clientId, string $redirectUri): string
    {
        return Platform::AUTH_AUTHORIZE
            . '?apiClientId=' . rawurlencode($clientId)
            . '&redirectUri=' . rawurlencode($redirectUri);
    }

    /**
     * @param array<string,mixed> $parsed
     * @throws ApiException
     */
    private function assertNoErrors(string $operation, array $parsed): void
    {
        if (!empty($parsed['errors'])) {
            throw new ApiException('Moloni ON API error.', ['errors' => $parsed['errors']]);
        }

        $operationErrors = $parsed['data'][$operation]['errors'] ?? [];

        if (!empty($operationErrors) && empty($parsed['data'][$operation]['data'])) {
            throw new ApiException('Moloni ON API rejected the request.', ['errors' => $operationErrors]);
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
            throw new ApiException('Moloni ON auth error.', ['response' => $parsed]);
        }

        return $parsed;
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
