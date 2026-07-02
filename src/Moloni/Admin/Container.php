<?php

declare(strict_types=1);

namespace Moloni\Admin;

use Moloni\Api\ApiClient;
use Moloni\Api\MoloniClient;
use Moloni\Services\AuthService;
use Moloni\Services\DocumentService;
use Moloni\Services\OrderService;
use Moloni\Services\SettingsService;
use Moloni\Support\Template;

/**
 * Tiny lazy service factory wiring the module's dependency graph.
 *
 * Not a general-purpose DI container — just enough to keep construction in one
 * place and share single instances within a request.
 */
class Container
{
    /** @var array<string,object> */
    private array $instances = [];

    private string $templatePath;

    private string $moduleLink;

    public function __construct(string $templatePath, string $moduleLink)
    {
        $this->templatePath = $templatePath;
        $this->moduleLink = $moduleLink;
    }

    public function apiClient(): ApiClient
    {
        return $this->instances[ApiClient::class] ??= new ApiClient();
    }

    public function moloniClient(): MoloniClient
    {
        return $this->instances[MoloniClient::class] ??= new MoloniClient($this->apiClient());
    }

    public function settings(): SettingsService
    {
        return $this->instances[SettingsService::class] ??= new SettingsService();
    }

    public function auth(): AuthService
    {
        return $this->instances[AuthService::class] ??= new AuthService($this->apiClient(), $this->moloniClient());
    }

    public function orders(): OrderService
    {
        return $this->instances[OrderService::class] ??= new OrderService();
    }

    public function documents(): DocumentService
    {
        return $this->instances[DocumentService::class] ??= new DocumentService(
            $this->moloniClient(),
            $this->settings()
        );
    }

    public function template(): Template
    {
        return $this->instances[Template::class] ??= new Template($this->templatePath, $this->moduleLink);
    }

    public function moduleLink(): string
    {
        return $this->moduleLink;
    }
}
