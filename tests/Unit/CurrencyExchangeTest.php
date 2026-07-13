<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\CurrencyExchange;
use PHPUnit\Framework\TestCase;

final class CurrencyExchangeTest extends TestCase
{
    public function testExposesIdAndRate(): void
    {
        $exchange = new CurrencyExchange(42, 1.1);

        self::assertSame(42, $exchange->id());
        self::assertSame(1.1, $exchange->rate());
    }

    /**
     * Pins the conversion direction: the rate is the base -> order rate (e.g.
     * 1 EUR = 1.1 USD), so an order-currency amount is divided by it to reach
     * the company base currency. 110 USD / 1.1 = 100 EUR.
     */
    public function testToBaseDividesOrderAmountByRate(): void
    {
        $exchange = new CurrencyExchange(1, 1.1);

        self::assertEqualsWithDelta(100.0, $exchange->toBase(110.0), 0.0001);
    }

    public function testToBaseIsIdentityAtParity(): void
    {
        $exchange = new CurrencyExchange(1, 1.0);

        self::assertSame(50.0, $exchange->toBase(50.0));
    }

    public function testToBaseLeavesAmountUntouchedForNonPositiveRate(): void
    {
        $exchange = new CurrencyExchange(1, 0.0);

        self::assertSame(75.0, $exchange->toBase(75.0));
    }
}
