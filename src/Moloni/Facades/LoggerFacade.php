<?php

declare(strict_types=1);

namespace Moloni\Facades;

use Moloni\Services\LogService;

/**
 * Static access to a shared {@see LogService} instance, so any layer can log
 * without dependency wiring.
 */
final class LoggerFacade
{
    private static ?LogService $logger = null;

    public static function service(): LogService
    {
        if (self::$logger === null) {
            self::$logger = new LogService();
        }

        return self::$logger;
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function debug(string $message, array $context = [], ?int $orderId = null): void
    {
        self::service()->debug($message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function info(string $message, array $context = [], ?int $orderId = null): void
    {
        self::service()->info($message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function warning(string $message, array $context = [], ?int $orderId = null): void
    {
        self::service()->warning($message, $context, $orderId);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function error(string $message, array $context = [], ?int $orderId = null): void
    {
        self::service()->error($message, $context, $orderId);
    }

    private function __construct()
    {
    }
}
