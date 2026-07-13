<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Mutations;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Creates a VAT tax for a given rate + fiscal zone, used as a fallback when no
 * matching tax already exists in the company.
 */
class CreateTax extends AbstractOperation
{
    protected const OPERATION = 'taxCreate';

    protected const QUERY = <<<'GRAPHQL'
    mutation taxCreate($companyId: Int!, $data: TaxInsert!) {
        taxCreate(companyId: $companyId, data: $data) {
            data {
                taxId
                name
                value
                type
                fiscalZone
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array<string,mixed> $data Already-mapped TaxInsert fields.
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
