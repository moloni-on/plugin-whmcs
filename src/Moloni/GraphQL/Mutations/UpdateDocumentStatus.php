<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Mutations;

use Moloni\GraphQL\AbstractOperation;

/**
 * Updates a document's status (e.g. draft -> closed).
 *
 * Like create, there is one update mutation per document type:
 * `<type>Update(companyId, data: <Type>Update!)`.
 */
class UpdateDocumentStatus extends AbstractOperation
{
    private string $operation;

    private string $query;

    public function __construct(string $documentType = 'invoice')
    {
        $this->operation = $documentType . 'Update';
        $inputType = ucfirst($documentType) . 'Update';

        $this->query = <<<GRAPHQL
        mutation {$this->operation}(\$companyId: Int!, \$data: {$inputType}!) {
            {$this->operation}(companyId: \$companyId, data: \$data) {
                data {
                    documentId
                    status
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
     * @param array{documentId:int,status:int} $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return [
            'data' => [
                'documentId' => (int) ($data['documentId'] ?? 0),
                'status' => (int) ($data['status'] ?? 0),
            ],
        ];
    }
}
