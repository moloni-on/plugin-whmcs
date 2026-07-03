<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Support\FiscalZone;
use PHPUnit\Framework\TestCase;

final class FiscalZoneTest extends TestCase
{
    public function testKeepsCodeAndCountryId(): void
    {
        $zone = new FiscalZone('ES', 7);

        self::assertSame('ES', $zone->code());
        self::assertSame(7, $zone->countryId());
    }

    public function testUpperCasesAndTrimsTheCode(): void
    {
        $zone = new FiscalZone('  es  ', 7);

        self::assertSame('ES', $zone->code());
    }

    public function testFallsBackToPortugalWhenCodeIsBlank(): void
    {
        self::assertSame('PT', (new FiscalZone('', 0))->code());
        self::assertSame('PT', (new FiscalZone('   ', 0))->code());
    }
}
