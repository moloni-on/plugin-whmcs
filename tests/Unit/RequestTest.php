<?php

declare(strict_types=1);

namespace MoloniOn\Tests\Unit;

use MoloniOn\Support\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testTypedGettersReturnDefaultsWhenAbsent(): void
    {
        $request = new Request([], [], []);

        self::assertSame('', $request->query('missing'));
        self::assertSame('fallback', $request->query('missing', 'fallback'));
        self::assertSame(0, $request->queryInt('missing'));
        self::assertSame(5, $request->postInt('missing', 5));
        self::assertFalse($request->hasPost('missing'));
        self::assertSame([], $request->postArray('missing'));
    }

    public function testReadsAndCastsValues(): void
    {
        $request = new Request(
            ['order_id' => '42'],
            ['company_id' => '7', 'auto_create' => '1', 'order_ids' => ['1', '2']],
            ['REQUEST_METHOD' => 'POST']
        );

        self::assertSame(42, $request->queryInt('order_id'));
        self::assertSame(7, $request->postInt('company_id'));
        self::assertTrue($request->hasPost('auto_create'));
        self::assertSame(['1', '2'], $request->postArray('order_ids'));
        self::assertTrue($request->isPost());
    }

    public function testRequestPrefersPostOverGet(): void
    {
        // Mirrors the default $_REQUEST (request_order "GP"): POST wins on collision.
        $request = new Request(['op' => 'fromGet'], ['op' => 'fromPost'], []);

        self::assertSame('fromPost', $request->request('op'));
    }

    public function testIsPostDefaultsToGet(): void
    {
        self::assertFalse((new Request([], [], []))->isPost());
    }
}
