<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Lists all companies the authenticated user can access.
 */
class GetCompanies extends AbstractOperation
{
    protected const OPERATION = 'companies';

    protected const QUERY = <<<'GRAPHQL'
    query whmcsCompanies($options: CompanyOptions) {
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
                    resource
                    limit
                    remaining
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
