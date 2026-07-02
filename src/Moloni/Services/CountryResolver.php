<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Exceptions\ApiException;
use Throwable;

/**
 * Maps a WHMCS ISO-3166-1 alpha-2 country code (e.g. "PT") to the Moloni ON
 * countryId and default languageId. The country list is fetched once and
 * cached for the request.
 */
class CountryResolver
{
    private MoloniClient $client;

    /** @var array<string,array{countryId:?int,languageId:?int}> */
    private array $cache = [];

    private bool $loaded = false;

    public function __construct(MoloniClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array{countryId:?int,languageId:?int}
     */
    public function resolve(string $iso2): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->cache[strtoupper(trim($iso2))] ?? ['countryId' => null, 'languageId' => null];
    }

    private function load(): void
    {
        $this->loaded = true;

        try {
            $countries = $this->client->getCountries();
        } catch (ApiException | Throwable $e) {
            return;
        }

        foreach ($countries as $country) {
            $iso = strtoupper((string) ($country['iso3166_1'] ?? ''));

            if ($iso === '') {
                continue;
            }

            $this->cache[$iso] = [
                'countryId' => ((int) ($country['countryId'] ?? 0)) ?: null,
                'languageId' => ((int) ($country['language']['languageId'] ?? 0)) ?: null,
            ];
        }
    }
}
