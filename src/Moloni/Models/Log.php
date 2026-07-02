<?php

declare(strict_types=1);

namespace Moloni\Models;

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
    public static function fetch(array $filters = [], int $limit = 200): array
    {
        $query = self::query();

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

        return $query->orderByDesc('timestamp')->limit($limit)->get()->all();
    }

    public static function clear(): void
    {
        self::query()->truncate();
    }
}
