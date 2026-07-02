<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Returns a short-lived token + path to download a document PDF from the
 * Moloni media API. One query per document type: `<type>GetPDFToken`.
 */
class GetDocumentPdfToken extends AbstractOperation
{
    private string $operation;

    private string $query;

    public function __construct(string $documentType = 'invoice')
    {
        $this->operation = $documentType . 'GetPDFToken';

        $this->query = <<<GRAPHQL
        query {$this->operation}(\$documentId: Int!) {
            {$this->operation}(documentId: \$documentId) {
                data {
                    token
                    filename
                    path
                }
                errors {
                    field
                    msg
                }
            }
        }
        GRAPHQL;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function query(): string
    {
        return $this->query;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['documentId' => (int) ($data['documentId'] ?? 0)];
    }
}
