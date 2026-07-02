<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Searches customers, typically filtered by VAT to detect an existing client
 * before creating a new one.
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
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        $variables = [];

        if (!empty($data['vat'])) {
            $variables['options'] = [
                'search' => [
                    'field' => 'vat',
                    'value' => (string) $data['vat'],
                ],
            ];
        }

        return $variables;
    }
}
