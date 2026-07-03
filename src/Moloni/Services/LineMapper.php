<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Models\Whmcs;

/**
 * Maps a WHMCS invoice line to its Moloni ON document-line metadata:
 * name, a stable product reference, a summary, a discount percentage and
 * whether the line should be skipped entirely.
 *
 * The reference is derived from the WHMCS line `type` (+ `relid`) so equivalent
 * items reuse one Moloni product instead of spawning a new product per
 * description — e.g. every ".com" registration is `REG-COM`, all hosting is
 * `Alojamento`. Promotions (which WHMCS stores as separate negative lines) are
 * folded into the discounted line's percentage and skipped as their own lines.
 */
class LineMapper
{
    private const PROMO_DOMAIN = 'PromoDomain';
    private const PROMO_HOSTING = 'PromoHosting';

    /** Default reference for a hosting line when no custom reference applies. */
    private const HOSTING_REFERENCE = 'Alojamento';

    private SettingsService $settings;

    /** @var array<string,string|null> Memoised custom-field descriptions, keyed "field|package". */
    private array $referenceCache = [];

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param object $item tblinvoiceitems row (type, relid, description, amount, duedate)
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    public function map($item, int $invoiceId): array
    {
        $type = (string) ($item->type ?? '');
        $relId = (int) ($item->relid ?? 0);

        switch ($type) {
            case 'DomainTransfer':
                return $this->domain($item, $invoiceId, $relId, 'Transferência de Domínio', 'T-', false);
            case 'DomainRegister':
                return $this->domain($item, $invoiceId, $relId, 'Registo de Domínio', 'REG-', true);
            case 'Domain':
                return $this->domain($item, $invoiceId, $relId, 'Renovação de Domínio', 'REN-', true);
            case 'Addon':
                return $this->addon($item, $relId);
            case 'Upgrade':
                return $this->upgrade($relId);
            case 'Hosting':
                return $this->hosting($item, $invoiceId, $relId);
            case 'Setup':
                return $this->line('Taxa de Instalação', 'TAX-INSTALL');
            case 'AddFunds':
                return $this->line('', 'ADD-FUNDS');
            case 'LateFee':
                return $this->line('', 'LATE-FEE');
            case self::PROMO_DOMAIN:
            case self::PROMO_HOSTING:
                // Promotions are folded into the discounted line, not billed on their own.
                return $this->line('', '', '', 0.0, true);
            case 'Item':
            case '':
                return $this->line('', '9999');
            default:
                // Unknown type: let the caller derive a reference from the name.
                return $this->line('', '');
        }
    }

