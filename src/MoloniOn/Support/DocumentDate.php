<?php

declare(strict_types=1);

namespace MoloniOn\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Builds the date strings sent to Moloni ON for documents and payments.
 *
 * Moloni ON stores a document date as an absolute instant (UTC) and renders it
 * in the company's own timezone (PDF, SAF-T, reports). A date sent without an
 * offset is read as UTC, so the old `date('Y-m-d H:i:s')` — the WHMCS server's
 * wall-clock time, no offset — was only right when PHP itself ran in UTC; on a
 * server set to e.g. Europe/Lisbon every document came out an hour late in
 * summer, and one issued just before midnight landed on the next day.
 *
 * Every value is therefore sent as ISO-8601 with an explicit offset, which is
 * correct whatever `date.timezone` the WHMCS server uses.
 */
final class DocumentDate
{
    /**
     * Timezone used when the company's is unknown. UTC midnight keeps the day
     * unchanged for every European company (it renders at 00:00–02:00 local).
     */
    private const DEFAULT_TIMEZONE = 'UTC';

    /**
     * The current instant, e.g. "2026-10-03T23:40:00+00:00".
     */
    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    /**
     * A calendar day (WHMCS stores due dates as `Y-m-d`) as midnight in the
     * company's timezone, so Moloni ON renders it on that same day. Returns
     * null for an empty, zero (`0000-00-00`) or unparsable value.
     */
    public static function startOfDay(string $date, string $timezone): ?string
    {
        $day = substr(trim($date), 0, 10);

        if ($day === '' || strpos($day, '0000-00-00') === 0) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, self::timezone($timezone));

        if ($parsed === false || $parsed->format('Y-m-d') !== $day) {
            return null;
        }

        return $parsed->format(DATE_ATOM);
    }

    private static function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name !== '' ? $name : self::DEFAULT_TIMEZONE);
        } catch (Exception $e) {
            return new DateTimeZone(self::DEFAULT_TIMEZONE);
        }
    }
}
