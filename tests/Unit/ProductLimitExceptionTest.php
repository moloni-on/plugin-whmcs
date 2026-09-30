<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Exceptions\ApiException;
use MoloniOn\Exceptions\ProductLimitException;
use MoloniOn\Support\Lang;
use PHPUnit\Framework\TestCase;

final class ProductLimitExceptionTest extends TestCase
{
    private function rejection(array $errors): ApiException
    {
        // The shape ApiClient::assertNoErrors() throws for per-operation errors.
        return new ApiException('Moloni ON API rejected the request.', ['errors' => $errors, 'sent' => []]);
    }

    public function testRecognisesThePlanLimitError(): void
    {
        self::assertTrue(ProductLimitException::isApiError(
            $this->rejection([['field' => '*', 'msg' => 'Number of items is over the allowed limit.']])
        ));
        self::assertTrue(ProductLimitException::isApiError($this->rejection([
            ['field' => 'reference', 'msg' => 'Reference already exists'],
            ['field' => '*', 'msg' => 'Number of items is over the allowed limit.'],
        ])));
    }

    public function testDoesNotMatchOtherErrors(): void
    {
        self::assertFalse(ProductLimitException::isApiError(
            $this->rejection([['field' => 'reference', 'msg' => 'Reference already exists']])
        ));
        self::assertFalse(ProductLimitException::isApiError(new ApiException('Moloni ON API error.')));
    }

    public function testCarriesATranslatedMessageAndTheReference(): void
    {
        Lang::boot('en');
        $e = new ProductLimitException('WHMCS-hosting', ['errors' => []]);

        self::assertInstanceOf(ApiException::class, $e);
        self::assertSame(
            'Could not create "WHMCS-hosting" in Moloni ON: the plan\'s product limit has been reached.',
            $e->getMessage()
        );
        self::assertSame('WHMCS-hosting', $e->getData()['reference']);

        Lang::boot('pt');
        self::assertSame(
            'Não foi possível criar "WHMCS-hosting" no Moloni ON: foi atingido o limite de produtos do plano.',
            (new ProductLimitException('WHMCS-hosting'))->getMessage()
        );

        Lang::boot('en');
    }
}
