<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL;

/**
 * Base for a single GraphQL query or mutation.
 *
 * Each concrete operation stores its GraphQL string in the QUERY constant,
 * declares the operation name (the root field, used to locate data/errors in
 * the response) and builds its variables from an input array.
 */
abstract class AbstractOperation
{
    /** The GraphQL document. */
    protected const QUERY = '';

    /** The root operation field name (e.g. "invoiceCreate"). */
    protected const OPERATION = '';

    public function query(): string
    {
        return static::QUERY;
    }

    public function operation(): string
    {
        return static::OPERATION;
    }

    /**
     * Build the variables payload for this operation.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return $data;
    }
}
