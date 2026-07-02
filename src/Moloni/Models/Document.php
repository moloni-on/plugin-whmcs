<?php

declare(strict_types=1);

namespace Moloni\Models;

/**
 * A document created in Moloni ON (mod_moloni_on_documents).
 *
 * Columns mirror the record we persist for each created document:
 * order_id, order_total, invoice_id, invoice_date, invoice_status,
 * invoice_total and value (the document creation status code).
 */
class Document extends AbstractModel
{
    public static function table(): string
    {
        return 'mod_moloni_on_documents';
    }

    /**
     * Persist a freshly created document.
     *
     * @param array{
     *     order_id:int,
     *     order_total:float,
     *     invoice_id:int,
     *     invoice_date:string,
     *     invoice_status:int,
     *     invoice_total:float,
     *     value:float
     * } $attributes
     */
    public static function store(array $attributes): int
    {
        return self::create($attributes);
    }

    /**
     * @return object|null
     */
    public static function findByOrderId(int $orderId)
    {
        return self::query()->where('order_id', $orderId)->first();
    }
}
