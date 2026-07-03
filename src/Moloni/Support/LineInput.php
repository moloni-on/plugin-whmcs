<?php

declare(strict_types=1);

namespace Moloni\Support;

/**
 * The billing fields of a single document line that travel together from the
 * line mapper to the line builder: what to call it, what it costs, its stable
 * product reference, a summary and a promotion discount.
 *
 * Grouping them keeps {@see \Moloni\Services\DocumentService::buildLine()} from
 * carrying a long, easily-transposed parameter list (the tax rates, fiscal zone
 * and ordering are computation context and stay as separate arguments).
 */
final class LineInput
{
    private string $name;

    private float $price;

    private ?string $reference;

    private string $summary;

    private float $discount;

    public function __construct(
        string $name,
        float $price,
        ?string $reference = null,
        string $summary = '',
        float $discount = 0.0
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->reference = $reference;
        $this->summary = $summary;
        $this->discount = $discount;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): float
    {
        return $this->price;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function discount(): float
    {
        return $this->discount;
    }
}
