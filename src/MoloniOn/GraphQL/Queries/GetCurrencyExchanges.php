<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Finds the currency exchange between two ISO-4217 currencies, so a document
 * billed in a client currency other than the company's base currency can carry
 * the matching exchange id and rate.
 *
 * The pair is matched by the `pair` search field ("FROM TO", e.g. "EUR USD").
 */
class GetCurrencyExchanges extends AbstractOperation
{
    protected const OPERATION = 'currencyExchanges';

    protected const QUERY = <<<'GRAPHQL'
    query currencyExchanges($options: CurrencyExchangeOptions) {
        currencyExchanges(options: $options) {
            data {
                currencyExchangeId
                exchange
                from {
                    iso4217
                }
                to {
                    iso4217
                }
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * @param array{from?:string,to?:string} $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        $from = strtoupper(trim((string) ($data['from'] ?? '')));
        $to = strtoupper(trim((string) ($data['to'] ?? '')));

        return [
            'options' => [
                'search' => ['field' => 'pair', 'value' => $from . ' ' . $to],
                'pagination' => ['page' => 1, 'qty' => 50],
            ],
        ];
    }
}
