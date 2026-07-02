<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;
use Moloni\GraphQL\AbstractOperation;

/**
 * Finds a VAT tax by its rate within a fiscal zone, so a document line can
 * reference the tax that matches the WHMCS order's tax rate.
 */
class GetTaxes extends AbstractOperation
{
    protected const OPERATION = 'taxes';

    protected const QUERY = <<<'GRAPHQL'
    query taxes($companyId: Int!, $options: TaxOptions) {
        taxes(companyId: $companyId, options: $options) {
            data {
                taxId
                name
                value
                type
                fiscalZone
                fiscalZoneFinanceType
                isDefault
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array{rate?:float|int|string,code?:string} $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        $rate = (string) ($data['rate'] ?? '0');
        $code = strtolower((string) ($data['code'] ?? 'pt'));

        return [
            'options' => [
                'filter' => [
                    ['field' => 'value', 'comparison' => 'eq', 'value' => $rate],
                    ['field' => 'flags', 'comparison' => 'eq', 'value' => '0'],
                    ['field' => 'type', 'comparison' => 'eq', 'value' => (string) TaxType::PERCENTAGE],
                    [
                        'field' => 'fiscalZoneFinanceType',
                        'comparison' => 'eq',
                        'value' => (string) TaxFiscalZoneType::VAT,
                    ],
                ],
                'search' => ['field' => 'fiscalZone', 'value' => $code],
            ],
        ];
    }
}
