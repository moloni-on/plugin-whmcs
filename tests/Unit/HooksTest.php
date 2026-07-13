<?php

declare(strict_types=1);

namespace {
    // Stub WHMCS's global run_hook() so the wrapper's filter/veto logic can be
    // exercised without a WHMCS install. Backed by HooksTest's static state.
    if (!function_exists('run_hook')) {
        function run_hook(string $hook, array $vars = [])
        {
            \MoloniOn\Tests\Unit\HooksTest::$lastHook = $hook;
            \MoloniOn\Tests\Unit\HooksTest::$lastVars = $vars;

            return \MoloniOn\Tests\Unit\HooksTest::$responses;
        }
    }
}

namespace MoloniOn\Tests\Unit {

    use MoloniOn\Support\Hooks;
    use PHPUnit\Framework\TestCase;

    final class HooksTest extends TestCase
    {
        /** @var array<int,mixed> */
        public static array $responses = [];
        public static string $lastHook = '';
        /** @var array<string,mixed> */
        public static array $lastVars = [];

        protected function setUp(): void
        {
            self::$responses = [];
            self::$lastHook = '';
            self::$lastVars = [];
        }

        public function testFilterReturnsValueUnchangedWithNoResponses(): void
        {
            self::assertSame('default', Hooks::filter('X', 'default'));
        }

        public function testFilterAppliesLastNonEmptyResponse(): void
        {
            self::$responses = [null, '', 'first', 'winner'];

            self::assertSame('winner', Hooks::filter('X', 'default'));
        }

        public function testFilterIgnoresNullAndEmptyResponses(): void
        {
            self::$responses = [null, ''];

            self::assertSame('default', Hooks::filter('X', 'default'));
        }

        public function testFilterExposesCurrentValueToCallbacks(): void
        {
            Hooks::filter('X', 'payload', ['order_id' => 7]);

            self::assertSame('payload', self::$lastVars['value']);
            self::assertSame(7, self::$lastVars['order_id']);
        }

        public function testAllowsIsTrueByDefault(): void
        {
            self::assertTrue(Hooks::allows('X'));
        }

        public function testAllowsIsVetoedByAFalseResponse(): void
        {
            self::$responses = [true, false, true];

            self::assertFalse(Hooks::allows('X'));
        }

        public function testDoActionForwardsTheHookNameAndContext(): void
        {
            Hooks::doAction('MoloniOnAfterCreateDocument', ['document_id' => 42]);

            self::assertSame('MoloniOnAfterCreateDocument', self::$lastHook);
            self::assertSame(42, self::$lastVars['document_id']);
        }
    }
}
