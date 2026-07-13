<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Mutations;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Creates a customer in Moloni ON.
 */
class CreateCustomer extends AbstractOperation
{
    protected const OPERATION = 'customerCreate';

    protected const QUERY = <<<'GRAPHQL'
    mutation customerCreate($companyId: Int!, $data: CustomerInsert!) {
        customerCreate(companyId: $companyId, data: $data) {
            data {
                customerId
                name
                vat
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array<string,mixed> $data Already-mapped CustomerInsert fields.
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
