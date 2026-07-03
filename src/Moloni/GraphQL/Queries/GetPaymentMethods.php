<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Lists payment methods — the whole list (for the settings dropdown) or filtered
 * by name to reuse one before creating it.
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
     * Pagination is always sent (the API rejects list queries without it); a
     * name search is added only when one is provided.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        $options = ['pagination' => ['page' => 1, 'qty' => 100]];

        if (!empty($data['name'])) {
            $options['search'] = ['field' => 'name', 'value' => (string) $data['name']];
        }

        return ['options' => $options];
    }
}
