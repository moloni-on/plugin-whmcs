<?php

declare(strict_types=1);

namespace MoloniOn\Models;

/**
 * Sync-tracking record for a WHMCS order (mod_moloni_on_orders).
 *
 * One row per order that has been acted upon (synced, discarded or failed).
 * Orders with no row here are considered "pending".
 */
class Order extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_DISCARDED = 'discarded';
    public const STATUS_FAILED = 'failed';

    public static function table(): string
    {
        return 'mod_moloni_on_orders';
    }

    /**
     * @return object|null
     */
    public static function findByOrderId(int $orderId)
    {
        return self::query()->where('order_id', $orderId)->first();
    }

    /**
     * @return array<int,int> WHMCS order ids that already have a tracking row.
     */
    public static function trackedOrderIds(): array
    {
        return self::query()->pluck('order_id')->map('intval')->all();
    }

    /**
     * Resolve the document type stored for a given Moloni document id.
     */
    public static function documentTypeFor(string $documentId): ?string
    {
        $row = self::query()->where('moloni_document_id', $documentId)->first();

        return $row->document_type ?? null;
    }

    public static function markSynced(int $orderId, string $documentId, string $documentType): void
    {
        self::query()->updateOrInsert(
            ['order_id' => $orderId],
            [
                'moloni_document_id' => $documentId,
                'document_type' => $documentType,
                'status' => self::STATUS_SYNCED,
                'error_message' => null,
                'synced_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function markFailed(int $orderId, string $error): void
    {
        self::query()->updateOrInsert(
            ['order_id' => $orderId],
            [
                'status' => self::STATUS_FAILED,
                'error_message' => $error,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function setStatus(int $orderId, string $status): void
    {
        self::query()->updateOrInsert(
            ['order_id' => $orderId],
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }
}
