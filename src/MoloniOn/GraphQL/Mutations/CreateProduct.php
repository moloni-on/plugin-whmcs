<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Mutations;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Creates a product in Moloni ON.
 *
 * NOTE: the exact `ProductInsert` field set should be validated against the
 * live Moloni ON schema — the fields mapped here (name, reference, price,
 * type, hasStock, measurementUnitId, categoryId, taxes) reflect the platform's
 * conventions but were not verifiable offline. Adjust {@see variables()} and
 * the caller if the schema differs.
 */
class CreateProduct extends AbstractOperation
{
    protected const OPERATION = 'productCreate';

    protected const QUERY = <<<'GRAPHQL'
    mutation productCreate($companyId: Int!, $data: ProductInsert!) {
        productCreate(companyId: $companyId, data: $data) {
            data {
                productId
                name
                reference
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array<string,mixed> $data Already-mapped ProductInsert fields.
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
