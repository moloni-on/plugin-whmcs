<?php

declare(strict_types=1);

namespace Moloni\Models;

use Illuminate\Database\Query\Builder;

/**
 * Application log record (mod_moloni_on_logs).
 */
class Log extends AbstractModel
{
    public static function table(): string
    {
        return 'mod_moloni_on_logs';
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function write(string $level, string $message, array $context = [], ?int $orderId = null): void
    {
        self::create([
            'level' => $level,
            'message' => $message,
            'context' => $context === [] ? null : json_encode($context),
            'order_id' => $orderId,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Fetch logs with optional filters, most recent first.
     *
     * @param array{level?:string,order_id?:int,from?:string,to?:string} $filters
     * @return array<int,object>
     */
    public static function fetch(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        return self::applyFilters(self::query(), $filters)
            ->orderByDesc('timestamp')
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Count logs matching the given filters (for pagination totals).
     *
     * @param array{level?:string,order_id?:int,from?:string,to?:string} $filters
     */
    public static function countFiltered(array $filters = []): int
    {
        return (int) self::applyFilters(self::query(), $filters)->count();
    }

    /**
     * Apply the shared log filters to a query builder.
     *
     * @param array{level?:string,order_id?:int,from?:string,to?:string} $filters
     */
    private static function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('timestamp', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('timestamp', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * Delete log entries. With no cutoff, removes everything; with a cutoff
     * (a 'Y-m-d H:i:s' timestamp) removes only entries strictly older than it.
     */
    public static function clear(?string $olderThan = null): void
    {
        if ($olderThan === null) {
            self::query()->truncate();

            return;
        }

        self::query()->where('timestamp', '<', $olderThan)->delete();
    }
}
