<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * Read-only view over a Moloni ON company payload.
 *
 * Wraps the `company`/`companies` GraphQL node and answers the feature
 * questions that gate the module: whether the company has bought the API
 * client, webhooks, etc. Permissions come from the company's `limits`
 * (a list of `{moduleId, active}` entries).
 *
 * Loaded once per request into {@see Context} and also built ad-hoc per row
 * on the company-selection screen. Mirrors the WordPress module's
 * `MoloniOn\Context\Company`.
 */
final class Company
{
    /** Permission `moduleId`s this module cares about; the rest are dropped. */
    private const TARGET_PERMISSIONS = [
        'tools.apiClients',
        'tools.webhooks',
    ];

    /** @var array<string,mixed> */
    private array $company;

    /**
     * @param array<string,mixed> $company The `company`/`companies` `data` node.
     */
    public function __construct(array $company)
    {
        foreach ($company['limits'] ?? [] as $key => $limit) {
            if (in_array($limit['moduleId'] ?? '', self::TARGET_PERMISSIONS, true)) {
                continue;
            }

            unset($company['limits'][$key]);
        }

        $this->company = $company;
    }

    // ---- Accessors --------------------------------------------------------

    /**
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->company[$key] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAll(): array
    {
        return $this->company;
    }

    public function getCompanyId(): int
    {
        return (int) ($this->company['companyId'] ?? 0);
    }

    public function getCountry(): int
    {
        return (int) ($this->company['country']['countryId'] ?? 0);
    }

    /**
     * The company's fiscal zone code (e.g. "PT"), upper-cased, or "" when absent.
     */
    public function getFiscalZone(): string
    {
        return strtoupper((string) ($this->company['fiscalZone']['fiscalZone'] ?? ''));
    }

    /**
     * The company's base currency as an ISO-4217 code (e.g. "EUR"), upper-cased,
     * or "" when absent. Documents are stored in this currency.
     */
    public function getCurrencyCode(): string
    {
        return strtoupper((string) ($this->company['currency']['iso4217'] ?? ''));
    }

    /**
     * Predefined tax-exemption reasons for the company's fiscal zone, as
     * `{code,name}` rows. Empty when the zone has no predefined list (in which
     * case the exemption reason is entered as free text). Portugal, for example,
     * returns the legally-defined M-codes.
     *
     * @return array<int,array{code:string,name:string}>
     */
    public function getExemptionReasons(): array
    {
        $reasons = $this->company['fiscalZone']['exemption']['reasons'] ?? [];

        return is_array($reasons) ? array_values($reasons) : [];
    }

    // ---- Permissions ------------------------------------------------------

    public function hasApiClient(): bool
    {
        return $this->isAllowed('tools.apiClients');
    }

    public function hasWebhooks(): bool
    {
        return $this->isAllowed('tools.webhooks');
    }

    /**
     * Whether the given `moduleId` is present and active in the company limits.
     */
    private function isAllowed(string $resource): bool
    {
        foreach ($this->company['limits'] ?? [] as $limit) {
            if (($limit['moduleId'] ?? '') !== $resource) {
                continue;
            }

            return ($limit['active'] ?? false) === true;
        }

        return false;
    }
}
