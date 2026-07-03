<?php

declare(strict_types=1);

namespace Moloni\Support;

/**
 * Immutable pagination state for a single admin list view.
 *
 * Holds the current page's items plus the totals the pagination partial needs
 * to render prev/next/number controls. Build it either from a full in-memory
 * array ({@see fromSlice}) or from a DB query via {@see paginate}, which counts
 * the rows, clamps the requested page and fetches only that page's slice.
 */
final class Paginator
{
    /** Default rows shown per page across the admin list views. */
    public const PER_PAGE = 15;

    /** @var array<int,mixed> Rows for the current page only. */
    private array $items;

    private int $total;

    private int $page;

    private int $perPage;

    /**
     * @param array<int,mixed> $items rows for the current page only
     * @param int              $total total rows across all pages
     */
    public function __construct(array $items, int $total, int $page, int $perPage)
    {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->page = self::clamp($page, $this->total, $this->perPage);
        $this->items = $items;
    }

    /**
     * Build from a full in-memory list by slicing out the requested page.
     * Use this when the list is assembled/filtered in PHP rather than SQL.
     *
     * @param array<int,mixed> $all
     */
    public static function fromSlice(array $all, int $page, int $perPage = self::PER_PAGE): self
    {
        $perPage = max(1, $perPage);
        $page = self::clamp($page, count($all), $perPage);
        $items = array_slice($all, ($page - 1) * $perPage, $perPage);

        return new self($items, count($all), $page, $perPage);
    }

    /**
     * Build from a DB source: clamp the page against $total, then call $fetch
     * with the resolved offset/limit to retrieve only that page's rows.
     *
     * @param callable(int $offset, int $limit):array<int,mixed> $fetch
     */
    public static function paginate(int $page, int $total, int $perPage, callable $fetch): self
    {
        $perPage = max(1, $perPage);
        $page = self::clamp($page, $total, $perPage);
        $items = $total === 0 ? [] : $fetch(($page - 1) * $perPage, $perPage);

        return new self($items, $total, $page, $perPage);
    }

    /** @return array<int,mixed> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function pageCount(): int
    {
        return (int) max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }

    /** 1-based index of the first row on this page (0 when empty). */
    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    /** 1-based index of the last row on this page. */
    public function to(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }

    /**
     * Page numbers to render, keeping the first, last and a window around the
     * current page. A 0 marks an elided gap ("…") between non-adjacent numbers.
     *
     * @return array<int,int>
     */
    public function pages(int $window = 2): array
    {
        $count = $this->pageCount();
        $pages = [];

        for ($i = 1; $i <= $count; $i++) {
            $near = $i >= $this->page - $window && $i <= $this->page + $window;

            if ($i === 1 || $i === $count || $near) {
                $pages[] = $i;
            } elseif (end($pages) !== 0) {
                $pages[] = 0;
            }
        }

        return $pages;
    }

    private static function clamp(int $page, int $total, int $perPage): int
    {
        $pageCount = (int) max(1, (int) ceil($total / $perPage));

        return min(max(1, $page), $pageCount);
    }
}
