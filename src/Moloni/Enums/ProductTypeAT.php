<?php

declare(strict_types=1);

namespace Moloni\Enums;

/**
 * Portuguese tax authority (AT) product classification codes for SAF-T.
 */
final class ProductTypeAT
{
    public const FINISHED_PRODUCTS = 'A';
    public const BIOLOGICAL_ASSETS = 'B';
    public const GOODS = 'M';
    public const RAW_MATERIALS = 'P';
    public const SUBPRODUCTS_WASTE_AND_REJECTS = 'S';
    public const WORK_AND_PRODUCTS_IN_PROGRESS = 'T';

    private function __construct()
    {
    }
}
