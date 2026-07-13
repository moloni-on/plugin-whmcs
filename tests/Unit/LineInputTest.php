<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\LineInput;
use PHPUnit\Framework\TestCase;

final class LineInputTest extends TestCase
{
    public function testExposesAllBillingFields(): void
    {
        $line = new LineInput('cPanel Pro', 9.99, 'CPA-PRO', 'example.com', 10.0, 'Alojamento');

        self::assertSame('cPanel Pro', $line->name());
        self::assertSame(9.99, $line->price());
        self::assertSame('CPA-PRO', $line->reference());
        self::assertSame('example.com', $line->summary());
        self::assertSame(10.0, $line->discount());
        self::assertSame('Alojamento', $line->productName());
    }

    public function testDefaultsForOptionalFields(): void
    {
        $line = new LineInput('Order total', 100.0);

        self::assertNull($line->reference());
        self::assertSame('', $line->summary());
        self::assertSame(0.0, $line->discount());
        self::assertSame('', $line->productName());
    }
}
