<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Searches products, typically by reference, to detect an existing product
 * before creating one.
 */
class GetProducts extends AbstractOperation
{
    protected const OPERATION = 'products';

    protected const QUERY = <<<'GRAPHQL'
    query products($companyId: Int!, $options: ProductOptions) {
        products(companyId: $companyId, options: $options) {
            data {
                productId
                name
                reference
                price
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

        if (!empty($data['reference'])) {
            $variables['options'] = [
                'filter' => [
                    ['field' => 'reference', 'comparison' => 'eq', 'value' => (string) $data['reference']],
                    ['field' => 'visible', 'comparison' => 'in', 'value' => '[0, 1]'],
                ],
            ];
        }

        return $variables;
    }
}
