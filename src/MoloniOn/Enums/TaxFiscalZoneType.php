<?php

declare(strict_types=1);

namespace MoloniOn\Enums;

/**
 * Moloni ON fiscal-zone finance types (used to distinguish VAT from other taxes).
 */
final class TaxFiscalZoneType
{
    public const VAT = 1;
    public const STAMP_TAX = 2;
    public const OTHER = 3;

    private function __construct()
    {
    }
}
