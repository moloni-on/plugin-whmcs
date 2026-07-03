<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Searches payment methods by name to reuse one before creating it.
 */
class GetPaymentMethods extends AbstractOperation
{
    protected const OPERATION = 'paymentMethods';

    protected const QUERY = <<<'GRAPHQL'
    query paymentMethods($companyId: Int!, $options: PaymentMethodOptions) {
        paymentMethods(companyId: $companyId, options: $options) {
            data {
                paymentMethodId
                name
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

        if (!empty($data['name'])) {
            $variables['options'] = [
                'search' => [
                    'field' => 'name',
                    'value' => (string) $data['name'],
                ],
            ];
        }

        return $variables;
    }
}
