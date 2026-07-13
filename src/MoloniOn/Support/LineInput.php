<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * The billing fields of a single document line that travel together from the
 * line mapper to the line builder: what to call it, what it costs, its stable
 * product reference, a summary and a promotion discount.
 *
 * Grouping them keeps {@see \MoloniOn\Services\DocumentService::buildLine()} from
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

    private string $productName;

    public function __construct(
        string $name,
        float $price,
        ?string $reference = null,
        string $summary = '',
        float $discount = 0.0,
        string $productName = ''
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->reference = $reference;
        $this->summary = $summary;
        $this->discount = $discount;
        $this->productName = $productName;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The generic name to give the Moloni product when it is CREATED (permanent;
     * a product cannot be renamed later). Empty falls back to the display name.
     */
    public function productName(): string
    {
        return $this->productName;
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
