<?php

declare(strict_types=1);

namespace Moloni\Models;

use Illuminate\Database\Query\Builder;
use WHMCS\Database\Capsule;

/**
 * Thin base for models backed by a WHMCS custom table.
 *
 * Subclasses declare the table name via {@see table()}. This wraps the
 * Capsule query builder so services never reference Capsule directly.
 */
abstract class AbstractModel
{
    /**
     * The custom database table backing this model.
     */
    abstract public static function table(): string;

    /**
     * Fresh query builder for this model's table.
     */
    public static function query(): Builder
    {
        return Capsule::table(static::table());
    }

    /**
     * Insert a row and return its new id.
     *
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): int
    {
        return (int) static::query()->insertGetId($attributes);
    }
}
