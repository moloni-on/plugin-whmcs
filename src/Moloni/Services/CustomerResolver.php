<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Exceptions\ApiException;
use Moloni\Exceptions\DocumentException;
use Moloni\Facades\LoggerFacade;
use Moloni\Models\Whmcs;

/**
 * Resolves the Moloni ON customer for a WHMCS client, creating or updating it.
 *
 * Lookup mirrors the Moloni ON WooCommerce plugin: search by VAT when present,
 * otherwise by e-mail; when neither is known a new customer is always created.
 * An already-existing customer is always updated with the latest WHMCS details.
 * The VAT is read from a configurable client custom field, falling back to the
 * native tblclients.tax_id.
 */
class CustomerResolver
{
    private MoloniClient $client;

    private SettingsService $settings;

    private CountryResolver $countries;

    public function __construct(MoloniClient $client, SettingsService $settings, CountryResolver $countries)
    {
        $this->client = $client;
        $this->settings = $settings;
        $this->countries = $countries;
    }

    /**
     * @param object|null $whmcsClient tblclients row
     * @return int The Moloni customer id.
     * @throws ApiException
     * @throws DocumentException
     */
    public function resolve($whmcsClient): int
    {
        if ($whmcsClient === null) {
            throw new DocumentException('Order has no associated WHMCS client.');
        }

        $vat = $this->resolveVat($whmcsClient);
        $email = trim((string) ($whmcsClient->email ?? ''));

        $data = $this->mapClient($whmcsClient, $vat, $email);
        $existing = $this->findExisting($vat, $email);

        if ($existing !== null && !empty($existing['customerId'])) {
            $data['customerId'] = (int) $existing['customerId'];

            return $this->extractId($this->client->updateCustomer($data), 'updated');
        }

        $data['number'] = $this->client->getCustomerNextNumber() ?? '';

        return $this->extractId($this->client->createCustomer($data), 'created');
    }

    /**
     * VAT from the configured client custom field, falling back to the native
     * tax_id. Null when neither is set.
     */
    private function resolveVat($whmcsClient): ?string
    {
        $field = $this->settings->vatField();

        if ($field !== '') {
            $custom = Whmcs::getClientCustomFieldValue((int) ($whmcsClient->id ?? 0), $field);

            if ($custom !== null) {
                return $custom;
            }
        }

        $taxId = trim((string) ($whmcsClient->tax_id ?? ''));

        return $taxId !== '' ? $taxId : null;
    }

    /**
     * Search for an existing customer: by VAT when present, else by e-mail.
     * Returns null (forcing a create) when neither identifier is available.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    private function findExisting(?string $vat, string $email): ?array
    {
        if ($vat !== null && $vat !== '') {
            return $this->client->findCustomerByVat($vat);
        }

        if ($email !== '') {
            return $this->client->findCustomerByEmail($email);
        }

        return null;
    }

    /**
     * Map a WHMCS client to the Moloni CustomerInsert/CustomerUpdate fields.
     *
     * @return array<string,mixed>
     */
    private function mapClient($whmcsClient, ?string $vat, string $email): array
    {
        $name = trim((string) ($whmcsClient->companyname ?? ''));
        $contactName = trim(($whmcsClient->firstname ?? '') . ' ' . ($whmcsClient->lastname ?? ''));

        if ($name === '') {
            $name = $contactName;
        }

        $country = strtoupper(trim((string) ($whmcsClient->country ?? '')));

        $data = [
            'name' => $name !== '' ? $name : 'Customer',
            'email' => $email,
            'vat' => $vat,
            'address' => $this->address($whmcsClient),
            'city' => (string) ($whmcsClient->city ?? ''),
            'zipCode' => $this->validateZip((string) ($whmcsClient->postcode ?? ''), $country),
            'phone' => (string) ($whmcsClient->phonenumber ?? ''),
            'contactName' => $contactName,
        ];

        $country = $this->countries->resolve((string) ($whmcsClient->country ?? ''));

        if ($country['countryId'] !== null) {
            $data['countryId'] = $country['countryId'];
        }

        if ($country['languageId'] !== null) {
            $data['languageId'] = $country['languageId'];
        }

        return $data;
    }

    private function address($whmcsClient): string
    {
        $address = trim((string) ($whmcsClient->address1 ?? ''));
        $address2 = trim((string) ($whmcsClient->address2 ?? ''));

        if ($address2 !== '') {
            $address = trim($address . ' ' . $address2);
        }

        return $address;
    }

    /**
     * Portuguese postcodes must be in the `NNNN-NNN` form for Moloni ON to
     * accept them, so coerce PT zips into that shape (defaulting to "1000-100"
     * when unusable). Non-PT zips are passed through unchanged. Ported from the
     * classic Moloni WHMCS plugin.
     */
    private function validateZip(string $zipCode, string $country): string
    {
        if ($country !== 'PT') {
            return $zipCode;
        }

        $digits = (string) preg_replace('/[^0-9]/', '', $zipCode);

        if ($digits === '') {
            LoggerFacade::warning('PT customer has no usable postcode; defaulted to 1000-100.', [
                'postcode' => $zipCode,
            ]);

            return '1000-100';
        }

        // Keep the leading digits and pad a partial code so it still yields the
        // NNNN-NNN shape Moloni ON requires for PT.
        $digits = str_pad(substr($digits, 0, 7), 7, '0');

        return substr($digits, 0, 4) . '-' . substr($digits, 4, 3);
    }

    /**
     * @param array<string,mixed> $result
     * @throws ApiException
     */
    private function extractId(array $result, string $action): int
    {
        $customerId = (int) ($result['customerId'] ?? 0);

        if ($customerId <= 0) {
            throw new ApiException(
                'Moloni ON did not return a customer id (' . $action . ').',
                ['response' => $result]
            );
        }

        return $customerId;
    }
}
