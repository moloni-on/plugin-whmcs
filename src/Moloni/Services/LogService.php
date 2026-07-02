<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Models\Log;
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
     * @param array{level?:string,order_id?:int,from?:string,to?:string} $filters
     * @return array<int,object>
     */
    public function getLogs(array $filters = [], int $limit = 200): array
    {
        return Log::fetch($filters, $limit);
    }

    public function clearLogs(): void
    {
        Log::clear();
    }
}
