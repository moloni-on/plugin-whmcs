<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Mutations;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Updates an existing customer in Moloni ON.
 */
class UpdateCustomer extends AbstractOperation
{
    protected const OPERATION = 'customerUpdate';

    protected const QUERY = <<<'GRAPHQL'
    mutation customerUpdate($companyId: Int!, $data: CustomerUpdate!) {
        customerUpdate(companyId: $companyId, data: $data) {
            data {
                customerId
                name
                number
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array<string,mixed> $data Already-mapped CustomerUpdate fields (must include customerId).
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