    /**
     * Domain lines share a reference per TLD, e.g. `REG-COM`, `REN-CO.UK`.
     *
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    private function domain($item, int $invoiceId, int $relId, string $name, string $prefix, bool $withDates): array
    {
        $tld = '';
        $summary = '';
        $domain = Whmcs::getDomainInfo($relId);

        if ($domain !== null && !empty($domain->domain)) {
            $parts = explode('.', (string) $domain->domain, 2);
            $tld = $parts[1] ?? '';
            $summary = (string) $domain->domain;

            if ($withDates) {
                $summary .= $this->dates($item, $domain->nextduedate ?? null);
            }
        }

        $reference = rtrim($prefix . strtoupper($tld), '-');
        $discount = $this->discount($item, $invoiceId, $relId, self::PROMO_DOMAIN);

        return $this->line($name, $reference, $summary, $discount);
    }

    /**
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    private function addon($item, int $relId): array
    {
        $addon = Whmcs::getAddonInfo($relId);

        if ($addon !== null && !empty($addon->name)) {
            $summary = (string) ($addon->domain ?? '');
            $summary .= $this->dates($item, $addon->nextduedate ?? null);

            return $this->line((string) $addon->name, $this->referenceFromName((string) $addon->name), $summary);
        }

        return $this->line((string) ($item->description ?? ''), 'Addon');
    }

    /**
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    private function upgrade(int $relId): array
    {
        $upgrade = Whmcs::getUpgradeInfo($relId);
        $productName = $upgrade !== null ? (string) ($upgrade->name ?? '') : '';

        return $this->line('Upgrade/Downgrade - ' . $productName, 'UPGRADE');
    }

    /**
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    private function hosting($item, int $invoiceId, int $relId): array
    {
        $hosting = Whmcs::getHostingInfo($relId);
        $name = $hosting !== null ? (string) ($hosting->name ?? '') : '';
        $summary = '';

        if ($hosting !== null) {
            $summary = (string) ($hosting->domain ?? '');
            $summary .= $this->dates($item, $hosting->nextduedate ?? null);
        }

        $discount = $this->discount($item, $invoiceId, $relId, self::PROMO_HOSTING);
        $reference = $this->hostingReference($hosting);

        return $this->line(
            $name !== '' ? $name : (string) ($item->description ?? ''),
            $reference,
            $summary,
            $discount
        );
    }

    /**
     * A hosting line's reference: the product custom-field description named by
     * the `custom_reference` setting, falling back to the default reference.
     *
     * @param object|null $hosting tblhosting row (carries packageid)
     */
    private function hostingReference($hosting): string
    {
        $fieldName = $this->settings->customReference();
        $packageId = (int) ($hosting->packageid ?? 0);

        if ($fieldName !== '' && $packageId > 0) {
            $custom = $this->customReferenceFor($fieldName, $packageId);

            if ($custom !== null) {
                return $custom;
            }
        }

        return self::HOSTING_REFERENCE;
    }

    /**
     * A product's custom-field description, memoised per (field, package) so a
     * bulk run over many orders/lines that share a package hits the DB once.
     */
    private function customReferenceFor(string $fieldName, int $packageId): ?string
    {
        $key = $fieldName . '|' . $packageId;

        if (!array_key_exists($key, $this->referenceCache)) {
            $this->referenceCache[$key] = Whmcs::productCustomFieldDescription($packageId, $fieldName);
        }

        return $this->referenceCache[$key];
    }

    /**
     * The promotion applied to a line, expressed as a percentage (0-100) of the
     * line amount. WHMCS stores promotions as separate negative line items.
     */
    private function discount($item, int $invoiceId, int $relId, string $promoType): float
    {
        $amount = (float) ($item->amount ?? 0);
        $promo = Whmcs::getLineDiscountAmount($invoiceId, $relId, $promoType);

        if ($amount <= 0.0 || $promo <= 0.0) {
            return 0.0;
        }

        return min(100.0, ($promo * 100) / $amount);
    }

    /**
     * A " duedate - nextduedate" suffix for a line summary, when both are known.
     */
    private function dates($item, ?string $nextDueDate): string
    {
        $dueDate = trim((string) ($item->duedate ?? ''));
        $nextDueDate = trim((string) ($nextDueDate ?? ''));

        if ($dueDate === '' && $nextDueDate === '') {
            return '';
        }

        return ' (' . $dueDate . ' - ' . $nextDueDate . ')';
    }

    /**
     * Build a reference from a product/addon name: the first three characters of
     * each alphanumeric word, joined by dashes (e.g. "cPanel Pro" -> "cPa-Pro").
     */
    private function referenceFromName(string $name): string
    {
        $clean = (string) preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
        $words = preg_split('/\s+/', trim($clean)) ?: [];
        $parts = [];

        foreach ($words as $word) {
            if ($word !== '') {
                $parts[] = substr($word, 0, 3);
            }
        }

        return implode('-', $parts);
    }

    /**
     * @return array{name:string,reference:string,summary:string,discount:float,skip:bool}
     */
    private function line(
        string $name,
        string $reference,
        string $summary = '',
        float $discount = 0.0,
        bool $skip = false
    ): array {
        return [
            'name' => $name,
            'reference' => $reference,
            'summary' => $summary,
            'discount' => $discount,
            'skip' => $skip,
        ];
    }
}
