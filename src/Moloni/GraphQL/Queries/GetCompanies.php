<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Lists all companies the authenticated user can access.
 */
class GetCompanies extends AbstractOperation
{
    protected const OPERATION = 'companies';

    protected const QUERY = <<<'GRAPHQL'
    query companies($options: CompanyOptions) {
        companies(options: $options) {
            data {
                companyId
                name
                email
                slug
                img1
                address
                isConfirmed
                zipCode
                city
                vat
                country {
                    countryId
                    title
                }
                limits {
                    moduleId
                    active
                }
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;
}
