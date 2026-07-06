<?php

declare(strict_types=1);

namespace Moloni\Support;

/**
 * Thin wrapper over WHMCS's custom-hook mechanism (`run_hook()`), so the module
 * can expose extension points that integrators subscribe to with `add_hook()`
 * in `/includes/hooks/`.
 *
 * WHMCS only ships *action* hooks natively; a module fires its own hook point by
 * calling `run_hook($name, $vars)`, which returns every registered callback's
 * return value. This class turns that into three ergonomic shapes:
 *
 *  - {@see filter()}   let callbacks replace a value (the last non-empty return
 *                      wins), e.g. rename a product before it is created;
 *  - {@see doAction()} fire-and-forget notification, return values ignored;
 *  - {@see allows()}   a veto gate — any callback returning `false` blocks.
 *
 * Every call degrades to a no-op when `run_hook()` is unavailable (unit tests,
 * or any context outside a full WHMCS install), so the wrapper is always safe
 * to call.
 *
 * Example (`/includes/hooks/moloni_on.php`):
 * <code>
 * add_hook('MoloniOnProductName', 1, function (array $vars) {
 *     return $vars['type'] === 'Hosting' ? 'Serviço de Alojamento' : null;
 * });
 * </code>
 */
final class Hooks
{
    /** Filter: the name used when the module CREATES a Moloni product for a line. */
    public const PRODUCT_NAME = 'MoloniOnProductName';

    /** Filter: the `<Type>Insert` payload just before the document is created. */
    public const BEFORE_CREATE_DOCUMENT = 'MoloniOnBeforeCreateDocument';

    /** Action: after a document was created (and persisted) in Moloni ON. */
    public const AFTER_CREATE_DOCUMENT = 'MoloniOnAfterCreateDocument';

    /** Veto: return false to keep a matched document as a draft instead of closing it. */
    public const BEFORE_CLOSE_DOCUMENT = 'MoloniOnBeforeCloseDocument';

    /** Action: after a document was closed in Moloni ON. */
    public const AFTER_CLOSE_DOCUMENT = 'MoloniOnAfterCloseDocument';

    /** Action: after creating a document for an order failed. */
    public const DOCUMENT_FAILED = 'MoloniOnDocumentFailed';

    /**
     * Let hook callbacks replace $value. Each registered callback receives the
     * current $value (under `value`) plus $context; the last non-empty return
     * value wins. Callbacks that return null/'' leave $value untouched.
     *
     * @param array<string,mixed> $context
     * @param mixed $value
     * @return mixed
     */
    public static function filter(string $hook, $value, array $context = [])
    {
        $context['value'] = $value;

        foreach (self::run($hook, $context) as $response) {
            if ($response !== null && $response !== '') {
                $value = $response;
            }
        }

        return $value;
    }

    /**
     * Fire a hook as a notification; return values are ignored.
     *
     * @param array<string,mixed> $context
     */
    public static function doAction(string $hook, array $context = []): void
    {
        self::run($hook, $context);
    }

    /**
     * Veto gate: true unless a registered callback explicitly returns false.
     *
     * @param array<string,mixed> $context
     */
    public static function allows(string $hook, array $context = []): bool
    {
        foreach (self::run($hook, $context) as $response) {
            if ($response === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Invoke the WHMCS hook point, returning each callback's response. Safely a
     * no-op when `run_hook()` is not defined (e.g. under unit tests).
     *
     * @param array<string,mixed> $context
     * @return array<int,mixed>
     */
    private static function run(string $hook, array $context): array
    {
        if (!function_exists('run_hook')) {
            return [];
        }

        $responses = run_hook($hook, $context);

        return is_array($responses) ? $responses : [];
    }
}
