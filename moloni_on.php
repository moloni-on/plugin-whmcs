<?php

/**
 * Moloni ON — WHMCS addon module entry point.
 *
 * Registers the WHMCS addon hooks and delegates page rendering to the
 * Dispatcher. Syncs WHMCS orders into Moloni ON as invoices/documents.
 *
 * @see ARCHITECTURE.md
 */

declare(strict_types=1);

use Moloni\Admin\Container;
use Moloni\Admin\Dispatcher;
use Moloni\Database\Installer;
use WHMCS\Config\Setting;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Module metadata shown in the WHMCS admin.
 *
 * @return array<string,mixed>
 */
function moloni_on_config(): array
{
    return [
        'name' => 'Moloni ON',
        'description' => 'Sync WHMCS orders into Moloni ON as invoices/documents.',
        'version' => '1.0.0',
        'author' => 'Moloni',
        'language' => 'english',
        'fields' => [],
    ];
}

/**
 * Create database tables on activation.
 *
 * @return array{status:string,description:string}
 */
function moloni_on_activate(): array
{
    return Installer::install();
}

/**
 * Drop database tables on deactivation.
 *
 * @return array{status:string,description:string}
 */
function moloni_on_deactivate(): array
{
    return Installer::uninstall();
}

/**
 * Ensure tables exist after a module upgrade.
 *
 * @param array<string,mixed> $vars
 */
function moloni_on_upgrade(array $vars): void
{
    Installer::install();
}

/**
 * Render the admin module page.
 *
 * @param array<string,mixed> $vars
 */
function moloni_on_output(array $vars): void
{
    $container = new Container(
        __DIR__ . '/templates',
        (string) ($vars['modulelink'] ?? ''),
        (string) Setting::getValue('SystemURL')
    );
    $dispatcher = new Dispatcher($container, $vars);

    echo $dispatcher->dispatch();
}
