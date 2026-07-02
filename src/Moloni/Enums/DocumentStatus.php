<?php

declare(strict_types=1);

namespace Moloni\Enums;

/**
 * Moloni ON document status codes.
 */
final class DocumentStatus
{
    public const DRAFT = 0;
    public const CLOSED = 1;

    private function __construct()
    {
    }
}
