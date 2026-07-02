<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Lists the company's document sets (séries), used when creating documents.
 */
class GetDocumentSets extends AbstractOperation
{
    protected const OPERATION = 'documentSets';

    protected const QUERY = <<<'GRAPHQL'
    query documentSets($companyId: Int!, $options: DocumentSetOptions) {
        documentSets(companyId: $companyId, options: $options) {
            data {
                documentSetId
                name
                isDefault
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;
}
