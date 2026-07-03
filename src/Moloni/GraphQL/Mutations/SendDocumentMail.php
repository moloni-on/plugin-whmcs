<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Mutations;

use Moloni\GraphQL\AbstractOperation;

/**
 * E-mails a document to its recipient.
 *
 * Like create/update, there is one mutation per document type:
 * `<type>SendMail(companyId, documents: [Int]!, mailData: MailData)`. The
 * mutation resolves to a bare scalar (no data/errors node), so the query has no
 * sub-selection.
 */
class SendDocumentMail extends AbstractOperation
{
    private string $operationName;

    private string $query;

    public function __construct(string $documentType = 'invoice')
    {
        $this->operationName = $documentType . 'SendMail';

        $this->query = <<<GRAPHQL
        mutation {$this->operationName}(\$companyId: Int!, \$documents: [Int]!, \$mailData: MailData) {
            {$this->operationName}(companyId: \$companyId, documents: \$documents, mailData: \$mailData)
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
     * @param array{documentId?:int,name?:string,email?:string,message?:string} $data
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return [
            'documents' => [(int) ($data['documentId'] ?? 0)],
            'mailData' => [
                'to' => [
                    'name' => (string) ($data['name'] ?? ''),
                    'email' => (string) ($data['email'] ?? ''),
                ],
                'message' => (string) ($data['message'] ?? ''),
                'attachment' => true,
            ],
        ];
    }
}
