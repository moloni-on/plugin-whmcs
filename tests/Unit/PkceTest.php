<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\Pkce;
use PHPUnit\Framework\TestCase;

final class PkceTest extends TestCase
{
    public function testVerifierIsLongEnoughAndBase64Url(): void
    {
        $verifier = Pkce::verifier();

        // RFC 7636: 43-128 characters, unreserved charset only (no padding).
        self::assertGreaterThanOrEqual(43, strlen($verifier));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier);
    }

    public function testVerifierIsRandomPerCall(): void
    {
        self::assertNotSame(Pkce::verifier(), Pkce::verifier());
    }

    public function testChallengeIsTheBase64UrlSha256OfTheVerifier(): void
    {
        $verifier = 'a-fixed-test-verifier-value-1234567890';

        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        self::assertSame($expected, Pkce::challenge($verifier));
    }

    public function testChallengeHasNoPaddingOrUrlUnsafeCharacters(): void
    {
        $challenge = Pkce::challenge(Pkce::verifier());

        self::assertStringNotContainsString('=', $challenge);
        self::assertStringNotContainsString('+', $challenge);
        self::assertStringNotContainsString('/', $challenge);
    }
}
