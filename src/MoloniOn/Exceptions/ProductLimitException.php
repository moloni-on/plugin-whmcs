<?php

declare(strict_types=1);

namespace MoloniOn\Exceptions;

use MoloniOn\Support\Lang;
use Throwable;

/**
 * Thrown when a missing product cannot be created in Moloni ON because the
 * company's plan product limit has been reached: up front when the company
 * limits say the plan is full ({@see \MoloniOn\Support\Company::canCreateProducts()}),
 * or when Moloni ON itself refuses the create. The document still fails, but
 * with this message instead of the generic API rejection.
 */
class ProductLimitException extends ApiException
{
    /** What Moloni ON answers a product create with once the plan's product limit is reached. */
    private const API_ERROR = 'Number of items is over the allowed limit.';

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(string $reference, array $data = [], ?Throwable $previous = null)
    {
        parent::__construct(
            Lang::get('product_limit_reached', ['reference' => $reference]),
            ['reference' => $reference] + $data,
            0,
            $previous
        );
    }

    /**
     * Whether an API rejection was caused by the plan's product limit.
     * {@see \MoloniOn\Api\ApiClient} keeps the operation errors in the data.
     */
    public static function isApiError(ApiException $e): bool
    {
        foreach ($e->getData()['errors'] ?? [] as $error) {
            if (is_array($error) && ($error['msg'] ?? '') === self::API_ERROR) {
                return true;
            }
        }

        return false;
    }
}
