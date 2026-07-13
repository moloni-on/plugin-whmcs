<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\Lang;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    public function testReturnsEnglishString(): void
    {
        Lang::boot('en');

        self::assertSame('Moloni ON', Lang::get('module_name'));
    }

    public function testFallsBackToKeyWhenMissing(): void
    {
        Lang::boot('en');

        self::assertSame('nonexistent_key_123', Lang::get('nonexistent_key_123'));
    }

    public function testInterpolatesPlaceholders(): void
    {
        Lang::boot('en');

        self::assertSame('Document created (ID 42).', Lang::get('document_created', ['id' => 42]));
    }

    public function testPortugueseLoads(): void
    {
        Lang::boot('pt');

        self::assertSame('pt', Lang::language());
        self::assertNotSame('nav_orders', Lang::get('nav_orders'));
    }

    public function testUnknownLanguageDefaultsToEnglish(): void
    {
        Lang::boot('fr');

        self::assertSame('en', Lang::language());
    }
}
