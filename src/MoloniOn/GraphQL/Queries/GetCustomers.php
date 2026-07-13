<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Searches customers by VAT or e-mail to detect an existing client before
 * creating a new one.
 */
class GetCustomers extends AbstractOperation
{
    protected const OPERATION = 'customers';

    protected const QUERY = <<<'GRAPHQL'
    query customers($companyId: Int!, $options: CustomerOptions) {
        customers(companyId: $companyId, options: $options) {
            data {
                customerId
                name
                number
                vat
                email
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * Accepts a single {field, value} search pair (e.g. vat or email).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        foreach (['vat', 'email'] as $field) {
            if (!empty($data[$field])) {
                return [
                    'options' => [
                        'search' => [
                            'field' => $field,
                            'value' => (string) $data[$field],
                        ],
                    ],
                ];
            }
        }

        return [];
    }
}
