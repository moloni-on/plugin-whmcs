<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Api\MoloniClient;
use MoloniOn\Exceptions\ApiException;
use MoloniOn\Exceptions\ProductLimitException;
use MoloniOn\Services\ProductResolver;
use MoloniOn\Services\SettingsService;
use MoloniOn\Support\Context;
use MoloniOn\Support\Lang;
use PHPUnit\Framework\TestCase;

/**
 * The plan product-limit handling in ProductResolver: existing products keep
 * resolving on a full plan, a missing one is refused up front (no API call),
 * and Moloni ON's own limit rejection becomes ProductLimitException.
 */
final class ProductResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Lang::boot('en');
        Context::reset();
    }

    protected function tearDown(): void
    {
        Context::reset();
    }

    private function resolver(MoloniClient $client): ProductResolver
    {
        return new ProductResolver($client, $this->createMock(SettingsService::class));
    }

    /**
     * A capped plan (limit > 0) with the given remaining count. Tests that
     * need an uncapped plan build the company payload directly.
     */
    private function companyWithRemaining(int $remaining, int $limit = 10): void
    {
        Context::setCompany([
            'limits' => [['resource' => 'products', 'limit' => $limit, 'remaining' => $remaining, 'active' => true]],
        ]);
    }

    public function testExistingProductResolvesEvenOnAFullPlan(): void
    {
        $this->companyWithRemaining(0);
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(['productId' => 7]);
        $client->expects(self::never())->method('createProduct');

        self::assertSame(7, $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST'));
    }

    public function testMissingProductOnAFullPlanIsRefusedWithoutCallingTheApi(): void
    {
        $this->companyWithRemaining(0);
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(null);
        $client->expects(self::never())->method('createProduct');

        $this->expectException(ProductLimitException::class);
        $this->expectExceptionMessage('Could not create "HOST" in Moloni ON');

        $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST');
    }

    public function testApiLimitRejectionBecomesProductLimitException(): void
    {
        $this->companyWithRemaining(5); // stale count: Moloni ON says otherwise
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(null);
        $client->method('createProduct')->willThrowException(new ApiException(
            'Moloni ON API rejected the request.',
            ['errors' => [['field' => '*', 'msg' => 'Number of items is over the allowed limit.']], 'sent' => []]
        ));

        $this->expectException(ProductLimitException::class);

        $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST');
    }

    public function testOtherApiErrorsAreRethrownUnchanged(): void
    {
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(null);
        $original = new ApiException('Moloni ON API rejected the request.', ['errors' => [['msg' => 'Other']]]);
        $client->method('createProduct')->willThrowException($original);

        try {
            $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST');
            self::fail('Expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame($original, $e);
        }
    }

    public function testMissingProductOnAnUncappedPlanIsCreated(): void
    {
        // Moloni ON reports limit: 0, remaining: 0 for a resource with no cap
        // (unlimited plan); this must not be read as "full".
        Context::setCompany([
            'limits' => [['resource' => 'products', 'limit' => 0, 'remaining' => 0, 'active' => true]],
        ]);
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(null);
        $client->expects(self::once())->method('createProduct')->willReturn(['productId' => 11]);

        self::assertSame(11, $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST'));
    }

    public function testCreatesWhenThePlanHasRoom(): void
    {
        $this->companyWithRemaining(1);
        $client = $this->createMock(MoloniClient::class);
        $client->method('findProductByReference')->willReturn(null);
        $client->expects(self::once())->method('createProduct')->willReturn(['productId' => 9]);

        self::assertSame(9, $this->resolver($client)->resolveId('Hosting', 10.0, [], '', 'HOST'));
    }
}
