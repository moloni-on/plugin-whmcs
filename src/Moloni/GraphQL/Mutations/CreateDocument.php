<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Mutations;

use Moloni\GraphQL\AbstractOperation;

/**
 * Creates a document in Moloni ON.
 *
 * Moloni ON exposes one create mutation per document type, all sharing the
 * same shape: `<type>Create(companyId, data: <Type>Insert!)`. This class
 * builds the operation name and input type from the document type code
 * (e.g. "simplifiedInvoice" -> simplifiedInvoiceCreate / SimplifiedInvoiceInsert).
 */
class CreateDocument extends AbstractOperation
{
    private string $operation;

    private string $query;

    public function __construct(string $documentType = 'invoice')
    {
        $this->operation = $documentType . 'Create';
        $inputType = ucfirst($documentType) . 'Insert';

        $this->query = <<<GRAPHQL
        mutation {$this->operation}(\$companyId: Int!, \$data: {$inputType}!) {
            {$this->operation}(companyId: \$companyId, data: \$data) {
                data {
                    documentId
                    number
                    totalValue
                    documentTotal
                    ourReference
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
     * @param array<string,mixed> $data Already-mapped <Type>Insert fields.
     * @return array<string,mixed>
     */
    public function variables(array $data = []): array
    {
        return ['data' => $data];
    }
}
