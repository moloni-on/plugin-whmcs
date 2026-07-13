<?php

declare(strict_types=1);

namespace MoloniOn\Enums;

/**
 * Moloni ON product types.
 *
 * WHMCS line items (hosting, domains, addons) are services, so SERVICE is the
 * sensible default. Values follow the Moloni ON convention; verify against the
 * live schema if product creation is rejected.
 */
final class ProductType
{
    public const PRODUCT = 1;
    public const SERVICE = 2;

    private function __construct()
    {
    }
}
