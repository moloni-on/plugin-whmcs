<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\Company;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function testAccessorsReadThePayload(): void
    {
        $company = new Company([
            'companyId' => 42,
            'name' => 'Acme Lda',
            'country' => ['countryId' => 1],
        ]);

        self::assertSame(42, $company->getCompanyId());
        self::assertSame('Acme Lda', $company->get('name'));
        self::assertSame(1, $company->getCountry());
        self::assertNull($company->get('missing'));
    }

    public function testMissingIdsDefaultToZero(): void
    {
        $company = new Company([]);

        self::assertSame(0, $company->getCompanyId());
        self::assertSame(0, $company->getCountry());
    }

    public function testHasApiClientReflectsActiveLimit(): void
    {
        $with = new Company(['limits' => [['moduleId' => 'tools.apiClients', 'active' => true]]]);
        $without = new Company(['limits' => [['moduleId' => 'tools.apiClients', 'active' => false]]]);
        $none = new Company(['limits' => []]);

        self::assertTrue($with->hasApiClient());
        self::assertFalse($without->hasApiClient());
        self::assertFalse($none->hasApiClient());
    }

    public function testHasWebhooksReflectsActiveLimit(): void
    {
        $company = new Company(['limits' => [['moduleId' => 'tools.webhooks', 'active' => true]]]);

        self::assertTrue($company->hasWebhooks());
        self::assertFalse($company->hasApiClient());
    }

    public function testUntrackedLimitsAreDroppedButOtherDataKept(): void
    {
        $company = new Company([
            'name' => 'Acme Lda',
            'limits' => [
                ['moduleId' => 'tools.apiClients', 'active' => true],
                ['moduleId' => 'plugins.woocommerce', 'active' => true],
                ['moduleId' => 'productsServices.stocks', 'active' => true],
            ],
        ]);

        $limits = $company->getAll()['limits'];

        self::assertCount(1, $limits);
        self::assertSame('tools.apiClients', array_values($limits)[0]['moduleId']);
        self::assertSame('Acme Lda', $company->get('name'));
    }

    public function testProductsLimitEntryIsKeptAlongsideTrackedModules(): void
    {
        $company = new Company([
            'limits' => [
                ['moduleId' => 'tools.webhooks', 'resource' => null, 'remaining' => 0, 'active' => true],
                [
                    'moduleId' => 'productsServices.products',
                    'resource' => 'products',
                    'remaining' => 0,
                    'active' => true,
                ],
                ['moduleId' => 'productsServices.stocks', 'resource' => null, 'remaining' => 0, 'active' => true],
            ],
        ]);

        self::assertCount(2, $company->getAll()['limits']);
        self::assertTrue($company->hasWebhooks());
    }

    public function testCanCreateProductsAllowsAnUncappedPlan(): void
    {
        // Moloni ON reports limit: 0, remaining: 0 for a resource with no cap
        // (unlimited plan) — this must not be read as "full".
        $uncapped = new Company(['limits' => [['resource' => 'products', 'limit' => 0, 'remaining' => 0]]]);

        self::assertTrue($uncapped->canCreateProducts());
    }

    public function testCanCreateProductsBlocksACappedPlanWithNothingLeft(): void
    {
        $full = new Company(['limits' => [['resource' => 'products', 'limit' => 10, 'remaining' => 0]]]);

        self::assertFalse($full->canCreateProducts());
    }

    public function testCanCreateProductsAllowsACappedPlanWithRoomLeft(): void
    {
        $withRoom = new Company(['limits' => [['resource' => 'products', 'limit' => 10, 'remaining' => 3]]]);

        self::assertTrue($withRoom->canCreateProducts());
    }

    public function testCanCreateProductsDoesNotBlockWithoutAProductsEntry(): void
    {
        self::assertTrue((new Company([]))->canCreateProducts());
        $withoutProducts = new Company(['limits' => [['moduleId' => 'tools.apiClients', 'active' => true]]]);

        self::assertTrue($withoutProducts->canCreateProducts());
    }
}
