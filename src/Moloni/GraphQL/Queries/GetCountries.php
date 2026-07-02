<?php

declare(strict_types=1);

namespace Moloni\GraphQL\Queries;

use Moloni\GraphQL\AbstractOperation;

/**
 * Lists Moloni ON countries with their default language, used to map a WHMCS
 * ISO-3166-1 alpha-2 country code to a Moloni countryId/languageId.
 */
class GetCountries extends AbstractOperation
{
    protected const OPERATION = 'countries';

    protected const QUERY = <<<'GRAPHQL'
    query countries($options: CountryOptions) {
        countries(options: $options) {
            data {
                countryId
                iso3166_1
                title
                language {
                    languageId
                    name
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
        // Fetch the whole list in one page (there are ~250 countries).
        return ['options' => ['pagination' => ['page' => 1, 'qty' => 500]]];
    }
}
