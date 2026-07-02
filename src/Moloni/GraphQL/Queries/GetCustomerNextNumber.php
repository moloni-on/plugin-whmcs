<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Returns the next sequential customer number, required when creating a
 * customer.
 */
class GetCustomerNextNumber extends AbstractOperation
{
    protected const OPERATION = 'customerNextNumber';

    protected const QUERY = <<<'GRAPHQL'
    query customerNextNumber($companyId: Int!, $options: GetNextCustomerNumberOptions) {
        customerNextNumber(companyId: $companyId, options: $options) {
            data
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;
}
