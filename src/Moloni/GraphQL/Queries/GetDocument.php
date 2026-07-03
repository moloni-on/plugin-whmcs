<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Fetches a single document's current state from Moloni ON.
 */
class GetDocument extends AbstractOperation
{
    protected const OPERATION = 'document';

    protected const QUERY = <<<'GRAPHQL'
    query document($companyId: Int!, $documentId: Int!, $options: DocumentOptionsSingle) {
        document(companyId: $companyId, documentId: $documentId, options: $options) {
            data {
                documentId
                status
                pdfExport
                documentType {
                    documentTypeId
                    apiCode
                    apiCodePlural
                }
                company {
                    companyId
                    slug
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
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['documentId' => (int) ($data['documentId'] ?? 0)];
    }
}
