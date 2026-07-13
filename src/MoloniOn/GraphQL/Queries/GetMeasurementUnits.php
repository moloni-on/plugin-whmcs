<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Lists the company's measurement units, used to populate the product-mapping
 * "measurement unit" setting on the config page.
 */
class GetMeasurementUnits extends AbstractOperation
{
    protected const OPERATION = 'measurementUnits';

    protected const QUERY = <<<'GRAPHQL'
    query measurementUnits($companyId: Int!, $options: MeasurementUnitOptions) {
        measurementUnits(companyId: $companyId, options: $options) {
            data {
                measurementUnitId
                name
                abbreviation
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * The API rejects list queries without pagination, so fetch the whole list
     * in a single generous page.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['options' => ['pagination' => ['page' => 1, 'qty' => 100]]];
    }
}
