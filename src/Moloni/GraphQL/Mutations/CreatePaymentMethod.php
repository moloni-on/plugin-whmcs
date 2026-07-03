<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Mutations;

use Moloni\GraphQL\AbstractOperation;

/**
 * Creates a payment method in Moloni ON (by name).
 */
class CreatePaymentMethod extends AbstractOperation
{
    protected const OPERATION = 'paymentMethodCreate';

    protected const QUERY = <<<'GRAPHQL'
    mutation paymentMethodCreate($companyId: Int!, $data: PaymentMethodInsert!) {
        paymentMethodCreate(companyId: $companyId, data: $data) {
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
     * @param array<string,mixed> $data Already-mapped PaymentMethodInsert fields.
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
