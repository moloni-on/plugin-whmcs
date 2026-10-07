<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use DateTimeImmutable;
use MoloniOn\Support\DocumentDate;
use PHPUnit\Framework\TestCase;

final class DocumentDateTest extends TestCase
{
    private string $serverTimezone;

    protected function setUp(): void
    {
        $this->serverTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverTimezone);
    }

    public function testNowCarriesAnExplicitUtcOffset(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            DocumentDate::now()
        );
    }

    public function testNowIsTheSameInstantWhateverTheServerTimezone(): void
    {
        date_default_timezone_set('Europe/Lisbon');
        $lisbon = new DateTimeImmutable(DocumentDate::now());

        date_default_timezone_set('UTC');
        $utc = new DateTimeImmutable(DocumentDate::now());

        self::assertLessThanOrEqual(2, abs($utc->getTimestamp() - $lisbon->getTimestamp()));
        self::assertLessThanOrEqual(2, abs(time() - $utc->getTimestamp()));
    }

    public function testStartOfDayIsMidnightInTheCompanyTimezone(): void
    {
        self::assertSame(
            '2026-10-04T00:00:00+01:00',
            DocumentDate::startOfDay('2026-10-04', 'Europe/Lisbon')
        );
        self::assertSame(
            '2026-12-04T00:00:00+00:00',
            DocumentDate::startOfDay('2026-12-04', 'Europe/Lisbon')
        );
    }

    public function testStartOfDayIgnoresTheServerTimezone(): void
    {
        date_default_timezone_set('America/New_York');

        self::assertSame(
            '2026-10-04T00:00:00+01:00',
            DocumentDate::startOfDay('2026-10-04', 'Europe/Lisbon')
        );
    }

    public function testStartOfDayDropsATimePart(): void
    {
        self::assertSame(
            '2026-10-04T00:00:00+01:00',
            DocumentDate::startOfDay('2026-10-04 15:30:00', 'Europe/Lisbon')
        );
    }

    public function testStartOfDayFallsBackToUtcForAnUnknownTimezone(): void
    {
        self::assertSame('2026-10-04T00:00:00+00:00', DocumentDate::startOfDay('2026-10-04', ''));
        self::assertSame('2026-10-04T00:00:00+00:00', DocumentDate::startOfDay('2026-10-04', 'Not/AZone'));
    }

    public function testStartOfDayRejectsMissingOrInvalidDates(): void
    {
        self::assertNull(DocumentDate::startOfDay('', 'Europe/Lisbon'));
        self::assertNull(DocumentDate::startOfDay('0000-00-00', 'Europe/Lisbon'));
        self::assertNull(DocumentDate::startOfDay('2026-02-30', 'Europe/Lisbon'));
        self::assertNull(DocumentDate::startOfDay('not a date', 'Europe/Lisbon'));
    }
}
