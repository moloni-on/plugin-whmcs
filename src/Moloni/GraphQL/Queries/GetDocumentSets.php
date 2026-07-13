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
            }
            errors {
                field
                msg
            }
        }
    }
    GRAPHQL;

    /**
     * The API rejects the query unless pagination options are provided, so
     * fetch the whole list in a single generous page.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['options' => ['pagination' => ['page' => 1, 'qty' => 100]]];
    }
}
