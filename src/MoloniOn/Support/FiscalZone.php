<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * A document's fiscal zone: the ISO-3166-1 alpha-2 code plus the matching
 * Moloni countryId.
 *
 * Centralises the two defaults that were previously repeated at every call
 * site: an empty/blank code falls back to Portugal ({@see self::DEFAULT_CODE}),
 * and the code is always normalised to upper case (Moloni stores fiscal-zone
 * codes in upper case, so lookups and created taxes must use that form).
 */
final class FiscalZone
{
    /** Fiscal zone used when none can be determined. */
    private const DEFAULT_CODE = 'PT';

    private string $code;

    private int $countryId;

    public function __construct(string $code, int $countryId)
    {
        $normalised = strtoupper(trim($code));
        $this->code = $normalised !== '' ? $normalised : self::DEFAULT_CODE;
        $this->countryId = $countryId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function countryId(): int
    {
        return $this->countryId;
    }
}
