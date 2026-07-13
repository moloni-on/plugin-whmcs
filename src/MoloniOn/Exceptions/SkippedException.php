<?php

declare(strict_types=1);

namespace MoloniOn\Exceptions;

/**
 * Thrown when an order is intentionally not turned into a document (e.g. a
 * mass-payment invoice that only aggregates other invoices). Unlike
 * {@see DocumentException} this is not a failure: the order has already been
 * marked discarded and logged, so callers should report it as skipped.
 */
class SkippedException extends MoloniException
{
}
