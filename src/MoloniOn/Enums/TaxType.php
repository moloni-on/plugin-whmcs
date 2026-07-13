<?php

declare(strict_types=1);

namespace MoloniOn\Enums;

/**
 * Moloni ON tax value types.
 */
final class TaxType
{
    public const PERCENTAGE = 1;
    public const CONSTANT_VALUE = 2;
    public const BY_PRODUCT = 3;

    private function __construct()
    {
    }
}
