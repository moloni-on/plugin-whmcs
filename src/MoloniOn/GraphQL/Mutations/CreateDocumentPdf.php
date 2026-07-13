<?php

declare(strict_types=1);

namespace MoloniOn\GraphQL\Mutations;

use MoloniOn\GraphQL\AbstractOperation;

/**
 * Generates (exports) a document's PDF in Moloni ON.
 *
 * This must run before {@see \MoloniOn\GraphQL\Queries\GetDocumentPdfToken} on a
 * document whose PDF has not been exported yet — otherwise the token query is
 * rejected. One mutation per document type: `<type>GetPDF(companyId, documentId)`.
 * The mutation resolves to a bare scalar (no data/errors sub-selection).
 */
class CreateDocumentPdf extends AbstractOperation
{
    // Unlike the other operations (which hold a static GraphQL string in a QUERY
    // constant), the operation name here is derived from the document type at
    // construction, so the query is built into instance state instead.
    private string $operationName;

    private string $query;

    public function __construct(string $documentType = 'invoice')
    {
        $this->operationName = $documentType . 'GetPDF';

        $this->query = <<<GRAPHQL
        mutation {$this->operationName}(\$companyId: Int!, \$documentId: Int!) {
            {$this->operationName}(companyId: \$companyId, documentId: \$documentId)
        }
        GRAPHQL;
    }

    public function operation(): string
    {
        return $this->operationName;
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
