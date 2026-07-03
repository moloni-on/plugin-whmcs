<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Models\Log;
use Moloni\Support\Paginator;
use Throwable;

/**
 * Writes and reads application logs (mod_moloni_on_logs).
 *
 * Logging never throws: a failed log write must not break the operation it is
 * recording.
 */
class LogService
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $level, string $message, array $context = [], ?int $orderId = null): void
    {
        try {
            Log::write($level, $message, $context, $orderId);
        } catch (Throwable $e) {
            // Swallow: logging must not be able to break the caller.
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    public function debug(string $message, array $context = [], ?int $orderId = null): void
    {
        $this->log(self::DEBUG, $message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function info(string $message, array $context = [], ?int $orderId = null): void
    {
        $this->log(self::INFO, $message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function warning(string $message, array $context = [], ?int $orderId = null): void
    {
        $this->log(self::WARNING, $message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function error(string $message, array $context = [], ?int $orderId = null): void
    {
        $this->log(self::ERROR, $message, $context, $orderId);
    }

    /**
     * A single page of logs, most recent first, matching the given filters.
     *
     * @param array{level?:string,order_id?:int,from?:string,to?:string} $filters
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = Paginator::PER_PAGE): Paginator
    {
        return Paginator::paginate(
            $page,
            Log::countFiltered($filters),
            $perPage,
            static fn (int $offset, int $limit): array => Log::fetch($filters, $limit, $offset)
        );
    }

    /**
     * Remove log entries older than one week, keeping recent activity.
     */
    public function clearLogs(): void
    {
        Log::clear(date('Y-m-d H:i:s', strtotime('-1 week')));
    }
}
