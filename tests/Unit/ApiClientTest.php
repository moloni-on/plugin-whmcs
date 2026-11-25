<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Api\ApiClient;
use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase
{
    public function testAuthorizeUrlUsesSnakeCaseFieldsWithPkce(): void
    {
        $api = new ApiClient();

        $url = $api->authorizeUrl('client-123', 'https://example.com/callback', 'the-challenge', 'the-state');

        self::assertSame(
            'https://api.molonion.pt/v1/auth/authorize'
            . '?response_type=code'
            . '&client_id=client-123'
            . '&redirect_uri=https%3A%2F%2Fexample.com%2Fcallback'
            . '&code_challenge=the-challenge'
            . '&code_challenge_method=S256'
            . '&state=the-state',
            $url
        );
    }

    public function testGrantFieldsForLegacyClientOmitsPkceAndKeepsClientSecret(): void
    {
        $fields = ApiClient::grantFields('client-1', 'the-secret', 'the-code');

        self::assertSame(
            [
                'grantType' => 'authorization_code',
                'apiClientId' => 'client-1',
                'clientSecret' => 'the-secret',
                'code' => 'the-code',
            ],
            $fields
        );
    }

    public function testGrantFieldsForPublicClientOmitsSecretAndAddsPkce(): void
    {
        $fields = ApiClient::grantFields('client-1', '', 'the-code', 'the-verifier', 'https://example.com/cb');

        self::assertSame(
            [
                'grantType' => 'authorization_code',
                'apiClientId' => 'client-1',
                'code' => 'the-code',
                'code_verifier' => 'the-verifier',
                'redirect_uri' => 'https://example.com/cb',
            ],
            $fields
        );
        self::assertArrayNotHasKey('clientSecret', $fields);
    }

    public function testRefreshFieldsForLegacyClientKeepsClientSecret(): void
    {
        $fields = ApiClient::refreshFields('client-1', 'the-secret', 'the-refresh-token');

        self::assertSame(
            [
                'grantType' => 'refresh_token',
                'apiClientId' => 'client-1',
                'clientSecret' => 'the-secret',
                'refreshToken' => 'the-refresh-token',
            ],
            $fields
        );
    }

    public function testRefreshFieldsForPublicClientOmitsSecret(): void
    {
        $fields = ApiClient::refreshFields('client-1', '', 'the-refresh-token');

        self::assertSame(
            ['grantType' => 'refresh_token', 'apiClientId' => 'client-1', 'refreshToken' => 'the-refresh-token'],
            $fields
        );
        self::assertArrayNotHasKey('clientSecret', $fields);
    }
}
