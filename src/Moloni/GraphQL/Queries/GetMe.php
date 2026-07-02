<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Returns the authenticated user and the companies they belong to.
 * Also used as a cheap connectivity/token check.
 */
class GetMe extends AbstractOperation
{
    protected const OPERATION = 'me';

    protected const QUERY = <<<'GRAPHQL'
    query {
        me {
            data {
                userCompanies {
                    company {
                        companyId
                    }
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
