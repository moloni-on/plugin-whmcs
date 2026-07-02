<?php

declare(strict_types=1);

namespace Moloni\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for the Moloni ON module.
 *
 * Carries an optional context array so callers can log structured data
 * alongside the human-readable message.
 */
class MoloniException extends Exception
{
    /** @var array<string,mixed> */
    protected array $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(string $message = '', array $data = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->data = $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
