<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Support\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testFromSliceReturnsRequestedPage(): void
    {
        $all = range(1, 42);

        $paginator = Paginator::fromSlice($all, 2, 15);

        self::assertSame(range(16, 30), $paginator->items());
        self::assertSame(42, $paginator->total());
        self::assertSame(2, $paginator->page());
        self::assertSame(3, $paginator->pageCount());
        self::assertSame(16, $paginator->from());
        self::assertSame(30, $paginator->to());
        self::assertTrue($paginator->hasPrev());
        self::assertTrue($paginator->hasNext());
    }

    public function testFromSliceClampsPageAboveRange(): void
    {
        $paginator = Paginator::fromSlice(range(1, 42), 99, 15);

        self::assertSame(3, $paginator->page());
        self::assertSame(range(31, 42), $paginator->items());
        self::assertFalse($paginator->hasNext());
        self::assertTrue($paginator->hasPrev());
    }

    public function testFromSliceClampsPageBelowOne(): void
    {
        $paginator = Paginator::fromSlice(range(1, 42), 0, 15);

        self::assertSame(1, $paginator->page());
        self::assertFalse($paginator->hasPrev());
    }

    public function testEmptyListIsASinglePage(): void
    {
        $paginator = Paginator::fromSlice([], 1, 15);

        self::assertSame([], $paginator->items());
        self::assertSame(0, $paginator->total());
        self::assertSame(1, $paginator->pageCount());
        self::assertSame(0, $paginator->from());
        self::assertSame(0, $paginator->to());
        self::assertFalse($paginator->hasPrev());
        self::assertFalse($paginator->hasNext());
    }

    public function testPaginateClampsPageAndPassesResolvedOffset(): void
    {
        $captured = [];

        $paginator = Paginator::paginate(99, 42, 15, static function (int $offset, int $limit) use (&$captured): array {
            $captured = [$offset, $limit];

            return ['row'];
        });

        self::assertSame([30, 15], $captured, 'last page offset should be resolved from the clamped page');
        self::assertSame(3, $paginator->page());
        self::assertSame(['row'], $paginator->items());
    }

    public function testPaginateSkipsFetchWhenEmpty(): void
    {
        $called = false;

        $paginator = Paginator::paginate(1, 0, 15, static function () use (&$called): array {
            $called = true;

            return [];
        });

        self::assertFalse($called, 'no query should run when there are no rows');
        self::assertSame([], $paginator->items());
    }

    public function testPagesElidesWithGapMarker(): void
    {
        // 20 pages, current in the middle -> first, gap, window, gap, last.
        $paginator = Paginator::fromSlice(range(1, 300), 10, 15);

        self::assertSame([1, 0, 8, 9, 10, 11, 12, 0, 20], $paginator->pages());
    }

    public function testPagesWithoutGapsWhenFewPages(): void
    {
        $paginator = Paginator::fromSlice(range(1, 45), 1, 15);

        self::assertSame([1, 2, 3], $paginator->pages());
    }
}
