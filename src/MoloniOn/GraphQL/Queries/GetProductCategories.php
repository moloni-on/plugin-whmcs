<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Lists the company's root product categories (those without a parent), used to
 * populate the product-mapping "product category" setting on the config page.
 */
class GetProductCategories extends AbstractOperation
{
    protected const OPERATION = 'productCategories';

    protected const QUERY = <<<'GRAPHQL'
    query productCategories($companyId: Int!, $options: ProductCategoryOptions) {
        productCategories(companyId: $companyId, options: $options) {
            data {
                productCategoryId
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
     * Filter to root categories (a null parent) and page through the list.
     * The API rejects list queries without pagination.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return [
            'options' => [
                'filter' => [
                    ['field' => 'parentId', 'comparison' => 'eq', 'value' => null],
                ],
                'pagination' => ['page' => 1, 'qty' => 100],
            ],
        ];
    }
}
