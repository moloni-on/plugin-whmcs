<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Fetches a single company's details (fiscal zone, currency, defaults).
 */
class GetCompany extends AbstractOperation
{
    protected const OPERATION = 'company';

    protected const QUERY = <<<'GRAPHQL'
    query company($companyId: Int!, $options: CompanyOptionsSingle) {
        company(companyId: $companyId, options: $options) {
            data {
                companyId
                isConfirmed
                name
                email
                address
                city
                zipCode
                slug
                vat
                fiscalZone {
                    fiscalZone
                }
                country {
                    countryId
                }
                currency {
                    currencyId
                    iso4217
                }
                limits {
                    moduleId
                    active
                }
            }
            options {
                defaultLanguageId
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
        return ['companyId' => (int) ($data['companyId'] ?? 0)];
    }
}
