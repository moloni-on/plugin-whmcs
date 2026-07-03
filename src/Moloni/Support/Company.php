<?php

declare(strict_types=1);

namespace Moloni\Support;

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
