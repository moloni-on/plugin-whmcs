<?php

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader so `MoloniOn\` and `MoloniOn\Tests\` namespaces resolve.
 * WHMCS globals/functions are not available under test; unit tests should mock them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
